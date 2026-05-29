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
    #[Route('/products', name: 'app_product_list', methods: ['GET'])]
    public function getProductList(ProductRepository $productRepository, SerializerInterface $serializer, Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 5);

        // Fetch paginated products
        $productList = $productRepository->findAllWithPagination($page, $limit);

        // Count total items in database (Crucial for frontend clients)
        $totalItems = $productRepository->count([]);

        // Construct a standard meta response structure
        $responseData = [
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $totalItems,
                'total_pages' => ceil($totalItems / $limit)
            ],
            'data' => $productList
        ];

        // If data does not exist, throw a 404 error explicitly
        if (!$responseData['data']) {
            return new JsonResponse(['message' => 'Products not found'], Response::HTTP_NOT_FOUND);
        }

        // Serialize everything
        $jsonResponse = $serializer->serialize($responseData, 'json', ['groups' => ['product:read']]);

        return new JsonResponse($jsonResponse, Response::HTTP_OK, [], true);
    }

    #[Route('/products/{id}', name: 'app_product_detail', methods: ['GET'])]
    public function getProductDetail(int $id, ProductRepository $productRepository, SerializerInterface $serializer): JsonResponse
    {
        // Manually fetch the product by its ID
        $product = $productRepository->find($id);

        // If the product does not exist, throw a 404 error explicitly
        if (!$product) {
            return new JsonResponse(['message' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        // Convert the single Product object into a clean JSON string
        $jsonProduct = $serializer->serialize($product, 'json', ['groups' => ['product:read']]);

        // Return a 200 OK JsonResponse
        return new JsonResponse($jsonProduct, Response::HTTP_OK, [], true);
    }
}
