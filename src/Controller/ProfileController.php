<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileEditFormType;
use App\Form\ChangePasswordFormType;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'user_profile')]
    public function profile(): Response
    {
        return $this->render('profile/profile.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/change-password', name: 'user_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        ActivityLogger $logger
    ): Response {
        $userInterface = $this->getUser();

        if (!$userInterface instanceof User) {
            throw new \LogicException('Authenticated user is not an instance of App\Entity\User');
        }

        /** @var User $user */
        $user = $userInterface;

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword = $form->get('newPassword')->getData();

            // ✅ Validate current password
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Current password is incorrect.');
                return $this->redirectToRoute('user_change_password');
            }

            // ✅ Hash and save new password
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);

            $em->flush();

            // ✅ Log password change
            $logger->log(
                $user,
                'update',
                'User',
                $user->getId(),
                'User changed own password'
            );

            $this->addFlash('success', 'Password changed successfully.');
            return $this->redirectToRoute('user_profile');
        }

        return $this->render('profile/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/profile/edit', name: 'user_profile_edit', methods: ['GET', 'POST'])]
public function editProfile(
    Request $request,
    EntityManagerInterface $em,
    ActivityLogger $logger
): Response {
    $user = $this->getUser();

    if (!$user instanceof User) {
        throw new \LogicException('Authenticated user is not an instance of App\Entity\User');
    }

    $form = $this->createForm(ProfileEditFormType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {

        $em->flush();

        // Log the update
        $logger->log(
            $user,
            'update',
            'User',
            $user->getId(),
            'User updated own profile information'
        );

        $this->addFlash('success', 'Profile updated successfully.');
        return $this->redirectToRoute('user_profile');
    }

    return $this->render('profile/edit_profile.html.twig', [
        'form' => $form->createView(),
    ]);
}

}
