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
    #[Route('/profile', name: 'app_client_profile', methods: ['GET'])]
    public function getProfile(SerializerInterface $serializer): JsonResponse
    {
        // Get the currently authenticated B2B Client (from JWT token)
        /** @var Client|null $currentClient */
        $currentClient = $this->getUser();

        // Safety fallback check in case security execution context misfires
        if (!$currentClient) {
            return new JsonResponse(
                ['message' => 'JWT Token validation failed or user context missing.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Serialize the single Client object into JSON
        $jsonClient = $serializer->serialize($currentClient, 'json', ['groups' => ['client:read']]);

        return new JsonResponse($jsonClient, Response::HTTP_OK, [], true);
    }
}
