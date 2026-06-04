<?php
// src/Serializer/UserNormalizer.php

namespace App\Serializer;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class UserNormalizer implements NormalizerInterface
{
    /**
     * @param NormalizerInterface $normalizer Injected primitive ObjectNormalizer to read entity fields.
     */
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
        private readonly UrlGeneratorInterface $router,
        private readonly RequestStack $requestStack
    ) {}

    /**
     * Transforms a single User entity object into a basic array layout enriched with item-level HATEOAS links.
     *
     * @param User $object The User entity instance to map.
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        // Prevent recursive rendering loops if this normalizer gets cross-triggered downstream
        $context[self::class . '_ALREADY_CALLED'] = true;

        // 1. Delegate core property hydration to the native platform ObjectNormalizer
        $normalizedData = $this->normalizer->normalize($object, $format, $context);

        // 2. Resolve current request footprint to safeguard URL generation context
        $request = $this->requestStack->getCurrentRequest();
        if (!$request || !is_array($normalizedData)) {
            return $normalizedData;
        }

        // 3. Inject item-specific hypermedia controls matching Richardson Maturity Level 3
        $normalizedData['_links'] = [
            'self' => [
                'href' => $this->router->generate('app_user_detail', ['id' => $object->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
            ],
            'update' => [
                'href' => $this->router->generate('app_user_edit', ['id' => $object->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
            ],
            'delete' => [
                'href' => $this->router->generate('app_user_delete', ['id' => $object->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
            ]
        ];

        return $normalizedData;
    }

    /**
     * Validates whether the incoming payload qualifies for User hypermedia enrichment.
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        // Execute only if data is a User instance and hasn't been intercepted by this class yet
        return $data instanceof User && !isset($context[self::class . '_ALREADY_CALLED']);
    }

    /**
     * Optimizes normalizer caching routines within the Symfony Dependency Injection component.
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            User::class => true, // Tells Symfony that this normalizer is strictly caching-optimized for User objects
        ];
    }
}