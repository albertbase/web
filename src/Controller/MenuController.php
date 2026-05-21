<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MenuController extends AbstractController
{
    #[Route('/menu', name: 'app_menu')]
    public function index(Request $request, ProductRepository $productRepo, CategoryRepository $categoryRepo): Response
    {
        $searchTerm = $request->query->get('q');
        $selectedCategory = $request->query->get('category');

        $products = $productRepo->findByMenuFilters($searchTerm, $selectedCategory);
        $categories = $categoryRepo->findAll();

        return $this->render('product/list.html.twig', [
            'products' => $products,
            'search' => $searchTerm,
            'selectedCategory' => $selectedCategory,
            'categories' => $categories,
        ]);
    }
}
