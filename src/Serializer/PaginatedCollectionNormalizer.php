<?php
// src/Serializer/PaginatedCollectionNormalizer.php

namespace App\Serializer;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PaginatedCollectionNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
        private readonly UrlGeneratorInterface $router,
        private readonly RequestStack $requestStack
    ) {}

    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        // Set the flag to avoid infinite loops and normalize the base array structure
        $context['PAGINATED_NORMALIZER_ALREADY_CALLED'] = true;

        // 1. Let Symfony's default normalizer transform the User entity into a basic array
        $normalizedData = $this->normalizer->normalize($object, $format, $context);

        // 2. Retrieve the current request and route name to build links dynamically
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return $normalizedData; // If there's no current request, we can't build links, so return the normalized data as is.
        }

        $route = $request->attributes->get('_route');
        $meta = $object['meta'];
        $currentPage = $meta['current_page'];
        $totalPages = $meta['total_pages'];
        $limit = $meta['limit'];

        // 3. Build HATEOAS pagination links
        $normalizedData['_links'] = [
            // 'first' link always points to the first page, even if we're already on it
            'first' => [
                'href' => $this->router->generate($route, ['page' => 1, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL)
            ],
            // 'last' link always points to the last page, even if we're already on it
            'last' => [
                'href' => $this->router->generate($route, ['page' => $totalPages, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL)
            ],
            // 'next' link only if there is a next page, otherwise null
            'next' => $currentPage < $totalPages ? [
                'href' => $this->router->generate($route, ['page' => $currentPage + 1, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL)
            ] : null,
            // 'prev' link only if there is a previous page, otherwise null
            'prev' => $currentPage > 1 ? [
                'href' => $this->router->generate($route, ['page' => $currentPage - 1, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL)
            ] : null,
        ];

        return $normalizedData;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        // This normalizer activates only if the data is an array containing our specific pagination metadata
        return is_array($data)
            && isset($data['meta'], $data['data'])
            && !isset($context['PAGINATED_NORMALIZER_ALREADY_CALLED']);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            'array' => false,
        ];
    }
}