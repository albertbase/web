<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\PasswordPolicy;
use App\Service\RegistrationVerificationService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ApiRegistrationController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        RegistrationVerificationService $registrationVerificationService,
        UserRepository $userRepository
    ): JsonResponse {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->apiError('Invalid JSON payload.', 400);
        }

        $username = trim((string) ($data['username'] ?? ''));
        $plainPassword = (string) ($data['password'] ?? '');
        $name = trim((string) ($data['name'] ?? ''));

        if ($username === '' || $plainPassword === '') {
            return $this->apiError('username and password are required.', 400);
        }

        if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
            return $this->apiError('username must be a valid email address.', 400);
        }

        if (!PasswordPolicy::isValid($plainPassword)) {
            return $this->apiError(
                PasswordPolicy::MESSAGE,
                400
            );
        }

        $normalizedUsername = strtolower($username);
        $existing = $userRepository->findOneByNormalizedUsername($normalizedUsername);

        if ($existing instanceof User && $existing->isVerified()) {
            return $this->apiError('An account with this email already exists.', 409);
        }

        if ($existing instanceof User && !$existing->isVerified()) {
            $emailSent = $registrationVerificationService->resendVerificationEmail($existing);

            return $this->apiSuccess(
                $emailSent
                    ? 'Account already exists but is not verified. A new verification email was sent.'
                    : 'Account already exists but is not verified. Verification email could not be sent right now.',
                [
                    'isVerified' => false,
                    'verificationEmailSent' => $emailSent,
                ]
            );
        }

        $user = new User();
        $user->setUsername($normalizedUsername);
        $user->setName($name !== '' ? $name : $normalizedUsername);

        try {
            $emailSent = $registrationVerificationService->register($user, $plainPassword);
        } catch (UniqueConstraintViolationException) {
            $existing = $userRepository->findOneByNormalizedUsername($normalizedUsername);

            if ($existing instanceof User && !$existing->isVerified()) {
                $emailSent = $registrationVerificationService->resendVerificationEmail($existing);

                return $this->apiSuccess(
                    $emailSent
                        ? 'Account already exists but is not verified. A new verification email was sent.'
                        : 'Account already exists but is not verified. Verification email could not be sent right now.',
                    [
                        'isVerified' => false,
                        'verificationEmailSent' => $emailSent,
                    ]
                );
            }

            return $this->apiError('An account with this email already exists.', 409);
        }

        return $this->apiSuccess(
            $emailSent
                ? 'Registration complete. Please check your email to verify your account.'
                : 'Registration complete, but verification email could not be sent right now.',
            [
                'isVerified' => $user->isVerified(),
                'verificationEmailSent' => $emailSent,
            ],
            201
        );
    }
}
