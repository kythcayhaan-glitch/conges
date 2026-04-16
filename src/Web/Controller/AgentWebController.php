<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Leave\Domain\Repository\LeaveRequestRepositoryInterface;
use App\Leave\Domain\ValueObject\LeaveStatus;
use App\Leave\Domain\ValueObject\LeaveType;
use App\Security\Entity\User;
use App\Shared\Domain\ValueObject\LeaveBalance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/agents', name: 'app_agents_')]
final class AgentWebController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface          $em,
        private readonly LeaveRequestRepositoryInterface $leaveRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function list(): Response
    {
        $users = $this->em->getRepository(User::class)->findBy([], ['username' => 'ASC']);

        return $this->render('agent/list.html.twig', ['users' => $users]);
    }

    #[Route('/profile', name: 'profile', methods: ['GET'])]
    #[IsGranted('ROLE_AGENT')]
    public function profile(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->redirectToRoute('app_agents_show', ['id' => $user->getId()]);
    }

    #[Route('/recap-pdf', name: 'recap_pdf', methods: ['GET'])]
    #[IsGranted('ROLE_CHEF_SERVICE')]
    public function recapPdf(Request $request): Response
    {
        $currentUser = $this->getUser();
        $isRh        = $this->isGranted('ROLE_RH');
        $isChef      = $this->isGranted('ROLE_CHEF_SERVICE') && !$isRh;
        $userId      = $request->query->get('userId');

        if ($isRh) {
            if ($userId) {
                $target = $this->em->getRepository(User::class)->find($userId);
                $agents = $target ? [$target] : [];
                $title  = $target ? ($target->getNomComplet() ?? $target->getUsername()) : 'Agent inconnu';
            } else {
                $agents = $this->em->getRepository(User::class)->findBy([], ['nom' => 'ASC', 'prenom' => 'ASC']);
                $title  = 'Tous les agents';
            }
        } else {
            if (!$currentUser instanceof User) {
                throw $this->createAccessDeniedException();
            }
            $chefServices  = $currentUser->getServiceNumbers();
            $allUsers      = $this->em->getRepository(User::class)->findAll();
            $serviceAgents = array_values(array_filter(
                $allUsers,
                fn(User $u) => count(array_intersect($u->getServiceNumbers(), $chefServices)) > 0
            ));
            usort($serviceAgents, fn(User $a, User $b) => strcmp((string)$a->getNom(), (string)$b->getNom()));

            if ($userId) {
                $target = $this->em->getRepository(User::class)->find($userId);
                if ($target instanceof User
                    && count(array_intersect($target->getServiceNumbers(), $chefServices)) > 0
                ) {
                    $agents = [$target];
                    $title  = $target->getNomComplet() ?? $target->getUsername();
                } else {
                    $agents = $serviceAgents;
                    $title  = 'Mon service';
                }
            } else {
                $agents = $serviceAgents;
                $title  = 'Mon service';
            }
        }

        $html = $this->renderView('agent/recap_pdf.html.twig', [
            'agents'       => $agents,
            'title'        => $title,
            'current_user' => $currentUser instanceof User ? $currentUser : null,
            'is_rh'        => $isRh,
        ]);

        $options = new \Dompdf\Options();
        $options->setIsHtml5ParserEnabled(true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();

        $slug     = preg_replace('/[^a-z0-9_]/i', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $title) ?: 'recap');
        $filename = sprintf('recap_soldes_%s_%s.pdf', strtolower($slug), date('d-m-Y'));

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[IsGranted('ROLE_AGENT')]
    public function show(string $id): Response
    {
        $currentUser = $this->getUser();

        if (!$this->isGranted('ROLE_RH')) {
            if (!$currentUser instanceof User || $currentUser->getId() !== $id) {
                throw $this->createAccessDeniedException();
            }
        }

        $user = $this->em->getRepository(User::class)->find($id);

        if ($user === null) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        return $this->render('agent/show.html.twig', ['user' => $user]);
    }

    #[Route('/{id}/balance', name: 'balance', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function updateBalance(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('balance_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_agents_show', ['id' => $id]);
        }

        $user = $this->em->getRepository(User::class)->find($id);

        if ($user === null) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $congeBalance    = (float) $request->request->get('congeBalance', $user->getLeaveBalance()->getValue());
        $rttBalance      = (float) $request->request->get('rttBalance', $user->getRttBalance()->getValue());
        $heureSupBalance = (float) $request->request->get('heureSupBalance', $user->getHeureSupBalance()->getValue());

        if ($congeBalance < 0 || $rttBalance < 0 || $heureSupBalance < 0) {
            $this->addFlash('error', 'Les soldes ne peuvent pas être négatifs.');
            return $this->redirectToRoute('app_agents_show', ['id' => $id]);
        }

        $approvedLeaves   = $this->leaveRepository->findByUserIdAndStatus($user->getId(), LeaveStatus::APPROVED);
        $usedConge        = 0.0;
        $usedRtt          = 0.0;
        $usedHeureSup     = 0.0;
        foreach ($approvedLeaves as $leave) {
            match ($leave->getType()) {
                LeaveType::RTT       => $usedRtt      += $leave->getHeures()->getValue(),
                LeaveType::HEURE_SUP => $usedHeureSup += $leave->getHeures()->getValue(),
                default              => $usedConge    += $leave->getHeures()->getValue(),
            };
        }

        $user->applyInitialLeaveBalance(new LeaveBalance($congeBalance));
        $user->applyLeaveBalance(new LeaveBalance(max(0.0, $congeBalance - $usedConge)));
        $user->applyInitialRttBalance(new LeaveBalance($rttBalance));
        $user->applyRttBalance(new LeaveBalance(max(0.0, $rttBalance - $usedRtt)));
        $user->applyInitialHeureSupBalance(new LeaveBalance($heureSupBalance));
        $user->applyHeureSupBalance(new LeaveBalance(max(0.0, $heureSupBalance - $usedHeureSup)));
        $this->em->flush();

        $this->addFlash('success', 'Soldes mis à jour.');

        return $this->redirectToRoute('app_agents_show', ['id' => $id]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function delete(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('agent_delete_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_agents_show', ['id' => $id]);
        }

        $user = $this->em->getRepository(User::class)->find($id);

        if ($user === null) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $name = $user->getNomComplet() ?? $user->getUsername();

        $this->leaveRepository->deleteAllByUserId($id);

        $this->em->remove($user);
        $this->em->flush();

        $this->addFlash('success', sprintf(
            'L\'utilisateur %s a été supprimé ainsi que toutes ses demandes de congé.',
            $name
        ));

        return $this->redirectToRoute('app_agents_list');
    }
}
