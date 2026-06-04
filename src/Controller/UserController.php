<?php
// src/Controller/UserController.php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Client;
use App\Repository\UserRepository;
use App\Serializer\PaginatedCollectionNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/users')]
final class UserController extends AbstractController
{
    /**
     * Fetch a paginated list of users linked to the authenticated B2B client.
     */
    #[Route('', name: 'app_user_list', methods: ['GET'])]
    public function getUserList(
        Request $request,
        UserRepository $userRepository,
        NormalizerInterface $normalizer,
        SerializerInterface $serializer,
        PaginatedCollectionNormalizer $paginatedNormalizer
    ): JsonResponse {
        // Extract pagination query parameters with default fallbacks
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 5);

        // SECURITY ANTI-DOS: Prevent client from requesting an abusive limit amount
        if ($limit > 50) {
            $limit = 50;
        }
        // Force positive integers for page and limit
        $page = $page < 1 ? 1 : $page;
        $limit = $limit < 1 ? 5 : $limit;

        // Retrieve the currently logged-in B2B Client entity instance
        /** @var Client $currentClient */
        $currentClient = $this->getUser();

        // Fetch custom paginated data set from repository layer
        $paginatedData = $userRepository->findPaginatedUsersByClient($currentClient, $page, $limit);

        // 1. Extract users safely (handles both structured array and flat array)
        $users = $paginatedData['results'] ?? $paginatedData;

        // 2. Compute total record count safely by falling back to a dedicated query if missing
        $totalItems = $paginatedData['total'] ?? $userRepository->countByClient($currentClient);
        $totalPages = (int) ceil($totalItems / $limit);

        // 3. Manually normalize each User entity to avoid circular reference loops.
        // This explicitly triggers your UserNormalizer, appending self/update/delete links to every item.
        $normalizedUsers = [];
        foreach ($users as $user) {
            $normalizedUsers[] = $normalizer->normalize($user, 'json', ['groups' => 'user:read']);
        }

        // 4. Normalize the authenticated client details using a safe normalization group.
        // Make sure to configure 'client:read' annotations in your Client entity to prevent leaks.
        $normalizedClient = $normalizer->normalize($currentClient, 'json', ['groups' => 'client:read']);

        // 5. Wrap the dataset inside our standardized pagination layout envelope, adding the client context
        $responseData = [
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
                'client' => $normalizedClient
            ],
            'data' => $normalizedUsers
        ];

        // 6. Inject root-level collection navigation hypermedia links (_links: first, last, next, prev)
        $finalPayload = $paginatedNormalizer->normalize($responseData, 'json');

        // 7. Transform the final complete data structure into raw JSON string safely
        $jsonResponse = $serializer->serialize($finalPayload, 'json');

        return new JsonResponse($jsonResponse, Response::HTTP_OK, [], true);
    }

    /**
     * Retrieve details of a single user.
     */
    #[Route('/{id}', name: 'app_user_detail', methods: ['GET'])]
    #[IsGranted('CAN_SEE_USER', subject: 'user')]
    public function getUserDetail(User $user, SerializerInterface $serializer): JsonResponse
    {
        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'user:read']);
        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }

    /**
     * Register and bind a new final user to the logged-in client.
     */
    #[Route('', name: 'app_user_create', methods: ['POST'])]
    public function createUser(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        /** @var User $user */
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        // Auto-assign the authenticated B2B Client as the owner of this user record
        $user->setClient($this->getUser());

        // Validate entity constraints
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($user);
        $em->flush();

        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'user:read']);
        return new JsonResponse($jsonUser, Response::HTTP_CREATED, [], true);
    }

    /**
     * Update an existing user record using partial or full payload hydration.
     */
    #[Route('/{id}', name: 'app_user_edit', methods: ['PUT', 'PATCH'])]
    #[IsGranted('CAN_EDIT_USER', subject: 'user')]
    public function editUser(
        User $user,
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        // Hydrate the existing user entity object using object_to_populate context configuration
        $serializer->deserialize(
            $request->getContent(),
            User::class,
            'json',
            ['object_to_populate' => $user]
        );

        // Enforce entity rules validation post-hydration
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
        }

        $em->flush();

        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'user:read']);
        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }

    /**
     * Remove a user record from the catalog.
     */
    #[Route('/{id}', name: 'app_user_delete', methods: ['DELETE'])]
    #[IsGranted('CAN_DELETE_USER', subject: 'user')]
    public function deleteUser(User $user, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($user);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}