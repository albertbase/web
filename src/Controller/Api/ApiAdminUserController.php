<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\PasswordPolicy;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/users', name: 'api_admin_users_')]
#[IsGranted('ROLE_ADMIN')]
class ApiAdminUserController extends AbstractController
{
    use ApiResponseTrait;

    private const ALLOWED_ROLES = ['ROLE_USER', 'ROLE_STAFF', 'ROLE_ADMIN'];

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, UserRepository $userRepository): JsonResponse
    {
        $search = trim((string) $request->query->get('search', ''));
        $role = trim((string) $request->query->get('role', ''));
        $status = trim((string) $request->query->get('status', ''));
        $limit = max(1, min(100, (int) $request->query->get('limit', 50)));
        $offset = max(0, (int) $request->query->get('offset', 0));

        $users = array_map(
            fn (User $user): array => $this->mapUser($user),
            $userRepository->findForAdminList(
                $search !== '' ? $search : null,
                $role !== '' ? $role : null,
                $status !== '' ? $status : null,
                $limit,
                $offset
            )
        );

        return $this->apiSuccess('Users retrieved successfully.', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $role,
                'status' => $status,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(int $id, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($id);
        if (!$user instanceof User) {
            return $this->apiError('User not found.', 404);
        }

        return $this->apiSuccess('User retrieved successfully.', [
            'user' => $this->mapUser($user),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->apiError('Invalid JSON payload.', 400);
        }

        $username = trim((string) ($data['username'] ?? ''));
        $plainPassword = (string) ($data['password'] ?? '');

        if ($username === '' || $plainPassword === '') {
            return $this->apiError('username and password are required.', 400);
        }

        if (!$this->isStrongPassword($plainPassword)) {
            return $this->apiError(
                PasswordPolicy::MESSAGE,
                400
            );
        }

        if ($userRepository->findOneByNormalizedUsername($username) instanceof User) {
            return $this->apiError('An account with this username already exists.', 409);
        }

        $status = strtolower(trim((string) ($data['status'] ?? User::STATUS_ACTIVE)));
        if (!in_array($status, [User::STATUS_ACTIVE, User::STATUS_DISABLED], true)) {
            return $this->apiError('status must be "active" or "disabled".', 400);
        }

        $user = new User();
        $user->setUsername($username);
        $user->setName(trim((string) ($data['name'] ?? '')) ?: $username);
        $user->setRoles($this->normalizeRoles($data['roles'] ?? ['ROLE_USER']));
        $user->setStatus($status);
        $user->setIsVerified((bool) ($data['isVerified'] ?? false));
        $user->setAuthProvider('local');
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

        $violations = $validator->validate($user);
        if (count($violations) > 0) {
            return $this->apiError('Validation failed.', 422, [
                'errors' => $this->validationErrors($violations),
            ]);
        }

        try {
            $entityManager->persist($user);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->apiError('A unique constraint was violated while creating the user.', 409);
        }

        return $this->apiSuccess('User created successfully.', [
            'user' => $this->mapUser($user),
        ], 201);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(
        int $id,
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $user = $userRepository->find($id);
        if (!$user instanceof User) {
            return $this->apiError('User not found.', 404);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->apiError('Invalid JSON payload.', 400);
        }

        if (array_key_exists('username', $data)) {
            $username = trim((string) $data['username']);
            if ($username === '') {
                return $this->apiError('username cannot be empty.', 400);
            }

            $existing = $userRepository->findOneByNormalizedUsername($username);
            if ($existing instanceof User && $existing->getId() !== $user->getId()) {
                return $this->apiError('An account with this username already exists.', 409);
            }

            $user->setUsername($username);
        }

        if (array_key_exists('name', $data)) {
            $user->setName(trim((string) $data['name']) ?: null);
        }

        if (array_key_exists('roles', $data)) {
            $user->setRoles($this->normalizeRoles($data['roles']));
        }

        if (array_key_exists('status', $data)) {
            $status = strtolower(trim((string) $data['status']));
            if (!in_array($status, [User::STATUS_ACTIVE, User::STATUS_DISABLED], true)) {
                return $this->apiError('status must be "active" or "disabled".', 400);
            }
            $user->setStatus($status);
        }

        if (array_key_exists('isVerified', $data)) {
            $user->setIsVerified((bool) $data['isVerified']);
        }

        if (array_key_exists('password', $data)) {
            $plainPassword = (string) $data['password'];
            if ($plainPassword !== '') {
                if (!$this->isStrongPassword($plainPassword)) {
                    return $this->apiError(
                        PasswordPolicy::MESSAGE,
                        400
                    );
                }
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }
        }

        $violations = $validator->validate($user);
        if (count($violations) > 0) {
            return $this->apiError('Validation failed.', 422, [
                'errors' => $this->validationErrors($violations),
            ]);
        }

        try {
            $entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->apiError('A unique constraint was violated while updating the user.', 409);
        }

        return $this->apiSuccess('User updated successfully.', [
            'user' => $this->mapUser($user),
        ]);
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $userRepository->find($id);
        if (!$user instanceof User) {
            return $this->apiError('User not found.', 404);
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            return $this->apiError('You cannot delete your own account.', 409);
        }

        $entityManager->remove($user);
        $entityManager->flush();

        return $this->apiSuccess('User deleted successfully.');
    }

    private function normalizeRoles(mixed $rolesPayload): array
    {
        if (!is_array($rolesPayload)) {
            $rolesPayload = [(string) $rolesPayload];
        }

        $normalized = [];
        foreach ($rolesPayload as $role) {
            $role = strtoupper(trim((string) $role));
            if ($role !== '' && in_array($role, self::ALLOWED_ROLES, true)) {
                $normalized[] = $role;
            }
        }

        if ($normalized === []) {
            $normalized[] = 'ROLE_USER';
        }

        return array_values(array_unique($normalized));
    }

    private function isStrongPassword(string $password): bool
    {
        return PasswordPolicy::isValid($password);
    }

    private function mapUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUserIdentifier(),
            'name' => $user->getName(),
            'roles' => $user->getRoles(),
            'status' => $user->getStatus(),
            'isActive' => $user->isActive(),
            'isVerified' => $user->isVerified(),
            'authProvider' => $user->getAuthProvider(),
            'lastLogin' => $user->getLastLogin()?->format(DATE_ATOM),
            'createdAt' => $user->getCreatedAt()?->format(DATE_ATOM),
        ];
    }

    private function validationErrors(iterable $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $field = $violation->getPropertyPath() ?: 'general';
            $errors[$field][] = $violation->getMessage();
        }

        return $errors;
    }
}
