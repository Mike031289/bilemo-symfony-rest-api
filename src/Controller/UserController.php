<?php
// src/Controller/UserController.php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

final class UserController extends AbstractController
{
    #[Route('/api/users', name: 'app_user_list', methods: ['GET'])]
    public function getUserList(UserRepository $userRepository, SerializerInterface $serializer, Request $request): JsonResponse
    {
        // Get the currently authenticated B2B Client (from JWT token)
        $currentClient = $this->getUser();

        // Extract pagination parameters from query string
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 5);

        // Fetch ONLY the users belonging to this specific client
        $userList = $userRepository->findByClientWithPagination($currentClient, $page, $limit);
        $totalItems = $userRepository->countByClient($currentClient);

        // Construct the professional response structure with metadata
        $responseData = [
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $totalItems,
                'total_pages' => ceil($totalItems / $limit)
            ],
            'data' => $userList
        ];

        // Serialize and return : Process JSON serialization if both checks passed
        $jsonUserList = $serializer->serialize($responseData, 'json');

        return new JsonResponse($jsonUserList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/users/{id}', name: 'app_user_detail', methods: ['GET'])]
    #[IsGranted('CAN_SEE_USER', subject: 'user')]
    public function getUserDetail(User $user, SerializerInterface $serializer): JsonResponse
    {
        // Note: Symfony automatically throws a 404 Not Found if the {id} does not exist in the database.
        // Note: The #[IsGranted] attribute automatically triggers UserVoter and throws a 403 Forbidden if access is denied.

        // Serialize the single User object into JSON
        $jsonUser = $serializer->serialize($user, 'json');

        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }
}
