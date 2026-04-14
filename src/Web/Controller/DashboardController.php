<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Leave\Domain\Repository\LeaveRequestRepositoryInterface;
use App\Security\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRepository,
        private readonly EntityManagerInterface          $em,
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_AGENT')]
    public function index(): Response
    {
        $user = $this->getUser();

        if ($this->isGranted('ROLE_RH')) {
            $pendingLeaves       = $this->leaveRepository->findAllPending();
            $validatedChefLeaves = $this->leaveRepository->findAllValidatedByChef();

            $users      = $this->em->getRepository(User::class)->findBy([], ['nom' => 'ASC', 'prenom' => 'ASC']);
            $agentNames = [];
            foreach ($users as $u) {
                $agentNames[$u->getId()] = $u->getNomComplet() ?? $u->getUsername();
            }

            return $this->render('dashboard/index.html.twig', [
                'pending_leaves'       => $pendingLeaves,
                'validated_chef_leaves'=> $validatedChefLeaves,
                'agent_names'          => $agentNames,
                'current_user'         => $user instanceof User ? $user : null,
                'recap_agents'         => $users,
            ]);
        }

        if ($this->isGranted('ROLE_CHEF_SERVICE') && $user instanceof User) {
            $chefServices   = $user->getServiceNumbers();
            $allUsers       = $this->em->getRepository(User::class)->findAll();
            $serviceUsers   = array_values(array_filter(
                $allUsers,
                fn(User $u) => count(array_intersect($u->getServiceNumbers(), $chefServices)) > 0
            ));
            usort($serviceUsers, fn(User $a, User $b) => strcmp((string)$a->getNom(), (string)$b->getNom()));
            $serviceUserIds = array_map(fn(User $u) => $u->getId(), $serviceUsers);
            $pendingLeaves  = $this->leaveRepository->findPendingByUserIds($serviceUserIds);

            $agentNames = [];
            foreach ($serviceUsers as $u) {
                $agentNames[$u->getId()] = $u->getNomComplet() ?? $u->getUsername();
            }

            return $this->render('dashboard/index.html.twig', [
                'pending_leaves' => $pendingLeaves,
                'agent_names'    => $agentNames,
                'current_user'   => $user,
                'is_chef'        => true,
                'recap_agents'   => $serviceUsers,
            ]);
        }

        $myLeaves = $user instanceof User
            ? array_slice($this->leaveRepository->findByUserId($user->getId()), 0, 5)
            : [];

        $leaveIds = array_map(fn($l) => $l->getId(), $myLeaves);
        $lastLogs = $this->leaveRepository->findLastAuditLogsByLeaveRequestIds($leaveIds);

        $authorNames = [];
        $authorIds   = array_filter(array_unique(array_map(fn($log) => $log->getAuteurId(), $lastLogs)));
        if (!empty($authorIds)) {
            foreach ($this->em->getRepository(User::class)->findBy(['id' => array_values($authorIds)]) as $u) {
                $authorNames[$u->getId()] = $u->getNomComplet() ?? $u->getUsername();
            }
        }

        return $this->render('dashboard/index.html.twig', [
            'current_user' => $user instanceof User ? $user : null,
            'my_leaves'    => $myLeaves,
            'last_logs'    => $lastLogs,
            'author_names' => $authorNames,
        ]);
    }
}
