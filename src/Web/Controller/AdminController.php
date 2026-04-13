<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Security\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/admin', name: 'app_admin_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    #[Route('/users', name: 'users', methods: ['GET'])]
    public function users(): Response
    {
        $users = $this->em->getRepository(User::class)->findBy([], ['username' => 'ASC']);

        return $this->render('admin/users.html.twig', ['users' => $users]);
    }

    #[Route('/users/new', name: 'users_new', methods: ['GET', 'POST'])]
    public function createUser(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_user_new', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_admin_users_new');
            }

            $username = trim((string) $request->request->get('username', ''));
            $email    = trim((string) $request->request->get('email', ''));
            $password = (string) $request->request->get('password', '');
            $nom      = trim((string) $request->request->get('nom', ''));
            $prenom   = trim((string) $request->request->get('prenom', ''));
            $balance  = (float) $request->request->get('leaveBalanceHours', 0.0);

            $error = $this->validateNewUser($username, $email, $password);
            if ($error) {
                $this->addFlash('error', $error);
                return $this->render('admin/user_new.html.twig', ['form_data' => $request->request->all()]);
            }

            $roles = $this->resolveRoles($request->request->all('roles') ?: []);

            $user = new User(
                id:               Uuid::v4()->toRfc4122(),
                email:            $email,
                roles:            $roles,
                username:         $username,
                nom:              $nom !== '' ? $nom : null,
                prenom:           $prenom !== '' ? $prenom : null,
                leaveBalanceValue: $balance,
            );
            $user->setPassword($this->hasher->hashPassword($user, $password));

            $this->em->persist($user);
            $this->em->flush();

            $this->addFlash('success', sprintf('Utilisateur "%s" créé avec succès.', $user->getUsername()));
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/user_new.html.twig', ['form_data' => []]);
    }

    #[Route('/users/{id}/edit', name: 'users_edit', methods: ['GET', 'POST'])]
    public function editUser(string $id, Request $request): Response
    {
        $user = $this->em->getRepository(User::class)->find($id);

        if ($user === null) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $isSelf = $user->getUserIdentifier() === $this->getUser()?->getUserIdentifier();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_user_edit_' . $id, $request->request->get('_token'))) {
                $this->addFlash('error', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_admin_users_edit', ['id' => $id]);
            }

            $username = trim((string) $request->request->get('username', ''));
            $email    = trim((string) $request->request->get('email', ''));
            $password = (string) $request->request->get('password', '');

            if ($username === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
                $this->addFlash('error', 'Nom d\'utilisateur invalide.');
                return $this->render('admin/user_edit.html.twig', ['user' => $user, 'is_self' => $isSelf]);
            }
            $existing = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
            if ($existing && $existing->getId() !== $user->getId()) {
                $this->addFlash('error', 'Ce nom d\'utilisateur est déjà utilisé.');
                return $this->render('admin/user_edit.html.twig', ['user' => $user, 'is_self' => $isSelf]);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Adresse e-mail invalide.');
                return $this->render('admin/user_edit.html.twig', ['user' => $user, 'is_self' => $isSelf]);
            }
            $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existing && $existing->getId() !== $user->getId()) {
                $this->addFlash('error', 'Cette adresse e-mail est déjà utilisée.');
                return $this->render('admin/user_edit.html.twig', ['user' => $user, 'is_self' => $isSelf]);
            }
            if ($password !== '' && strlen($password) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');
                return $this->render('admin/user_edit.html.twig', ['user' => $user, 'is_self' => $isSelf]);
            }

            $user->setUsername($username);
            $user->setEmail($email);

            if ($password !== '') {
                $user->setPassword($this->hasher->hashPassword($user, $password));
            }
            if (!$isSelf) {
                $user->setRoles($this->resolveRoles($request->request->all('roles') ?: []));
            }

            $nom     = trim((string) $request->request->get('nom', ''));
            $prenom  = trim((string) $request->request->get('prenom', ''));
            $balance = (float) $request->request->get('leaveBalanceHours', $user->getLeaveBalance()->getValue());

            $user->setNom($nom !== '' ? $nom : null);
            $user->setPrenom($prenom !== '' ? $prenom : null);
            $user->applyLeaveBalance(new \App\Shared\Domain\ValueObject\LeaveBalance($balance));

            $this->em->flush();

            $this->addFlash('success', sprintf('Utilisateur "%s" mis à jour.', $user->getUsername()));
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/user_edit.html.twig', ['user' => $user, 'is_self' => $isSelf]);
    }

    // -------------------------------------------------------------------------

    private function validateNewUser(string $username, string $email, string $password): ?string
    {
        if ($username === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
            return 'Nom d\'utilisateur invalide (lettres, chiffres, points, tirets).';
        }
        if ($this->em->getRepository(User::class)->findOneBy(['username' => $username])) {
            return 'Ce nom d\'utilisateur est déjà utilisé.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Adresse e-mail invalide.';
        }
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            return 'Un utilisateur avec cet e-mail existe déjà.';
        }
        if (strlen($password) < 8) {
            return 'Le mot de passe doit contenir au moins 8 caractères.';
        }

        return null;
    }

    /** @param string[] $selected */
    private function resolveRoles(array $selected): array
    {
        $allowed = ['ROLE_AGENT', 'ROLE_RH', 'ROLE_ADMIN'];
        $roles   = array_values(array_intersect($selected, $allowed));
        if (!in_array('ROLE_AGENT', $roles, true)) {
            $roles[] = 'ROLE_AGENT';
        }

        return $roles;
    }
}
