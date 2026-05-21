<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\RegistrationVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class VerificationController extends AbstractController
{
    #[Route('/verify/email', name: 'app_verify_email')]
    public function verify(
        Request $request,
        EntityManagerInterface $entityManager,
        RegistrationVerificationService $registrationVerificationService
    ): RedirectResponse
    {
        $token = (string) $request->query->get('token', '');

        if ($token === '') {
            $this->addFlash('danger', 'Verification token is missing.');
            return $this->redirectToRoute('app_login');
        }

        $user = $entityManager->getRepository(User::class)->findOneBy([
            'emailVerificationToken' => $token,
        ]);

        if (!$user) {
            $this->addFlash('danger', 'Verification link is invalid or has expired.');
            return $this->redirectToRoute('app_login');
        }

        if ($registrationVerificationService->isVerificationTokenExpired($user)) {
            $emailSent = $registrationVerificationService->resendVerificationEmail($user);

            $this->addFlash(
                $emailSent ? 'warning' : 'danger',
                $emailSent
                    ? 'Verification link expired. A new verification email was sent.'
                    : 'Verification link expired and a new email could not be sent right now.'
            );

            return $this->redirectToRoute('app_login');
        }

        $user->setIsVerified(true);
        $user->setEmailVerificationToken(null);
        $user->setEmailVerificationRequestedAt(null);
        $entityManager->flush();

        $this->addFlash('success', 'Your account has been verified. You can now sign in.');

        return $this->redirectToRoute('app_login');
    }
}
