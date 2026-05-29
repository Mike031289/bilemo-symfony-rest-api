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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserController extends AbstractController
{
    #[Route('/users', name: 'app_user_list', methods: ['GET'])]
    public function getUserList(
        UserRepository $userRepository,
        SerializerInterface $serializer,
        Request $request
    ): JsonResponse {
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
        $jsonUserList = $serializer->serialize($responseData, 'json', ['groups' => ['user:read']]);

        return new JsonResponse($jsonUserList, Response::HTTP_OK, [], true);
    }

    #[Route('/users', name: 'app_user_create', methods: ['POST'])]
    public function createUser(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        // Get the currently authenticated B2B Client from the JWT token
        $currentClient = $this->getUser();

        // Deserialize the incoming JSON payload directly into a User entity instance
        /** @var User $user */
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        // Security & Business Logic: Forcefully link the new User to the authenticated Client
        $user->setClient($currentClient);

        // 4. Validate the entity based on constraints defined in User.php
            $errors = $validator->validate($user);

            if ($errors->count() > 0) {
                // If there are validation errors, serialize them and return an HTTP 400 Bad Request
                $jsonErrors = $serializer->serialize($errors, 'json');
                return new JsonResponse($jsonErrors, Response::HTTP_BAD_REQUEST, [], true);
            }

        // Persist and flush the new entity into the database
        $em->persist($user);
        $em->flush();

        // Serialize the newly created user to return it in the response
        $jsonUser = $serializer->serialize($user, 'json', ['groups' => ['user:read']]);

        // Return a professional HTTP 201 Created response along with the resource data
        return new JsonResponse($jsonUser, Response::HTTP_CREATED, [], true);
    }

    #[Route('/users/{id}', name: 'app_user_detail', methods: ['GET'])]
    #[IsGranted('CAN_SEE_USER', subject: 'user')]
    public function getUserDetail(
        User $user,
        SerializerInterface $serializer
    ): JsonResponse {
        // Note: Symfony automatically throws a 404 Not Found if the {id} does not exist in the database.
        // Note: The #[IsGranted] attribute automatically triggers UserVoter and throws a 403 Forbidden if access is denied.

        // Serialize the single User object into JSON
        $jsonUser = $serializer->serialize($user, 'json', ['groups' => ['user:read']]);

        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }

    #[Route('/users/{id}', name: 'app_user_edit', methods: ['PUT', 'PATCH'])]
    #[IsGranted('CAN_EDIT_USER', subject: 'user')]
    public function editUser(
        User $user,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $em
    ): JsonResponse {
        // Note: Symfony automatically throws a 404 Not Found if the {id} does not exist in the database.
        // Note: The #[IsGranted] attribute automatically triggers UserVoter and throws a 403 Forbidden if access is denied.

        // Use deserialize with object_to_populate to update existing entity
        $jsonContent = $request->getContent();
        if (empty($jsonContent)) {
            return new JsonResponse(['message' => 'No data provided'], Response::HTTP_BAD_REQUEST);
        }

        // Use deserialize with object_to_populate to update the existing entity.
        /** @var User $user */
        $serializer->deserialize(
            $jsonContent,
            User::class,
            'json',
            ['object_to_populate' => $user]
        );

        // Validate the updated entity based on constraints defined in User.php
        $errors = $validator->validate($user);
        if ($errors->count() > 0) {
            $jsonErrors = $serializer->serialize($errors, 'json');
            return new JsonResponse($jsonErrors, Response::HTTP_BAD_REQUEST, [], true);
        }
        // Persist the changes to the database
        $em->persist($user);
        $em->flush();

        // Serialize the single User object into JSON
        $jsonUser = $serializer->serialize($user, 'json', ['groups' => ['user:read']]);

        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }

    #[Route('/users/{id}', name: 'app_user_delete', methods: ['DELETE'])]
    #[IsGranted('CAN_DELETE_USER', subject: 'user')]
    public function deleteUser(
        User $user,
        EntityManagerInterface $em
    ): JsonResponse {
        // Remove the User entity from the database
        $em->remove($user);

        // Execute the DELETE query in the database
        $em->flush();

        // Returns a clean HTTP 204 No Content response to confirm successful deletion
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
