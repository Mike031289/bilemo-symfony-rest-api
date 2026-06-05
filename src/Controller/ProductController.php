<?php
// src/Controller/ProductController.php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Client;
use App\Repository\ProductRepository;
use App\Serializer\PaginatedCollectionNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/products')]
final class ProductController extends AbstractController
{
    /**
     * Fetch a paginated list of available mobile products with root HATEOAS collection links
     * and authenticated client context metadata.
     */
    #[Route('', name: 'app_product_list', methods: ['GET'])]
    public function getProductList(
        ProductRepository $productRepository,
        SerializerInterface $serializer,
        PaginatedCollectionNormalizer $paginatedNormalizer,
        Request $request
    ): JsonResponse {
        // Extract pagination query parameters with default fallbacks
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 5);

        // SECURITY ANTI-DOS: Prevent clients from requesting an abusive limit amount
        if ($limit > 50) {
            $limit = 50;
        }

        // Force positive integers for page and limit parameters
        $page = $page < 1 ? 1 : $page;
        $limit = $limit < 1 ? 5 : $limit;

        /** @var Client $currentClient */
        $currentClient = $this->getUser();

        // Fetch products and computed boundaries from the repository layer
        $products = $productRepository->findPaginatedProducts($page, $limit);
        $totalItems = $productRepository->countAllProducts();
        $totalPages = (int) ceil($totalItems / $limit);

        // 1. Serialize the data envelope including the current authenticated B2B client context.
        // Explicitly pass 'client:read' group to allow client primitive properties normalization.
        $serializedData = $serializer->serialize([
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
                'client' => $currentClient
            ],
            'data' => $products
        ], 'json', ['groups' => ['product:read', 'client:read']]);

        // 2. Decode the JSON string back into a native array structure to safely append root-level controls
        $arrayData = json_decode($serializedData, true);

        // 3. Explicitly execute the PaginatedCollectionNormalizer to inject collection and client hypermedia controls
        $finalPayload = $paginatedNormalizer->normalize($arrayData, 'json');

        return new JsonResponse($finalPayload, Response::HTTP_OK);
    }

    /**
     * Retrieve precise details of a single catalog product.
     */
    #[Route('/{id}', name: 'app_product_detail', methods: ['GET'])]
    public function getProductDetail(
        Product $product,
        SerializerInterface $serializer
    ): JsonResponse {
        // Direct serialization delegates execution dynamically via context routing checks inside ProductNormalizer
        $jsonProduct = $serializer->serialize($product, 'json', ['groups' => ['product:detail']]);

        return new JsonResponse($jsonProduct, Response::HTTP_OK, [], true);
    }
}
