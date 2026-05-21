<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        MailerInterface $mailer,
        #[Autowire('%env(CONTACT_RECIPIENT_EMAIL)%')] string $recipientEmail
    ): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('contact_form', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Your message could not be sent. Please try again.');

                return $this->redirectToRoute('app_contact');
            }

            $name = trim((string) $request->request->get('name', ''));
            $email = trim((string) $request->request->get('email', ''));
            $subject = trim((string) $request->request->get('subject', ''));
            $message = trim((string) $request->request->get('message', ''));

            if ($name === '' || $email === '' || $subject === '' || $message === '') {
                $this->addFlash('danger', 'Please complete all fields before sending.');

                return $this->redirectToRoute('app_contact');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('danger', 'Please enter a valid email address.');

                return $this->redirectToRoute('app_contact');
            }

            $mail = (new Email())
                ->from('Sweetoria <albertbase09675695595@gmail.com>')
                ->to($recipientEmail)
                ->replyTo($email)
                ->subject('[Sweetoria Contact] ' . $subject)
                ->text(
                    "Name: {$name}\n".
                    "Email: {$email}\n".
                    "Subject: {$subject}\n\n".
                    "Message:\n{$message}\n"
                )
                ->html(sprintf(
                    '<p><strong>Name:</strong> %s</p><p><strong>Email:</strong> %s</p><p><strong>Subject:</strong> %s</p><p><strong>Message:</strong></p><p>%s</p>',
                    nl2br(htmlspecialchars($name, ENT_QUOTES, 'UTF-8')),
                    nl2br(htmlspecialchars($email, ENT_QUOTES, 'UTF-8')),
                    nl2br(htmlspecialchars($subject, ENT_QUOTES, 'UTF-8')),
                    nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'))
                ));

            try {
                $mailer->send($mail);

                $this->addFlash('success', 'Thanks! Your message was sent to our inbox.');

                return $this->redirectToRoute('app_contact', ['success' => 1]);
            } catch (TransportExceptionInterface $e) {
                $this->addFlash('danger', 'We could not send your message right now. Please try again later.');

                return $this->redirectToRoute('app_contact');
            }
        }

        return $this->render('contact/index.html.twig', [
            'submitted' => $request->query->getBoolean('success'),
        ]);
    }
}
