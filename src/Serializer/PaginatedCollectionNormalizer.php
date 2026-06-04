<?php
// src/Serializer/PaginatedCollectionNormalizer.php

namespace App\Serializer;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PaginatedCollectionNormalizer implements NormalizerInterface
{
    // REMOVED: Autowire object normalizer to prevent type errors with native arrays.
    // This normalizer only processes pre-constructed array structures.
    public function __construct(
        private readonly UrlGeneratorInterface $router,
        private readonly RequestStack $requestStack
    ) {}

    /**
     * Normalizes the layout structure by appending root-level HATEOAS links.
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        // 1. Map the pre-normalized structure coming from the controller directly
        $normalizedData = [
            'meta' => $object['meta'],
            'data' => $object['data'] // Contains already normalized users
        ];

        // 2. Retrieve the current request context to compute target routing dynamically
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return $normalizedData;
        }

        $route = $request->attributes->get('_route');

        // Extract pagination parameters safely from the nested 'meta' array
        $meta = $object['meta'];
        $currentPage = (int) $meta['current_page'];
        $totalPages = (int) $meta['total_pages'];
        $limit = (int) $meta['limit'];

        // 3. Build absolute HATEOAS pagination links matching Richardson Maturity Level 3
        $normalizedData['_links'] = [
            'first' => [
                'href' => $this->router->generate($route, ['page' => 1, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL)
            ],
            'last' => [
                'href' => $this->router->generate($route, ['page' => $totalPages, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL)
            ],
            'next' => $currentPage < $totalPages ? [
                'href' => $this->router->generate($route, ['page' => $currentPage + 1, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL)
            ] : null,
            'prev' => $currentPage > 1 ? [
                'href' => $this->router->generate($route, ['page' => $currentPage - 1, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL)
            ] : null,
        ];

        return $normalizedData;
    }

    /**
     * Checks whether the given data is supported for normalization.
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        // Explicitly intercept array envelopes containing pagination data structures
        return is_array($data) && isset($data['meta'], $data['data']);
    }

    /**
     * Declares supported types for optimization and caching behaviors.
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => false, // Dynamically evaluate supportsNormalization for all formats
        ];
    }
}