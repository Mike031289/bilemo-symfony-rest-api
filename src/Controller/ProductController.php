<?php
// src/Controller/ProductController.php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

final class ProductController extends AbstractController
{
    #[Route('/api/products', name: 'app_product_list', methods: ['GET'])]
    public function getProductList(ProductRepository $productRepository, SerializerInterface $serializer, Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 5);

        // Fetch paginated products
        $productList = $productRepository->findAllWithPagination($page, $limit);

        // Count total items in database (Crucial for frontend clients)
        $totalItems = $productRepository->count([]);

        // Construct a standard meta response structure
        $responsedata = [
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $totalItems,
                'total_pages' => ceil($totalItems / $limit)
            ],
            'data' => $productList
        ];

        // Serialize everything
        $jsonResponse = $serializer->serialize($responsedata, 'json');

        return new JsonResponse($jsonResponse, Response::HTTP_OK, [], true);
    }
}
