<?php
// src/Controller/ProductController.php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

final class ProductController extends AbstractController
{
    #[Route('/api/products', name: 'app_product_list', methods: ['GET'])]
    public function getProductList(ProductRepository $productRepository, SerializerInterface $serializer): JsonResponse
    {
        // Fetch all product entities from the database
        $productList = $productRepository->findAll();

        // Convert the PHP object array into a clean JSON string using the Serializer component
        $jsonProductList = $serializer->serialize($productList, 'json');

        // Return a JsonResponse with the serialized data and a 200 OK HTTP status
        // The fourth parameter 'true' tells Symfony that the data is already a JSON string
        return new JsonResponse($jsonProductList, Response::HTTP_OK, [], true);
    }
}
