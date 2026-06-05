<?php
// src/Controller/ClientController.php

namespace App\Controller;

use App\Entity\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

final class ClientController extends AbstractController
{
    /**
     * Retrieve details and automated HATEOAS navigation controls for the authenticated Client session.
     */
    #[Route('/profile', name: 'app_client_profile', methods: ['GET'])]
    public function getProfile(SerializerInterface $serializer): JsonResponse
    {
        /** @var Client|null $currentClient */
        $currentClient = $this->getUser();

        if (!$currentClient) {
            return new JsonResponse(
                ['message' => 'JWT Token validation failed or user context missing.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // The serializer engine automatically cascades normalization through ClientNormalizer
        $jsonClient = $serializer->serialize($currentClient, 'json', ['groups' => ['client:read']]);

        return new JsonResponse($jsonClient, Response::HTTP_OK, [], true);
    }
}
