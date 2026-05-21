<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $categories = [];

        foreach (['Cakes', 'Pastries', 'Desserts'] as $categoryName) {
            $category = new Category();
            $category->setName($categoryName);
            $manager->persist($category);
            $categories[$categoryName] = $category;
        }

        $products = [
            ['Chocolate Fudge Cake', 'Rich layered chocolate cake with silky chocolate glaze.', 45.00, 12, 'Cakes'],
            ['Vanilla Celebration Cake', 'Soft vanilla cake for birthdays and anniversaries.', 42.50, 10, 'Cakes'],
            ['Red Velvet Slice', 'Velvety slice topped with cream cheese icing.', 28.00, 15, 'Cakes'],
            ['Butter Croissant', 'Flaky golden croissant baked fresh each morning.', 18.00, 20, 'Pastries'],
            ['Blueberry Danish', 'Buttery pastry filled with sweet blueberry compote.', 22.00, 18, 'Pastries'],
            ['Pain au Chocolat', 'Classic French pastry with a rich chocolate center.', 24.00, 16, 'Pastries'],
            ['Chocolate Mousse Cup', 'Light chocolate mousse served in a dessert cup.', 20.00, 24, 'Desserts'],
            ['Fruit Tart', 'Crisp tart shell filled with custard and fresh fruit.', 26.00, 14, 'Desserts'],
            ['Tiramisu Slice', 'Coffee-soaked layers finished with cocoa dust.', 30.00, 11, 'Desserts'],
        ];

        foreach ($products as [$name, $description, $price, $stock, $categoryName]) {
            $product = new Product();
            $product
                ->setName($name)
                ->setDescription($description)
                ->setPrice($price)
                ->setStock($stock)
                ->setCategory($categories[$categoryName])
                ->setCreatedAt(new \DateTimeImmutable());

            $manager->persist($product);
        }

        $manager->flush();
    }
}
