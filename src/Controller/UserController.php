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

        // If data does not exist, throw a 404 error explicitly
        if (!$responseData['data']) {
            return new JsonResponse(['message' => 'Users not found'], Response::HTTP_NOT_FOUND);
        }

        $jsonUserList = $serializer->serialize($responseData, 'json');

        return new JsonResponse($jsonUserList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/users/{id}', name: 'app_user_detail', methods: ['GET'])]
    public function getUserDetail(int $id, UserRepository $userRepository, SerializerInterface $serializer): JsonResponse
    {
        // Fetch the User manually by its ID
        $user = $userRepository->find($id);

        // FIRST CHECK: If the user does not exist, return a 404 immediately
        if (!$user) {
            return new JsonResponse(
                ['message' => 'User not found.'],
                Response::HTTP_NOT_FOUND // 404
            );
        }

        // SECOND CHECK: Now that we are sure $user exists, check ownership (403)
        if ($user->getClient() !== $this->getUser()) {
            return new JsonResponse(
                ['message' => 'Access Denied: You do not have permission to view this user.'],
                Response::HTTP_FORBIDDEN // 403
            );
        }

        // Process JSON serialization if both checks passed
        $jsonUser = $serializer->serialize($user, 'json');

        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }
}
