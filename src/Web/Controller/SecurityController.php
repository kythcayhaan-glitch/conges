<?php

declare(strict_types=1);

namespace App\Web\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SecurityController extends AbstractController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface      $em,
    ) {
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error'         => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('This method is intercepted by the firewall.');
    }

    #[Route('/account/password', name: 'app_account_password', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_AGENT')]
    public function changePassword(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->render('security/change_password.html.twig');
        }

        if (!$this->isCsrfTokenValid('change_password', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_account_password');
        }

        $user            = $this->getUser();
        $currentPassword = (string) $request->request->get('current_password', '');
        $newPassword     = (string) $request->request->get('new_password', '');
        $confirmation    = (string) $request->request->get('confirmation', '');

        if (!$this->hasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
            return $this->redirectToRoute('app_account_password');
        }

        if (strlen($newPassword) < 8) {
            $this->addFlash('error', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
            return $this->redirectToRoute('app_account_password');
        }

        if ($newPassword !== $confirmation) {
            $this->addFlash('error', 'La confirmation ne correspond pas au nouveau mot de passe.');
            return $this->redirectToRoute('app_account_password');
        }

        $user->setPassword($this->hasher->hashPassword($user, $newPassword));
        $this->em->flush();

        $this->addFlash('success', 'Mot de passe modifié avec succès.');
        return $this->redirectToRoute('app_account_password');
    }
}
