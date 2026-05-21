<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ApiTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiGoogleLoginController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ApiTokenService $apiTokenService,
        #[Autowire('%env(GOOGLE_CLIENT_ID)%')]
        private string $googleClientId,
    ) {}

    #[Route('/auth/google', name: 'api_auth_google', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->apiError('Invalid JSON payload.', 400);
        }

        $idToken = trim((string) ($data['id_token'] ?? $data['idToken'] ?? ''));

        if ($idToken === '') {
            return $this->apiError('id_token is required in the JSON body.', 400);
        }

        try {
            $tokenInfo = $this->verifyGoogleIdToken($idToken);
        } catch (\InvalidArgumentException $exception) {
            return $this->apiError($exception->getMessage(), 401);
        }

        $googleId = (string) ($tokenInfo['sub'] ?? '');
        $email = strtolower(trim((string) ($tokenInfo['email'] ?? '')));
        $name = trim((string) ($tokenInfo['name'] ?? ''));

        if ($googleId === '' || $email === '') {
            return $this->apiError('Google account email is required.', 401);
        }

        if (($tokenInfo['email_verified'] ?? 'false') !== 'true') {
            return $this->apiError('Google account email is not verified.', 403);
        }

        try {
            $user = $this->resolveStaffUser($email, $name, $googleId);
        } catch (\RuntimeException $exception) {
            return $this->apiError($exception->getMessage(), 403);
        }

        $user->markSessionStarted();
        $user->setLastLogin(new \DateTimeImmutable());
        $this->entityManager->flush();

        $token = $this->apiTokenService->generate($user);

        return $this->apiSuccess('Google sign-in successful', [
            'token_type' => 'Bearer',
            'access_token' => $token,
            'expires_in' => 86400,
            'authProvider' => 'google',
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUserIdentifier(),
                'name' => $user->getName(),
                'roles' => $user->getRoles(),
                'isVerified' => $user->isVerified(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function verifyGoogleIdToken(string $idToken): array
    {
        $response = $this->httpClient->request(
            'GET',
            'https://oauth2.googleapis.com/tokeninfo',
            ['query' => ['id_token' => $idToken]]
        );

        if ($response->getStatusCode() !== 200) {
            throw new \InvalidArgumentException('Invalid Google ID token.');
        }

        /** @var array<string, mixed> $tokenInfo */
        $tokenInfo = $response->toArray(false);
        $audience = (string) ($tokenInfo['aud'] ?? '');

        if ($audience === '' || !$this->isAllowedGoogleAudience($audience)) {
            throw new \InvalidArgumentException('Google token audience is not allowed.');
        }

        return $tokenInfo;
    }

    private function isAllowedGoogleAudience(string $audience): bool
    {
        $allowed = array_filter([
            $this->googleClientId,
            $_ENV['GOOGLE_ANDROID_CLIENT_ID'] ?? null,
            $_ENV['GOOGLE_IOS_CLIENT_ID'] ?? null,
        ]);

        return in_array($audience, $allowed, true);
    }

    private function resolveStaffUser(string $email, string $name, string $googleId): User
    {
        $user = $this->userRepository->findOneBy(['username' => $email]);

        if (!$user instanceof User) {
            $user = new User();
            $user->setUsername($email);
            $user->setName($name !== '' ? $name : $email);
            $user->setRoles(['ROLE_STAFF']);
            $user->setGoogleId($googleId);
            $user->setAuthProvider('google');
            $user->setStatus('active');
            $user->setIsActive(true);
            $user->setIsVerified(true);
            $user->setCreatedAt(new \DateTimeImmutable());
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32)))
            );
            $user->setLastLogin(new \DateTimeImmutable());
            $this->entityManager->persist($user);

            return $user;
        }

        if (in_array('ROLE_ADMIN', $user->getAssignedRoles(), true)) {
            throw new \RuntimeException('Admin accounts cannot sign in with Google.');
        }

        if ($user->isCustomerOnly()) {
            throw new \RuntimeException(
                'Google sign-in is only available for staff accounts. Please use your email and password.'
            );
        }

        if (!$user->isVerified()) {
            $user->setIsVerified(true);
        }

        if (!$user->getGoogleId() && $googleId !== '') {
            $user->setGoogleId($googleId);
        }

        if ($user->getAuthProvider() !== 'google') {
            $user->setAuthProvider('google');
        }

        if (!$user->getName() && $name !== '') {
            $user->setName($name);
        }

        return $user;
    }
}
