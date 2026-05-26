<?php
// src/Serializer/UserNormalizer.php

namespace App\Serializer;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class UserNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
        private readonly UrlGeneratorInterface $router,
        private readonly RequestStack $requestStack
    ) {}

    /**
     * This method is called by Symfony's Serializer when normalizing User objects.
     * It first delegates to the default normalizer to get the basic array representation,
     * then it adds a '_links' section with URLs for related actions (self, update, delete).
     * @param User $object The User entity to normalize into an array with HATEOAS links.
     * @param string|null $format The format being (de)serialized from or into
     * @param array $context Options that normalizers have access to
     * @return array The normalized data with HATEOAS links
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        // Set the flag to prevent infinite loops before calling the main normalizer
        $context['USER_NORMALIZER_ALREADY_CALLED'] = true;

        // 1. Let Symfony's default normalizer transform the User entity into a basic array
        $normalizedData  = $this->normalizer->normalize($object, $format, $context);

           // 2. Retrieve the current request and route name to build links dynamically
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return $normalizedData; // If there's no current request, we can't build links, so return the normalized data as is.
        }

        // 2. Generate URLs for HATEOAS links using the router and add them to the normalized data. (e.g., self, update, delete links for the User resource).
        $normalizedData ['_links'] = [
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

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        // This normalizer triggers ONLY for User entity objects
        // and avoids infinite loops by checking if it hasn't run already
        return $data instanceof User && !isset($context['USER_NORMALIZER_ALREADY_CALLED']);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            User::class => false, // false means it's not cacheable dynamically per instance
        ];
    }
}