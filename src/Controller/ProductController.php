<?php
// src/Controller/ProductController.php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Client;
use App\Repository\ProductRepository;
use App\Serializer\PaginatedCollectionNormalizer;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/products')]
#[OA\Tag(name: 'Products')]
#[OA\Response(
    response: 401,
    description: 'Unauthorized - Invalid, missing or expired JWT Bearer token'
)]
final class ProductController extends AbstractController
{
    /**
     * Fetch a paginated list of available mobile products with HTTP Caching.
     */
    #[Route('', name: 'app_product_list', methods: ['GET'])]
    #[OA\Get(
        path: '/products',
        summary: 'Retrieve paginated mobile product catalog',
        description: 'Fetches a paginated slice of globally available mobile devices. This endpoint includes HTTP caching parameters.'
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'The page number framework navigation index',
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'The maximum capacity of records delivered inside a single collection slice (Anti-DOS capped at 50)',
        schema: new OA\Schema(type: 'integer', default: 5)
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Returns the HATEOAS paginated collection envelope',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'meta', type: 'object', properties: [
                    new OA\Property(property: 'current_page', type: 'integer', example: 1),
                    new OA\Property(property: 'limit', type: 'integer', example: 5),
                    new OA\Property(property: 'total_items', type: 'integer', example: 24),
                    new OA\Property(property: 'total_pages', type: 'integer', example: 5),
                    new OA\Property(property: 'client', ref: new Model(type: Client::class, groups: ['client:read']))
                ]),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: Product::class, groups: ['product:read']))
                ),
                new OA\Property(property: '_links', type: 'object', properties: [
                    new OA\Property(property: 'self', type: 'string', example: '/products?page=1&limit=5'),
                    new OA\Property(property: 'next', type: 'string', example: '/products?page=2&limit=5')
                ])
            ]
        )
    )]
    public function getProductList(
        ProductRepository $productRepository,
        SerializerInterface $serializer,
        PaginatedCollectionNormalizer $paginatedNormalizer,
        Request $request
    ): Response {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 5);

        $limit = $limit > 50 ? 50 : $limit;
        $page = $page < 1 ? 1 : $page;
        $limit = $limit < 1 ? 5 : $limit;

        $etag = md5('products_list_page_' . $page . '_limit_' . $limit);

        $response = new Response();
        $response->setEtag($etag);
        $response->setPublic();

        if ($response->isNotModified($request)) {
            return $response;
        }

        /** @var Client $currentClient */
        $currentClient = $this->getUser();

        $products = $productRepository->findPaginatedProducts($page, $limit);
        $totalItems = $productRepository->countAllProducts();
        $totalPages = (int) ceil($totalItems / $limit);

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

        $arrayData = json_decode($serializedData, true);
        $finalPayload = $paginatedNormalizer->normalize($arrayData, 'json');

        $response->setContent(json_encode($finalPayload));
        $response->headers->set('Content-Type', 'application/json');
        $response->setMaxAge(3600);

        return $response;
    }

    /**
     * Retrieve precise details of a single catalog product with HTTP Caching.
     */
    #[Route('/{id}', name: 'app_product_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/products/{id}',
        summary: 'Retrieve a single product details',
        description: 'Fetches technical details and description parameters of a specific mobile product identified by its ID.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'The unique structural system record identifier of the product',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Returns the localized single product detail entity model',
        content: new OA\JsonContent(ref: new Model(type: Product::class, groups: ['product:detail']))
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found - The requested product record reference does not exist'
    )]
    public function getProductDetail(
        Product $product,
        Request $request,
        SerializerInterface $serializer
    ): Response {
        $etag = md5('product_detail_' . $product->getId());

        $response = new Response();
        $response->setEtag($etag);
        $response->setPublic();

        if ($response->isNotModified($request)) {
            return $response;
        }

        $jsonProduct = $serializer->serialize($product, 'json', ['groups' => ['product:detail']]);

        $response->setContent($jsonProduct);
        $response->headers->set('Content-Type', 'application/json');
        $response->setMaxAge(3600);

        return $response;
    }
}