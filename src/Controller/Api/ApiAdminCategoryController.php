<?php

namespace App\Controller\Api;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/categories', name: 'api_admin_categories_')]
#[IsGranted('ROLE_STAFF')]
class ApiAdminCategoryController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(CategoryRepository $categoryRepository): JsonResponse
    {
        $categories = array_map(
            static fn (array $row): array => [
                'id' => $row['category']->getId(),
                'name' => $row['category']->getName(),
                'productCount' => $row['productCount'],
            ],
            $categoryRepository->findAllWithProductCounts()
        );

        return $this->apiSuccess('Categories retrieved successfully.', [
            'categories' => $categories,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(int $id, CategoryRepository $categoryRepository): JsonResponse
    {
        $category = $categoryRepository->find($id);
        if (!$category instanceof Category) {
            return $this->apiError('Category not found.', 404);
        }

        return $this->apiSuccess('Category retrieved successfully.', [
            'category' => $this->mapCategory($category),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        CategoryRepository $categoryRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->apiError('Invalid JSON payload.', 400);
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return $this->apiError('Category name is required.', 400);
        }

        if ($categoryRepository->findOneByNormalizedName($name) !== null) {
            return $this->apiError('Category name already exists.', 409);
        }

        $category = new Category();
        $category->setName($name);

        $violations = $validator->validate($category);
        if (count($violations) > 0) {
            return $this->apiError('Validation failed.', 422, [
                'errors' => $this->validationErrors($violations),
            ]);
        }

        try {
            $entityManager->persist($category);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->apiError('Category name already exists.', 409);
        }

        return $this->apiSuccess('Category created successfully.', [
            'category' => $this->mapCategory($category),
        ], 201);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(
        int $id,
        Request $request,
        CategoryRepository $categoryRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $category = $categoryRepository->find($id);
        if (!$category instanceof Category) {
            return $this->apiError('Category not found.', 404);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->apiError('Invalid JSON payload.', 400);
        }

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                return $this->apiError('Category name cannot be empty.', 400);
            }

            $existing = $categoryRepository->findOneByNormalizedName($name);
            if ($existing instanceof Category && $existing->getId() !== $category->getId()) {
                return $this->apiError('Category name already exists.', 409);
            }

            $category->setName($name);
        }

        $violations = $validator->validate($category);
        if (count($violations) > 0) {
            return $this->apiError('Validation failed.', 422, [
                'errors' => $this->validationErrors($violations),
            ]);
        }

        try {
            $entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->apiError('Category name already exists.', 409);
        }

        return $this->apiSuccess('Category updated successfully.', [
            'category' => $this->mapCategory($category),
        ]);
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        int $id,
        CategoryRepository $categoryRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $category = $categoryRepository->find($id);
        if (!$category instanceof Category) {
            return $this->apiError('Category not found.', 404);
        }

        if ($category->getProducts()->count() > 0) {
            return $this->apiError(
                'Cannot delete category because it is still assigned to products.',
                409
            );
        }

        $entityManager->remove($category);
        $entityManager->flush();

        return $this->apiSuccess('Category deleted successfully.');
    }

    private function mapCategory(Category $category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'productCount' => $category->getProducts()->count(),
        ];
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
