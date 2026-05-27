<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\RegistrationVerificationService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        RegistrationVerificationService $registrationVerificationService,
        UserRepository $userRepository
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $submittedUsername = strtolower(trim((string) $form->get('email')->getData()));
            if ($submittedUsername !== '') {
                $existing = $userRepository->findOneByNormalizedUsername($submittedUsername);
                if ($existing instanceof User && !$existing->isVerified()) {
                    $registrationVerificationService->verifyExistingUnverifiedUser($existing);
                    $this->addFlash('success', 'Your account has been verified. You can now sign in.');

                    return $this->redirectToRoute('app_login');
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setUsername(strtolower(trim((string) $user->getUserIdentifier())));

            try {
                $registrationVerificationService->register($user, $plainPassword);
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('danger', 'An account with this email already exists.');
                return $this->redirectToRoute('app_login');
            }

            $this->addFlash('success', 'Registration complete. You can now sign in.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
