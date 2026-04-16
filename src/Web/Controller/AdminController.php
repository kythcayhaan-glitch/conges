<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Leave\Domain\Repository\LeaveRequestRepositoryInterface;
use App\Leave\Domain\ValueObject\LeaveStatus;
use App\Leave\Domain\ValueObject\LeaveType;
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
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface          $em,
        private readonly UserPasswordHasherInterface     $hasher,
        private readonly LeaveRequestRepositoryInterface $leaveRepository,
    ) {
    }

    #[Route('/users', name: 'users', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function users(): Response
    {
        $users = $this->em->getRepository(User::class)->findBy([], ['username' => 'ASC']);

        return $this->render('admin/users.html.twig', ['users' => $users]);
    }

    #[Route('/users/new', name: 'users_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
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

            $roles           = $this->resolveRoles($request->request->all('roles') ?: []);
            $serviceNumbers  = $this->parseServiceNumbers((string) $request->request->get('serviceNumbers', ''));
            $rttBalance      = (float) $request->request->get('rttBalanceHours', 0.0);
            $heureSupBalance = (float) $request->request->get('heureSupBalanceHours', 0.0);

            $user = new User(
                id:                  Uuid::v4()->toRfc4122(),
                email:               $email,
                roles:               $roles,
                username:            $username,
                nom:                 $nom !== '' ? $nom : null,
                prenom:              $prenom !== '' ? $prenom : null,
                leaveBalanceValue:   $balance,
                rttBalanceValue:     $rttBalance,
                heureSupBalanceValue: $heureSupBalance,
                serviceNumbers:      $serviceNumbers,
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
    #[IsGranted('ROLE_RH')]
    public function editUser(string $id, Request $request): Response
    {
        $user = $this->em->getRepository(User::class)->find($id);

        if ($user === null) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $isSelf  = $user->getUserIdentifier() === $this->getUser()?->getUserIdentifier();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $tplVars = ['user' => $user, 'is_self' => $isSelf, 'is_admin' => $isAdmin];

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
                return $this->render('admin/user_edit.html.twig', $tplVars);
            }
            $existing = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
            if ($existing && $existing->getId() !== $user->getId()) {
                $this->addFlash('error', 'Ce nom d\'utilisateur est déjà utilisé.');
                return $this->render('admin/user_edit.html.twig', $tplVars);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Adresse e-mail invalide.');
                return $this->render('admin/user_edit.html.twig', $tplVars);
            }
            $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existing && $existing->getId() !== $user->getId()) {
                $this->addFlash('error', 'Cette adresse e-mail est déjà utilisée.');
                return $this->render('admin/user_edit.html.twig', $tplVars);
            }
            if ($password !== '' && strlen($password) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');
                return $this->render('admin/user_edit.html.twig', $tplVars);
            }

            $user->setUsername($username);
            $user->setEmail($email);

            if ($password !== '') {
                $user->setPassword($this->hasher->hashPassword($user, $password));
            }
            if ($isAdmin && !$isSelf) {
                $user->setRoles($this->resolveRoles($request->request->all('roles') ?: []));
            }

            $nom    = trim((string) $request->request->get('nom', ''));
            $prenom = trim((string) $request->request->get('prenom', ''));

            $user->setNom($nom !== '' ? $nom : null);
            $user->setPrenom($prenom !== '' ? $prenom : null);

            $congeInitial    = (float) $request->request->get('leaveBalanceHours', $user->getInitialLeaveBalance()->getValue());
            $rttInitial      = (float) $request->request->get('rttBalanceHours', $user->getInitialRttBalance()->getValue());
            $heureSupInitial = (float) $request->request->get('heureSupBalanceHours', $user->getInitialHeureSupBalance()->getValue());

            $approvedLeaves  = $this->leaveRepository->findByUserIdAndStatus($user->getId(), LeaveStatus::APPROVED);
            $usedConge       = 0.0;
            $usedRtt         = 0.0;
            $usedHeureSup    = 0.0;
            foreach ($approvedLeaves as $leave) {
                match ($leave->getType()) {
                    LeaveType::RTT       => $usedRtt      += $leave->getHeures()->getValue(),
                    LeaveType::HEURE_SUP => $usedHeureSup += $leave->getHeures()->getValue(),
                    default              => $usedConge    += $leave->getHeures()->getValue(),
                };
            }

            $user->applyInitialLeaveBalance(new \App\Shared\Domain\ValueObject\LeaveBalance($congeInitial));
            $user->applyLeaveBalance(new \App\Shared\Domain\ValueObject\LeaveBalance(max(0.0, $congeInitial - $usedConge)));
            $user->applyInitialRttBalance(new \App\Shared\Domain\ValueObject\LeaveBalance($rttInitial));
            $user->applyRttBalance(new \App\Shared\Domain\ValueObject\LeaveBalance(max(0.0, $rttInitial - $usedRtt)));
            $user->applyInitialHeureSupBalance(new \App\Shared\Domain\ValueObject\LeaveBalance($heureSupInitial));
            $user->applyHeureSupBalance(new \App\Shared\Domain\ValueObject\LeaveBalance(max(0.0, $heureSupInitial - $usedHeureSup)));

            $user->setServiceNumbers($this->parseServiceNumbers((string) $request->request->get('serviceNumbers', '')));

            $this->em->flush();

            $this->addFlash('success', sprintf('Utilisateur "%s" mis à jour.', $user->getUsername()));
            return $this->redirectToRoute('app_agents_show', ['id' => $user->getId()]);
        }

        return $this->render('admin/user_edit.html.twig', $tplVars);
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
        $allowed = ['ROLE_AGENT', 'ROLE_CHEF_SERVICE', 'ROLE_RH', 'ROLE_ADMIN'];
        $roles   = array_values(array_intersect($selected, $allowed));
        if (!in_array('ROLE_AGENT', $roles, true)) {
            $roles[] = 'ROLE_AGENT';
        }

        return $roles;
    }

    /**
     * Convertit une saisie texte "03, 11" en tableau normalisé ['03', '11'].
     * @return string[]
     */
    private function parseServiceNumbers(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $result = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if (preg_match('/^\d{1,2}$/', $p)) {
                $result[] = str_pad($p, 2, '0', STR_PAD_LEFT);
            }
        }
        return array_values(array_unique($result));
    }
}
