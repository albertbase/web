<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ProfileEditFormType;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'customer_profile')]
    public function customerProfile(): Response
    {
        if ($this->isGranted('ROLE_STAFF')) {
            return $this->redirectToRoute('staff_profile');
        }

        return $this->render('profile/customer_profile.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/admin/profile', name: 'staff_profile')]
    #[IsGranted('ROLE_STAFF')]
    public function staffProfile(): Response
    {
        return $this->render('profile/staff_profile.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/change-password', name: 'customer_change_password', methods: ['GET', 'POST'])]
    public function customerChangePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        ActivityLogger $logger
    ): Response {
        if ($this->isGranted('ROLE_STAFF')) {
            return $this->redirectToRoute('staff_change_password');
        }

        return $this->handleChangePassword(
            $request,
            $passwordHasher,
            $em,
            $logger,
            'profile/customer_change_password.html.twig',
            'customer_profile'
        );
    }

    #[Route('/admin/change-password', name: 'staff_change_password', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function staffChangePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        ActivityLogger $logger
    ): Response {
        return $this->handleChangePassword(
            $request,
            $passwordHasher,
            $em,
            $logger,
            'profile/staff_change_password.html.twig',
            'staff_profile'
        );
    }

    #[Route('/profile/edit', name: 'customer_profile_edit', methods: ['GET', 'POST'])]
    public function customerEditProfile(
        Request $request,
        EntityManagerInterface $em,
        ActivityLogger $logger
    ): Response {
        if ($this->isGranted('ROLE_STAFF')) {
            return $this->redirectToRoute('staff_profile_edit');
        }

        return $this->handleEditProfile(
            $request,
            $em,
            $logger,
            'profile/customer_edit_profile.html.twig',
            'customer_profile'
        );
    }

    #[Route('/admin/profile/edit', name: 'staff_profile_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function staffEditProfile(
        Request $request,
        EntityManagerInterface $em,
        ActivityLogger $logger
    ): Response {
        return $this->handleEditProfile(
            $request,
            $em,
            $logger,
            'profile/staff_edit_profile.html.twig',
            'staff_profile'
        );
    }

    private function handleChangePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        ActivityLogger $logger,
        string $template,
        string $profileRoute
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('Authenticated user is not an instance of App\Entity\User');
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword = $form->get('newPassword')->getData();

            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $form->get('currentPassword')->addError(
                    new FormError('Current password is incorrect.')
                );

                return $this->render($template, [
                    'form' => $form->createView(),
                ]);
            }

            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $em->flush();

            $logger->log(
                $user,
                'update',
                'User',
                $user->getId(),
                'User changed own password'
            );

            $this->addFlash('success', 'Password changed successfully.');

            return $this->redirectToRoute($profileRoute);
        }

        return $this->render($template, [
            'form' => $form->createView(),
        ]);
    }

    private function handleEditProfile(
        Request $request,
        EntityManagerInterface $em,
        ActivityLogger $logger,
        string $template,
        string $profileRoute
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('Authenticated user is not an instance of App\Entity\User');
        }

        $form = $this->createForm(ProfileEditFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $logger->log(
                $user,
                'update',
                'User',
                $user->getId(),
                'User updated own profile information'
            );

            $this->addFlash('success', 'Profile updated successfully.');

            return $this->redirectToRoute($profileRoute);
        }

        return $this->render($template, [
            'form' => $form->createView(),
        ]);
    }
}
