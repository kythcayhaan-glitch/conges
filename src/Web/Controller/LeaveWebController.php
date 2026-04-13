<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Leave\Application\Command\ApproveLeave\ApproveLeaveCommand;
use App\Leave\Application\Command\EditLeave\EditLeaveCommand;
use App\Leave\Application\Command\CancelLeave\CancelLeaveCommand;
use App\Leave\Application\Command\RejectLeave\RejectLeaveCommand;
use App\Leave\Application\Command\SubmitLeave\SubmitLeaveCommand;
use App\Leave\Domain\Repository\LeaveRequestRepositoryInterface;
use App\Leave\Domain\Service\LeaveDebitService;
use App\Leave\Domain\ValueObject\LeaveType;
use App\Leave\Infrastructure\Security\LeaveRequestVoter;
use App\Security\Entity\User;
use App\Shared\Domain\Bus\CommandBusInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/leave', name: 'app_leave_')]
final class LeaveWebController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface              $commandBus,
        private readonly LeaveRequestRepositoryInterface  $leaveRepository,
        private readonly ValidatorInterface               $validator,
        private readonly EntityManagerInterface           $em,
        private readonly LeaveDebitService                $leaveDebitService,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_AGENT')]
    public function list(Request $request): Response
    {
        $user         = $this->getUser();
        $leaves       = [];
        $typeParam    = $request->query->get('type', 'CONGE');
        $leaveType    = LeaveType::tryFrom(strtoupper($typeParam)) ?? LeaveType::CONGE;

        if ($this->isGranted('ROLE_RH')) {
            $statut       = $request->query->get('statut');
            $userIdFilter = $request->query->get('userId');

            if ($userIdFilter) {
                $leaves = $this->leaveRepository->findByUserId($userIdFilter);
            } elseif ($statut === 'PENDING') {
                $leaves = $this->leaveRepository->findAllPending();
            } else {
                $leaves = $this->leaveRepository->findAll();
            }
        } else {
            $leaves = $user instanceof User
                ? $this->leaveRepository->findByUserId($user->getId())
                : [];
        }

        // Filtrage par type
        $leaves = array_values(array_filter($leaves, fn($l) => $l->getType() === $leaveType));

        // Index userId → nom complet
        $users      = $this->em->getRepository(User::class)->findAll();
        $agentNames = [];
        foreach ($users as $u) {
            $agentNames[$u->getId()] = $u->getNomComplet() ?? $u->getUsername();
        }

        // Dernier log d'audit par demande
        $leaveIds = array_map(fn($l) => $l->getId(), $leaves);
        $lastLogs = $this->leaveRepository->findLastAuditLogsByLeaveRequestIds($leaveIds);

        // Index userId → nom complet pour les auteurs d'audit
        $authorIds  = array_filter(array_unique(array_map(fn($log) => $log->getAuteurId(), $lastLogs)));
        $userEmails = [];
        if (!empty($authorIds)) {
            $authorUsers = $this->em->getRepository(User::class)->findBy(['id' => array_values($authorIds)]);
            foreach ($authorUsers as $u) {
                $userEmails[$u->getId()] = $u->getNomComplet() ?? $u->getUsername();
            }
        }

        return $this->render('leave/list.html.twig', [
            'leaves'      => $leaves,
            'agent_names' => $agentNames,
            'last_logs'   => $lastLogs,
            'user_emails' => $userEmails,
            'leave_type'  => $leaveType,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_AGENT')]
    public function new(Request $request): Response
    {
        $isRh      = $this->isGranted('ROLE_RH');
        $users     = $isRh ? $this->em->getRepository(User::class)->findBy([], ['username' => 'ASC']) : [];
        $typeParam = $request->query->get('type', $request->request->get('leaveType', 'CONGE'));
        $leaveType = LeaveType::tryFrom(strtoupper((string) $typeParam)) ?? LeaveType::CONGE;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('leave_new', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_leave_new', ['type' => $leaveType->value]);
            }

            $user = $this->getUser();

            $agentId = $isRh
                ? (string) $request->request->get('agentId', '')
                : ($user instanceof User ? $user->getId() : '');

            $matin          = (bool) $request->request->get('matin', false);
            $apresMidi      = (bool) $request->request->get('apresMidi', false);
            $journeeEntiere = (bool) $request->request->get('journeeEntiere', false);
            $command        = new SubmitLeaveCommand(
                agentId:        $agentId,
                dateDebut:      (string) $request->request->get('dateDebut', ''),
                heureDebut:     ($h = $request->request->get('heureDebut', '')) !== '' ? $h : null,
                dateFin:        (string) $request->request->get('dateFin', ''),
                heureFin:       ($h = $request->request->get('heureFin', '')) !== '' ? $h : null,
                motif:          ($m = trim((string) $request->request->get('motif', ''))) !== '' ? $m : null,
                matin:          $matin,
                apresMidi:      $apresMidi,
                journeeEntiere: $journeeEntiere,
                type:           $leaveType,
            );

            $violations = $this->validator->validate($command);

            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $v) { $errors[] = $v->getMessage(); }
                $this->addFlash('error', implode(' ', $errors));
                return $this->render('leave/new.html.twig', [
                    'form_data'  => $request->request->all(),
                    'users'      => $users,
                    'is_rh'      => $isRh,
                    'leave_type' => $leaveType,
                ]);
            }

            try {
                $this->commandBus->dispatch($command);
                $this->addFlash('success', 'Demande soumise avec succès.');
                return $this->redirectToRoute('app_leave_list', ['type' => $leaveType->value]);
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('leave/new.html.twig', [
            'form_data'  => [],
            'users'      => $users,
            'is_rh'      => $isRh,
            'leave_type' => $leaveType,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[IsGranted('ROLE_AGENT')]
    public function show(string $id): Response
    {
        $leave = $this->leaveRepository->findById($id);

        if ($leave === null) {
            throw $this->createNotFoundException('Demande de congé introuvable.');
        }

        $this->denyAccessUnlessGranted(LeaveRequestVoter::LEAVE_VIEW, $leave);

        $leaveUser = $this->em->getRepository(User::class)->find($leave->getUserId());

        $auditLogs     = $this->isGranted('ROLE_RH')
            ? $this->leaveRepository->findAuditLogsByLeaveRequestId($id)
            : [];

        $rejectionLog  = null;
        if ($leave->isRejected()) {
            $logs = $this->leaveRepository->findAuditLogsByLeaveRequestId($id);
            foreach (array_reverse($logs) as $log) {
                if ($log->getNouveauStatut()->value === 'REJECTED' && $log->getCommentaire()) {
                    $rejectionLog = $log;
                    break;
                }
            }
        }

        return $this->render('leave/show.html.twig', [
            'leave'         => $leave,
            'leave_user'    => $leaveUser,
            'audit_logs'    => $auditLogs,
            'rejection_log' => $rejectionLog,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_AGENT')]
    public function edit(string $id, Request $request): Response
    {
        $leave = $this->leaveRepository->findById($id);

        if ($leave === null) {
            throw $this->createNotFoundException('Demande de congé introuvable.');
        }

        $this->denyAccessUnlessGranted(LeaveRequestVoter::LEAVE_CANCEL, $leave);

        if (!$leave->isPending()) {
            $this->addFlash('error', 'Seules les demandes en attente peuvent être modifiées.');
            return $this->redirectToRoute('app_leave_show', ['id' => $id]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('leave_edit_' . $id, $request->request->get('_token'))) {
                $this->addFlash('error', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_leave_edit', ['id' => $id]);
            }

            $user           = $this->getUser();
            $matin          = (bool) $request->request->get('matin', false);
            $apresMidi      = (bool) $request->request->get('apresMidi', false);
            $journeeEntiere = (bool) $request->request->get('journeeEntiere', false);
            $command        = new EditLeaveCommand(
                leaveRequestId: $id,
                dateDebut:      (string) $request->request->get('dateDebut', ''),
                heureDebut:     ($h = $request->request->get('heureDebut', '')) !== '' ? $h : null,
                dateFin:        (string) $request->request->get('dateFin', ''),
                heureFin:       ($h = $request->request->get('heureFin', '')) !== '' ? $h : null,
                motif:          ($m = trim((string) $request->request->get('motif', ''))) !== '' ? $m : null,
                editedByUserId: $user instanceof User ? $user->getId() : '',
                matin:          $matin,
                apresMidi:      $apresMidi,
                journeeEntiere: $journeeEntiere,
            );

            $violations = $this->validator->validate($command);

            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $v) {
                    $errors[] = $v->getMessage();
                }
                $this->addFlash('error', implode(' ', $errors));
                return $this->render('leave/edit.html.twig', ['leave' => $leave, 'form_data' => $request->request->all()]);
            }

            try {
                $this->commandBus->dispatch($command);
                $this->addFlash('success', 'Demande modifiée avec succès.');
                return $this->redirectToRoute('app_leave_show', ['id' => $id]);
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('leave/edit.html.twig', ['leave' => $leave, 'form_data' => []]);
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function approve(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('leave_approve_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_leave_show', ['id' => $id]);
        }

        $leave = $this->leaveRepository->findById($id);
        if ($leave === null) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(LeaveRequestVoter::LEAVE_APPROVE, $leave);

        $user = $this->getUser();

        try {
            $this->commandBus->dispatch(new ApproveLeaveCommand(
                leaveRequestId:   $id,
                approvedByUserId: $user instanceof User ? $user->getId() : '',
                commentaire:      $request->request->get('commentaire'),
            ));
            $this->addFlash('success', 'Demande approuvée.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_leave_show', ['id' => $id]);
    }

    #[Route('/{id}/reject', name: 'reject', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function reject(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('leave_reject_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_leave_show', ['id' => $id]);
        }

        $leave = $this->leaveRepository->findById($id);
        if ($leave === null) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(LeaveRequestVoter::LEAVE_REJECT, $leave);

        $user = $this->getUser();

        try {
            $this->commandBus->dispatch(new RejectLeaveCommand(
                leaveRequestId:   $id,
                rejectedByUserId: $user instanceof User ? $user->getId() : '',
                commentaire:      ($c = trim((string) $request->request->get('commentaire', ''))) !== '' ? $c : null,
            ));
            $this->addFlash('success', 'Demande rejetée.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_leave_show', ['id' => $id]);
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    #[IsGranted('ROLE_AGENT')]
    public function cancel(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('leave_cancel_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_leave_show', ['id' => $id]);
        }

        $leave = $this->leaveRepository->findById($id);
        if ($leave === null) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(LeaveRequestVoter::LEAVE_CANCEL, $leave);

        $user = $this->getUser();

        try {
            $this->commandBus->dispatch(new CancelLeaveCommand(
                leaveRequestId:    $id,
                cancelledByUserId: $user instanceof User ? $user->getId() : '',
                commentaire:       $request->request->get('commentaire'),
            ));
            $this->addFlash('success', 'Demande annulée.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_leave_list');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function delete(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('leave_delete_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_leave_show', ['id' => $id]);
        }

        $leave = $this->leaveRepository->findById($id);
        if ($leave === null) {
            throw $this->createNotFoundException();
        }

        // Si la demande était approuvée, rembourser le solde
        if ($leave->isApproved()) {
            $user = $this->em->getRepository(User::class)->find($leave->getUserId());
            if ($user !== null) {
                $this->leaveDebitService->credit($user, $leave->getHeures(), $leave->getType());
            }
        }

        $this->leaveRepository->delete($leave);
        $this->addFlash('success', 'Demande supprimée.');

        return $this->redirectToRoute('app_leave_list');
    }
}
