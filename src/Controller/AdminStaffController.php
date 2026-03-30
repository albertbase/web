<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Service\ActivityLogger;
use Symfony\Component\Security\Http\Attribute\IsGranted;


// #[IsGranted('ROLE_ADMIN')]
class AdminStaffController extends AbstractController
{
    #[Route('/admin/staff', name: 'admin_staff_index')]
    public function index(UserRepository $repo)
    {
        return $this->render('admin_staff/index.html.twig', [
            // 'staff' => $repo->findBy(['status' => ['active', 'disabled']]),
            'staff' => $repo->findAll(),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/staff/new', name: 'admin_staff_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        ActivityLogger $logger
    ) {
        $user = new User();
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setIsActive(false);

        $form = $this->createForm(UserType::class, $user, [
            'is_staff_form' => true
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ✅ Sync isActive with status on creation
            if ($user->getStatus() === 'disabled') {
                $user->setIsActive(false);
            } else {
                $user->setIsActive(true);
            }


            // ✅ Hash password only if provided
            // if ($user->getPlainPassword()) {
            //     $hashed = $passwordHasher->hashPassword($user, $user->getPlainPassword());
            //     $user->setPassword($hashed);
            // }
            $plainPassword = $form->get('plainPassword')->getData();

            

            if ($plainPassword) {
                $hashed = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashed);
            }
            

            $em->persist($user);
            $em->flush();

            $logger->log(
            user: $this->getUser(),
            action: 'create',
            entityType: 'User',
            entityId: $user->getId(),
            details: 'Created staff account: ' . $user->getUsername()
        );

            

            return $this->redirectToRoute('admin_staff_index');
        }

        return $this->render('admin_staff/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }


    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/staff/{id}/edit', name: 'admin_staff_edit')]
    public function edit(User $user, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher, ActivityLogger $logger)
    {
        $form = $this->createForm(UserType::class, $user, [
            'is_staff_form' => true
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {


             // ✅ Sync isActive with status
            if ($user->getStatus() === 'disabled') {
                $user->setIsActive(false);
            } else {
                $user->setIsActive(true);
            }

            if ($user->getPlainPassword()) {
                $hashed = $passwordHasher->hashPassword($user, $user->getPlainPassword());
                $user->setPassword($hashed);
            }

           


            $em->persist($user);
            $em->flush();

            $logger->log(
        user: $this->getUser(),
        action: 'edit',
        entityType: 'User',
        entityId: $user->getId(),
        details: 'Updated staff account: ' . $user->getUsername()
    );

            return $this->redirectToRoute('admin_staff_index');
        }


        // if ($form->isSubmitted() && $form->isValid()) {
        //     $em->flush();
        //     return $this->redirectToRoute('admin_staff_index');
        // }

        return $this->render('admin_staff/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/staff/{id}/reset-password', name: 'admin_staff_reset_password')]
    public function resetPassword(User $user, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em)
    {
        $newPassword = 'staff123'; // or generate random
        $hashed = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashed);

        $em->flush();

        $this->addFlash('success', 'Password has been reset to: ' . $newPassword);

        return $this->redirectToRoute('admin_staff_index');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/staff/{id}/toggle-status', name: 'admin_staff_toggle_status')]
    public function toggleStatus(User $user, EntityManagerInterface $em)
    {
        // Toggle logic
        if ($user->getStatus() === 'active') {
            $user->setStatus('disabled');
        } else {
            $user->setStatus('active');
        }

        $em->flush();

        $this->addFlash('success', 'Account status updated.');

        return $this->redirectToRoute('admin_staff_index');
    }
    #[IsGranted('ROLE_ADMIN')]
#[Route('/admin/staff/{id}/delete', name: 'admin_staff_delete', methods: ['POST'])]
public function delete(User $user, EntityManagerInterface $em, ActivityLogger $logger)
{
    // 🔥 Soft delete instead of hard delete
    // $user->setStatus('archived');
    // $user->setIsActive(false);

    

    // 🔥 Log archive action
    $logger->log(
        user: $this->getUser(),
        action: 'delete',
        entityType: 'User',
        entityId: $user->getId(),
        details: 'Archived staff account: ' . $user->getUsername()
    );

    $em->remove($user);
    $em->flush();
    $this->addFlash('success', 'Staff account deleted successfully.');

    return $this->redirectToRoute('admin_staff_index');
}



}