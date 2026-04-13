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
            $pendingLeaves = $this->leaveRepository->findAllPending();

            $users      = $this->em->getRepository(User::class)->findAll();
            $agentNames = [];
            foreach ($users as $u) {
                $agentNames[$u->getId()] = $u->getNomComplet() ?? $u->getUsername();
            }

            return $this->render('dashboard/index.html.twig', [
                'pending_leaves' => $pendingLeaves,
                'agent_names'    => $agentNames,
                'current_user'   => $user instanceof User ? $user : null,
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
