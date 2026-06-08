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
     * Fetch a paginated list of available mobile products with HTTP Caching.
     */
    #[Route('', name: 'app_product_list', methods: ['GET'])]
    public function getProductList(
        ProductRepository $productRepository,
        SerializerInterface $serializer,
        PaginatedCollectionNormalizer $paginatedNormalizer,
        Request $request
    ): Response {
        // Extract pagination query parameters with default fallbacks
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 5);

        // SECURITY ANTI-DOS: Prevent clients from requesting an abusive limit amount
        $limit = $limit > 50 ? 50 : $limit;

        // Force positive integers for page and limit parameters
        $page = $page < 1 ? 1 : $page;
        $limit = $limit < 1 ? 5 : $limit;

        // Generate a unique cache fingerprint (ETag) based on pagination boundaries
        $etag = md5('products_list_page_' . $page . '_limit_' . $limit);

        // Initialize empty HTTP Response and configure validation cache keys
        $response = new Response();
        $response->setEtag($etag);
        $response->setPublic();

        // Check if the resource footprint matches the client's 'If-None-Match' header
        if ($response->isNotModified($request)) {
            // Early bypass: Return HTTP 304 Not Modified immediately to skip heavy logic execution
            return $response;
        }

        /** @var Client $currentClient */
        $currentClient = $this->getUser();

        // Fetch products and computed boundaries from the repository layer
        $products = $productRepository->findPaginatedProducts($page, $limit);
        $totalItems = $productRepository->countAllProducts();
        $totalPages = (int) ceil($totalItems / $limit);

        // Serialize the data envelope including the current authenticated B2B client context.
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

        // Decode the JSON string back into a native array structure to safely append root-level controls
        $arrayData = json_decode($serializedData, true);

        // Explicitly execute the PaginatedCollectionNormalizer to inject collection and client hypermedia controls
        $finalPayload = $paginatedNormalizer->normalize($arrayData, 'json');

        // Populate response configuration with content payload, content-type, and expiration rules
        $response->setContent(json_encode($finalPayload));
        $response->headers->set('Content-Type', 'application/json');
        $response->setMaxAge(3600); // Instruct cache layers to trust this payload for 1 hour

        return $response;
    }

    /**
     * Retrieve precise details of a single catalog product with HTTP Caching.
     */
    #[Route('/{id}', name: 'app_product_detail', methods: ['GET'])]
    public function getProductDetail(
        Product $product,
        Request $request,
        SerializerInterface $serializer
    ): Response {
        // Generate a unique cache fingerprint (ETag) using entity properties or ID hash
        $etag = md5('product_detail_' . $product->getId());

        // Initialize empty HTTP Response and configure validation cache keys
        $response = new Response();
        $response->setEtag($etag);
        $response->setPublic();

        // Check if the resource footprint matches the client's 'If-None-Match' header
        if ($response->isNotModified($request)) {
            // Early bypass: Return HTTP 304 Not Modified immediately
            return $response;
        }

        // Direct serialization delegates execution dynamically via context routing checks inside ProductNormalizer
        $jsonProduct = $serializer->serialize($product, 'json', ['groups' => ['product:detail']]);

        // Populate response data footprint and set expiration lifecycle rules
        $response->setContent($jsonProduct);
        $response->headers->set('Content-Type', 'application/json');
        $response->setMaxAge(3600);

        return $response;
    }
}
