<?php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\RealTimeNotificationService;

#[Route('/admin/products', name: 'api_admin_products_')]
#[IsGranted('ROLE_STAFF')]
class ApiAdminProductController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, ProductRepository $productRepository): JsonResponse
    {
        $search = trim((string) $request->query->get('search', ''));
        $categoryIdRaw = $request->query->get('categoryId');
        $categoryId = is_numeric($categoryIdRaw) ? (int) $categoryIdRaw : null;
        $limit = max(1, min(100, (int) $request->query->get('limit', 50)));
        $offset = max(0, (int) $request->query->get('offset', 0));

        $products = array_map(
            fn (Product $product): array => $this->mapProduct($product, $request),
            $productRepository->findForAdminList(
                $search !== '' ? $search : null,
                $categoryId,
                $limit,
                $offset
            )
        );

        return $this->apiSuccess('Products retrieved successfully.', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'categoryId' => $categoryId,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(int $id, Request $request, ProductRepository $productRepository): JsonResponse
    {
        $product = $productRepository->findWithCreator($id);
        if (!$product instanceof Product) {
            return $this->apiError('Product not found.', 404);
        }

        return $this->apiSuccess('Product retrieved successfully.', [
            'product' => $this->mapProduct($product, $request),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        RealTimeNotificationService $realTimeNotificationService
    ): JsonResponse {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->apiError('Invalid JSON payload.', 400);
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return $this->apiError('Product name is required.', 400);
        }

        if ($productRepository->findOneBy(['name' => $name]) instanceof Product) {
            return $this->apiError('A product with this name already exists.', 409);
        }

        $price = filter_var($data['price'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($price === false || $price <= 0) {
            return $this->apiError('Price must be a number greater than zero.', 400);
        }

        $stock = filter_var($data['stock'] ?? null, FILTER_VALIDATE_INT);
        if ($stock === false || $stock < 0) {
            return $this->apiError('Stock must be an integer greater than or equal to zero.', 400);
        }

        $product = new Product();
        $product->setName($name);
        $product->setDescription(isset($data['description']) ? (string) $data['description'] : null);
        $product->setPrice((float) $price);
        $product->setStock((int) $stock);
        $product->setImage(isset($data['image']) ? trim((string) $data['image']) : null);

        if (array_key_exists('categoryId', $data) && $data['categoryId'] !== null && $data['categoryId'] !== '') {
            $category = $categoryRepository->find((int) $data['categoryId']);
            if ($category === null) {
                return $this->apiError('Category not found.', 404);
            }
            $product->setCategory($category);
        } elseif (array_key_exists('categoryName', $data)) {
            $categoryName = trim((string) $data['categoryName']);
            if ($categoryName !== '') {
                $category = $categoryRepository->findOneByNormalizedName($categoryName);
                if ($category === null) {
                    return $this->apiError('Category not found.', 404);
                }
                $product->setCategory($category);
            }
        }

        $user = $this->getUser();
        if ($user instanceof User) {
            $product->setCreatedBy($user);
        }

        $violations = $validator->validate($product);
        if (count($violations) > 0) {
            return $this->apiError('Validation failed.', 422, [
                'errors' => $this->validationErrors($violations),
            ]);
        }

        $entityManager->persist($product);
        $entityManager->flush();

        $realTimeNotificationService->publishInventoryUpdate($product);
        $realTimeNotificationService->publishNotification(
            'New Product Added',
            sprintf('Product "%s" has been added with %d stock.', $product->getName(), $product->getStock()),
            'success'
        );

        return $this->apiSuccess('Product created successfully.', [
            'product' => $this->mapProduct($product, $request),
        ], 201);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(
        int $id,
        Request $request,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        RealTimeNotificationService $realTimeNotificationService
    ): JsonResponse {
        $product = $productRepository->find($id);
        if (!$product instanceof Product) {
            return $this->apiError('Product not found.', 404);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->apiError('Invalid JSON payload.', 400);
        }

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                return $this->apiError('Product name cannot be empty.', 400);
            }

            $existing = $productRepository->findOneBy(['name' => $name]);
            if ($existing instanceof Product && $existing->getId() !== $product->getId()) {
                return $this->apiError('A product with this name already exists.', 409);
            }

            $product->setName($name);
        }

        if (array_key_exists('description', $data)) {
            $product->setDescription($data['description'] !== null ? (string) $data['description'] : null);
        }

        if (array_key_exists('price', $data)) {
            $price = filter_var($data['price'], FILTER_VALIDATE_FLOAT);
            if ($price === false || $price <= 0) {
                return $this->apiError('Price must be a number greater than zero.', 400);
            }
            $product->setPrice((float) $price);
        }

        if (array_key_exists('stock', $data)) {
            $stock = filter_var($data['stock'], FILTER_VALIDATE_INT);
            if ($stock === false || $stock < 0) {
                return $this->apiError('Stock must be an integer greater than or equal to zero.', 400);
            }
            $product->setStock((int) $stock);
        }

        if (array_key_exists('image', $data)) {
            $product->setImage($data['image'] !== null ? trim((string) $data['image']) : null);
        }

        if (array_key_exists('categoryId', $data)) {
            if ($data['categoryId'] === null || $data['categoryId'] === '') {
                $product->setCategory(null);
            } else {
                $category = $categoryRepository->find((int) $data['categoryId']);
                if ($category === null) {
                    return $this->apiError('Category not found.', 404);
                }
                $product->setCategory($category);
            }
        }

        $violations = $validator->validate($product);
        if (count($violations) > 0) {
            return $this->apiError('Validation failed.', 422, [
                'errors' => $this->validationErrors($violations),
            ]);
        }

        $entityManager->flush();

        $realTimeNotificationService->publishInventoryUpdate($product);
        $realTimeNotificationService->publishNotification(
            'Product Updated',
            sprintf('Product "%s" stock updated to %d.', $product->getName(), $product->getStock()),
            'info'
        );

        return $this->apiSuccess('Product updated successfully.', [
            'product' => $this->mapProduct($product, $request),
        ]);
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        int $id,
        ProductRepository $productRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $product = $productRepository->find($id);
        if (!$product instanceof Product) {
            return $this->apiError('Product not found.', 404);
        }

        if ($product->getOrderItems()->count() > 0) {
            return $this->apiError('Cannot delete product because it is referenced by orders.', 409);
        }

        $entityManager->remove($product);
        $entityManager->flush();

        return $this->apiSuccess('Product deleted successfully.');
    }

    private function mapProduct(Product $product, Request $request): array
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'stock' => $product->getStock(),
            'image' => $product->getImage(),
            'imageUrl' => $this->buildImageUrl($request, $product->getImage()),
            'category' => $product->getCategory() ? [
                'id' => $product->getCategory()?->getId(),
                'name' => $product->getCategory()?->getName(),
            ] : null,
            'createdBy' => $product->getCreatedBy() ? [
                'id' => $product->getCreatedBy()?->getId(),
                'username' => $product->getCreatedBy()?->getUserIdentifier(),
            ] : null,
            'createdAt' => $product->getCreatedAt()?->format(DATE_ATOM),
        ];
    }

    private function buildImageUrl(Request $request, ?string $image): ?string
    {
        if ($image === null || $image === '') {
            return null;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/').'/uploads/products/'.$image;
    }

    private function validationErrors(iterable $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $field = $violation->getPropertyPath() ?: 'general';
            $errors[$field][] = $violation->getMessage();
        }

        return $errors;
    }
}
