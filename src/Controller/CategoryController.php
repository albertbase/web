<?php

namespace App\Controller;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Form\CategoryType;
use Symfony\Component\Security\Http\Attribute\IsGranted;



final class CategoryController extends AbstractController
{
    // #[Route('/category', name: 'app_category')]
    // public function index(): Response
    // {
    //     return $this->render('category/index.html.twig', [
    //         'controller_name' => 'CategoryController',
    //     ]);
    // }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/categories', name: 'admin_categories')]
    public function index(EntityManagerInterface $em): Response
    {
    $categories = $em->getRepository(Category::class)->findAll();
    return $this->render('admin/categories.html.twig', [
        'categories' => $categories,
    ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/categories/new', name: 'admin_categories_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
    $category = new Category();
    $form = $this->createForm(CategoryType::class, $category);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->persist($category);
        $em->flush();
        $this->addFlash('success', sprintf('Category "%s" created successfully.', $category->getName()));
        return $this->redirectToRoute('admin_categories');
    }

    return $this->render('admin/category_form.html.twig', [
        'form' => $form->createView(),
    ]); 
    }

    #[Route('/categories', name: 'category_browser')]
    public function categoryPage(Request $request, CategoryRepository $categoryRepo, ProductRepository $productRepo): Response
    {
        $categories = $categoryRepo->findAllSorted();
        $selected = $request->query->get('selected');

        $selectedCategory = $selected ? $categoryRepo->findOneBy(['name' => $selected]) : null;
        $products = $selectedCategory ? $productRepo->findBy(['category' => $selectedCategory]) : [];

        return $this->render('category/page.html.twig', [
            'categories' => $categories,
            'selectedCategory' => $selected,
            'products' => $products,
        ]);
    }

    #[Route('/category/{name}', name: 'category_page')]
    public function showByCategory(string $name, CategoryRepository $categoryRepo, ProductRepository $productRepo): Response
    {
        $category = $categoryRepo->findOneBy(['name' => $name]);

        if (!$category) {
            throw $this->createNotFoundException('Category not found.');
        }

        $products = $productRepo->findBy(['category' => $category]);

        return $this->render('category/page.html.twig', [
            'selectedCategory' => $name,
            'products' => $products,
        ]);
    }


    #[Route('/categories/list', name: 'category_list')]
public function list(CategoryRepository $categoryRepo): Response
{
    $categories = $categoryRepo->findAllSorted();

    return $this->render('category/list.html.twig', [
        'categories' => $categories,
    ]);
}

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/categories/{id}/edit', name: 'admin_categories_edit')]
public function edit(Category $category, Request $request, EntityManagerInterface $em): Response
{
    $form = $this->createForm(CategoryType::class, $category);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->flush();
        $this->addFlash('success', 'Category updated successfully.');
        return $this->redirectToRoute('admin_categories');
    }

    return $this->render('admin/category_edit.html.twig', [
        'form' => $form->createView(),
        'category' => $category,
    ]);
}


#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/categories/{id}/delete', name: 'admin_categories_delete', methods: ['POST'])]
public function delete(Category $category, Request $request, EntityManagerInterface $em): Response
{
    if ($this->isCsrfTokenValid('delete' . $category->getId(), $request->request->get('_token'))) {
        $em->remove($category);
        $em->flush();
        $this->addFlash('success', 'Category deleted successfully.');
    }

    return $this->redirectToRoute('admin_categories');
}

    

}
