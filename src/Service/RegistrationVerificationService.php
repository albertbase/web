<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationVerificationService
{
    private const VERIFICATION_TOKEN_TTL_HOURS = 24;

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(string:MAILER_FROM_ADDRESS)%')]
        private string $fromAddress,
        #[Autowire('%env(string:MAILER_FROM_NAME)%')]
        private string $fromName,
    ) {}

    /**
     * Registers or persists the user as already verified.
     *
     * @throws UniqueConstraintViolationException
     */
    public function register(User $user, string $plainPassword): void
    {
        $user->setUsername(strtolower(trim((string) $user->getUserIdentifier())));
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        if ($user->getRoles() === []) {
            $user->setRoles(['ROLE_USER']);
        }
        $user->setIsVerified(true);
        $user->setEmailVerificationToken(null);
        $user->setEmailVerificationRequestedAt(null);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setStatus(User::STATUS_ACTIVE);
        $user->setIsActive(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function verifyExistingUnverifiedUser(User $user): void
    {
        if ($user->isVerified()) {
            return;
        }

        $user->setIsVerified(true);
        $user->setEmailVerificationToken(null);
        $user->setEmailVerificationRequestedAt(null);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * Refreshes verification window for an existing unverified account and attempts to send an email.
     * Returns true when email was sent successfully, false otherwise.
     */
    public function resendVerificationEmail(User $user): bool
    {
        if ($user->isVerified()) {
            return true;
        }

        if ($user->getEmailVerificationToken() === null || $user->getEmailVerificationToken() === '') {
            $user->setEmailVerificationToken(bin2hex(random_bytes(32)));
        }

        $user->setEmailVerificationRequestedAt(new \DateTimeImmutable());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->sendVerificationEmailSafely($user);
    }

    public function isVerificationTokenExpired(User $user): bool
    {
        $requestedAt = $user->getEmailVerificationRequestedAt();
        if (!$requestedAt instanceof \DateTimeImmutable) {
            return true;
        }

        $expiresAt = $requestedAt->modify('+'.self::VERIFICATION_TOKEN_TTL_HOURS.' hours');
        return $expiresAt <= new \DateTimeImmutable();
    }

    private function initializeVerificationToken(User $user): void
    {
        $user->setEmailVerificationToken(bin2hex(random_bytes(32)));
        $user->setEmailVerificationRequestedAt(new \DateTimeImmutable());
    }

    private function sendVerificationEmailSafely(User $user): bool
    {
        try {
            $this->sendVerificationEmail($user);
            return true;
        } catch (TransportExceptionInterface) {
            return false;
        }
    }

    private function sendVerificationEmail(User $user): void
    {
        $verificationUrl = $this->urlGenerator->generate('app_verify_email', [
            'token' => $user->getEmailVerificationToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->mailer->send(
            (new TemplatedEmail())
                ->from(sprintf('%s <%s>', $this->fromName, $this->fromAddress))
                ->to($user->getUsername())
                ->subject('Verify your Sweetoria account')
                ->replyTo(sprintf('%s <%s>', $this->fromName, $this->fromAddress))
                ->htmlTemplate('emails/registration_verification.html.twig')
                ->context([
                    'user' => $user,
                    'verificationUrl' => $verificationUrl,
                ])
        );
    }
}
