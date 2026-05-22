<?php
// src/Controller/UserController.php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

final class UserController extends AbstractController
{
    #[Route('/api/users', name: 'app_user_list', methods: ['GET'])]
    public function getUserList(UserRepository $userRepository, SerializerInterface $serializer, Request $request): JsonResponse
    {
        // Get the currently authenticated B2B Client(from JWT token)
        $currentClient = $this->getUser();

        // Extract pagination parameters
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 5);

        // Fetch ONLY the users belonging to this Client with pagination
        $userList = $userRepository->findByClientWithPagination($currentClient, $page, $limit);
        $totalItems = $userRepository->countByClient($currentClient);

        // Structure the response (same professional standard as products)
        $responseData = [
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $totalItems,
                'total_pages' => ceil($totalItems / $limit)
            ],
            'data' => $userList
        ];

        $jsonUserList = $serializer->serialize($responseData, 'json');

        return new JsonResponse($jsonUserList, Response::HTTP_OK, [], true);
    }
}
