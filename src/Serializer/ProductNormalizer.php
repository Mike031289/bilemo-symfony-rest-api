<?php
// src/Serializer/ProductNormalizer.php

namespace App\Serializer;

use App\Entity\Product;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ProductNormalizer implements NormalizerInterface
{
    /**
     * @param NormalizerInterface $normalizer Injected native ObjectNormalizer to read entity primitive properties.
     */
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
        private readonly UrlGeneratorInterface $router,
        private readonly RequestStack $requestStack
    ) {}

    /**
     * Transforms a single Product entity object into an array structure enriched with standardized HATEOAS links.
     *
     * @param Product $object
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::class . '_ALREADY_CALLED'] = true;

        // 1. Delegate core properties normalization to the native platform ObjectNormalizer
        $normalizedData = $this->normalizer->normalize($object, $format, $context);

        $request = $this->requestStack->getCurrentRequest();
        if (!$request || !is_array($normalizedData)) {
            return $normalizedData;
        }

        // 2. Extract routing signature context to adapt hypermedia links appropriately
        $currentRoute = $request->attributes->get('_route');

        // 3. Define standard item links (Self always maps back to its precise singular resource URI)
        $normalizedData['_links'] = [
            'self' => [
                'href' => $this->router->generate('app_product_detail', ['id' => $object->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
            ],
            'products' => [
                'href' => $this->router->generate('app_product_list', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ]
        ];

        // 4. Inject B2B Client contextual pointer if the relation is mapped and defined on the entity object
        if (method_exists($object, 'getClient') && $object->getClient()) {
            $normalizedData['_links']['client'] = [
                'href' => $this->router->generate('app_client_profile', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ];
        }

        return $normalizedData;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Product && !isset($context[self::class . '_ALREADY_CALLED']);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Product::class => true,
        ];
    }
}
