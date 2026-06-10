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
     * @param NormalizerInterface $normalizer Injected native ObjectNormalizer to read entity primitive properties.
     */
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
        private readonly UrlGeneratorInterface $router,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * Transforms a single User entity object into an array structure enriched with standardized HATEOAS links.
     *
     * @param User $object The User entity instance to map.
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

        // 2. Define standard item links (Self always maps back to its precise singular resource URI)
        $normalizedData['_links'] = [
            'self' => [
                'href' => $this->router->generate('app_user_detail', ['id' => $object->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
            ],
            'users' => [
                'href' => $this->router->generate('app_user_list', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ],
            'update' => [
                'href' => $this->router->generate('app_user_edit', ['id' => $object->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
            ],
            'delete' => [
                'href' => $this->router->generate('app_user_delete', ['id' => $object->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
            ]
        ];

        // 3. Inject B2B Client contextual pointer if the relation is defined on the entity object
        if (method_exists($object, 'getClient') && $object->getClient()) {
            $normalizedData['_links']['client'] = [
                'href' => $this->router->generate('app_client_profile', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ];
        }

        return $normalizedData;
    }

    /**
     * Validates whether the incoming payload qualifies for User hypermedia enrichment.
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof User && !isset($context[self::class . '_ALREADY_CALLED']);
    }

    /**
     * Optimizes normalizer caching routines within the Symfony Dependency Injection component.
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            User::class => true,
        ];
    }
}
