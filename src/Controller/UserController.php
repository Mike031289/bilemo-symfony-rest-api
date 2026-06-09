<?php
// src/Controller/UserController.php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Client;
use App\Repository\UserRepository;
use App\Serializer\PaginatedCollectionNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/users')]
#[OA\Tag(name: 'Users')]
#[OA\Response(
    response: 401,
    description: 'Unauthorized - Invalid, missing or expired JWT Bearer token'
)]
final class UserController extends AbstractController
{
    /**
     * Fetch a paginated list of users linked to the authenticated B2B client with HTTP Caching.
     */
    #[Route('', name: 'app_user_list', methods: ['GET'])]
    #[OA\Get(
        path: '/users',
        summary: 'Retrieve tenant-scoped users list',
        description: 'Fetches user accounts structurally linked to the currently authenticated B2B client context.'
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'The page selection pointer',
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'The maximum collection records limit (Anti-DOS capped at 50)',
        schema: new OA\Schema(type: 'integer', default: 5)
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Returns the isolated client user-list collection envelope',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'meta', type: 'object', properties: [
                    new OA\Property(property: 'current_page', type: 'integer', example: 1),
                    new OA\Property(property: 'limit', type: 'integer', example: 5),
                    new OA\Property(property: 'total_items', type: 'integer', example: 12),
                    new OA\Property(property: 'total_pages', type: 'integer', example: 3),
                    new OA\Property(property: 'client', ref: new Model(type: Client::class, groups: ['client:read']))
                ]),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: User::class, groups: ['user:read']))
                ),
                new OA\Property(property: '_links', type: 'object', properties: [
                    new OA\Property(property: 'self', type: 'string', example: '/users?page=1&limit=5')
                ])
            ]
        )
    )]
    public function getUserList(
        Request $request,
        UserRepository $userRepository,
        SerializerInterface $serializer,
        PaginatedCollectionNormalizer $paginatedNormalizer
    ): Response {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 5);

        $limit = $limit > 50 ? 50 : $limit;
        $page = $page < 1 ? 1 : $page;
        $limit = $limit < 1 ? 5 : $limit;

        /** @var Client $currentClient */
        $currentClient = $this->getUser();

        $etag = md5('users_client_' . $currentClient->getId() . '_page_' . $page . '_limit_' . $limit);

        $response = new Response();
        $response->setEtag($etag);
        $response->setPublic();

        if ($response->isNotModified($request)) {
            return $response;
        }

        $paginatedData = $userRepository->findPaginatedUsersByClient($currentClient, $page, $limit);
        $users = $paginatedData['results'] ?? $paginatedData;

        $totalItems = $paginatedData['total'] ?? $userRepository->countByClient($currentClient);
        $totalPages = (int) ceil($totalItems / $limit);

        $serializedData = $serializer->serialize([
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
                'client' => $currentClient
            ],
            'data' => $users
        ], 'json', [
            'groups' => ['user:read', 'client:read']
        ]);

        $arrayData = json_decode($serializedData, true);
        $finalPayload = $paginatedNormalizer->normalize($arrayData, 'json');

        $response->setContent(json_encode($finalPayload));
        $response->headers->set('Content-Type', 'application/json');
        $response->setMaxAge(3600);

        return $response;
    }

    /**
     * Retrieve details of a single user with HTTP Caching.
     */
    #[Route('/{id}', name: 'app_user_detail', methods: ['GET'])]
    #[IsGranted('CAN_SEE_USER', subject: 'user')]
    #[OA\Get(
        path: '/users/{id}',
        summary: 'Retrieve single user profile record',
        description: 'Fetches detailed record metrics of an attached consumer user. Regulated by Client owner validation Voters.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'The entity database system record identifier targeting the consumer user record',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Returns the fully populated distinct context model mapping matching the detail group',
        content: new OA\JsonContent(ref: new Model(type: User::class, groups: ['user:detail']))
    )]
    #[OA\Response(response: 403, description: 'Forbidden - Resource access denied (ownership constraint failure rules via Voter check)')]
    #[OA\Response(response: 404, description: 'Not Found - Targeted consumer user identifier reference does not exist')]
    public function getUserDetail(User $user, Request $request, SerializerInterface $serializer): Response
    {
        $etag = md5('user_detail_' . $user->getId());

        $response = new Response();
        $response->setEtag($etag);
        $response->setPublic();

        if ($response->isNotModified($request)) {
            return $response;
        }

        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'user:detail']);

        $response->setContent($jsonUser);
        $response->headers->set('Content-Type', 'application/json');
        $response->setMaxAge(3600);

        return $response;
    }

    /**
     * Register and bind a new final user to the logged-in client.
     */
    #[Route('', name: 'app_user_create', methods: ['POST'])]
    #[OA\Post(
        path: '/users',
        summary: 'Register and bind a new consumer account record',
        description: 'Creates a new consumer profile record entry and automatically binds ownership references to the active authenticated B2B Client session.'
    )]
    #[OA\RequestBody(
        description: 'JSON structural initialization payload mapping to consumer configurations profiles schemas',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['firstName', 'lastName', 'email'],
            properties: [
                new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john.doe@example.com')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Created - User profile generated and assigned successfully',
        content: new OA\JsonContent(ref: new Model(type: User::class, groups: ['user:detail']))
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Validation framework entity constraint execution anomalies (e.g., duplicated email or missing fields)')]
    public function createUser(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        /** @var User $user */
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        $user->setClient($this->getUser());

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($user);
        $em->flush();

        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'user:detail']);
        return new JsonResponse($jsonUser, Response::HTTP_CREATED, [], true);
    }

    /**
     * Update an existing user record using partial or full payload hydration.
     */
    #[Route('/{id}', name: 'app_user_edit', methods: ['PUT', 'PATCH'])]
    #[IsGranted('CAN_EDIT_USER', subject: 'user')]
    #[OA\Put(
        path: '/users/{id}',
        summary: 'Hydrate or partially patch consumer context variables data structural elements',
        description: 'Updates active records properties attributes on targeted customer entities. Protected by explicit Tenant context owner validations.'
    )]
    #[OA\Patch(
        path: '/users/{id}',
        summary: 'Hydrate or partially patch consumer context variables data structural elements',
        description: 'Updates active records properties attributes on targeted customer entities. Protected by explicit Tenant context owner validations.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'The physical key database structure identifier targeting consumer configurations entities references',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        description: 'Flexible JSON dictionary containing the payload components targeted for structural update processing sequences',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'firstName', type: 'string', example: 'Johnny'),
                new OA\Property(property: 'lastName', type: 'string', example: 'Updated')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Targeted modifications structural adjustments compiled cleanly into active schema models',
        content: new OA\JsonContent(ref: new Model(type: User::class, groups: ['user:detail']))
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Mutation execution failed due to invalid schema properties or database unique fields collisions')]
    #[OA\Response(response: 403, description: 'Forbidden - Resource operation requested violates tenant scope security boundaries logic rules')]
    #[OA\Response(response: 404, description: 'Not Found - Resource payload resolution reference point execution empty')]
    public function editUser(
        User $user,
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $serializer->deserialize(
            $request->getContent(),
            User::class,
            'json',
            ['object_to_populate' => $user]
        );

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
        }

        $em->flush();

        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'user:detail']);
        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }

    /**
     * Remove a user record from the catalog.
     */
    #[Route('/{id}', name: 'app_user_delete', methods: ['DELETE'])]
    #[IsGranted('CAN_DELETE_USER', subject: 'user')]
    #[OA\Delete(
        path: '/users/{id}',
        summary: 'Purge individual consumer references from database data architecture indexes',
        description: 'Permanently removes customer records and structural boundaries linked properties. Guarded by entity ownership validation Voter controls.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'The target system record identifier scheduled for physical data array deletion',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'No Content - Execution complete. Target consumer system profile removed permanently')]
    #[OA\Response(response: 403, description: 'Forbidden - Destruction operation rejected by business rules boundary context verification checks')]
    #[OA\Response(response: 404, description: 'Not Found - Requested identification structural index map returns zero properties')]
    public function deleteUser(User $user, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($user);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}