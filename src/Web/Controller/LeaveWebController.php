<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Leave\Application\Command\ApproveLeave\ApproveLeaveCommand;
use App\Leave\Application\Command\EditLeave\EditLeaveCommand;
use App\Leave\Application\Command\CancelLeave\CancelLeaveCommand;
use App\Leave\Application\Command\RejectLeave\RejectLeaveCommand;
use App\Leave\Application\Command\SubmitLeave\SubmitLeaveCommand;
use App\Leave\Application\Command\ValidateByChef\ValidateByChefCommand;
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
        $user      = $this->getUser();
        $leaves    = [];
        $typeParam = strtoupper($request->query->get('type', 'CONGE'));
        $allTypes  = $typeParam === 'ALL';
        $leaveType = $allTypes ? null : (LeaveType::tryFrom($typeParam) ?? LeaveType::CONGE);

        $isChef = $this->isGranted('ROLE_CHEF_SERVICE') && !$this->isGranted('ROLE_RH');

        if ($this->isGranted('ROLE_RH')) {
            $statut       = $request->query->get('statut');
            $userIdFilter = $request->query->get('userId');

            if ($userIdFilter) {
                $leaves = $this->leaveRepository->findByUserId($userIdFilter);
            } elseif ($statut === 'PENDING') {
                $leaves = $this->leaveRepository->findAllPending();
            } elseif ($statut === 'VALIDATED_CHEF') {
                $leaves = $this->leaveRepository->findAllValidatedByChef();
            } else {
                $leaves = $this->leaveRepository->findAll();
            }
        } elseif ($isChef && $user instanceof User) {
            // Chef de service : voit les demandes de tous ses services
            $chefServices   = $user->getServiceNumbers();
            $allUsers       = $this->em->getRepository(User::class)->findAll();
            $serviceUsers   = array_filter(
                $allUsers,
                fn(User $u) => count(array_intersect($u->getServiceNumbers(), $chefServices)) > 0
            );
            $serviceUserIds = array_map(fn(User $u) => $u->getId(), $serviceUsers);

            $statut = $request->query->get('statut');
            if ($statut === 'PENDING') {
                $leaves = $this->leaveRepository->findPendingByUserIds($serviceUserIds);
            } else {
                $leaves = $this->leaveRepository->findByUserIds($serviceUserIds);
            }
        } else {
            $leaves = $user instanceof User
                ? $this->leaveRepository->findByUserId($user->getId())
                : [];
        }

        // Filtrage par type (ignoré si type=ALL pour RH/Admin)
        if ($leaveType !== null) {
            $leaves = array_values(array_filter($leaves, fn($l) => $l->getType() === $leaveType));
        }

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

        // Agents disponibles pour le modal d'impression
        $printUsers = [];
        if ($this->isGranted('ROLE_RH')) {
            $printUsers = $this->em->getRepository(User::class)->findBy([], ['username' => 'ASC']);
        } elseif ($isChef && $user instanceof User && !empty($user->getServiceNumbers())) {
            $chefServices = $user->getServiceNumbers();
            $allU = $this->em->getRepository(User::class)->findAll();
            usort($allU, fn(User $a, User $b) => strcmp($a->getUsername(), $b->getUsername()));
            $printUsers = array_values(array_filter(
                $allU,
                fn(User $u) => count(array_intersect($u->getServiceNumbers(), $chefServices)) > 0
            ));
        }

        return $this->render('leave/list.html.twig', [
            'leaves'       => $leaves,
            'agent_names'  => $agentNames,
            'last_logs'    => $lastLogs,
            'user_emails'  => $userEmails,
            'leave_type'   => $leaveType,
            'is_chef'      => $isChef,
            'current_user' => $user instanceof User ? $user : null,
            'print_users'  => $printUsers,
        ]);
    }

    #[Route('/pdf', name: 'pdf', methods: ['GET'])]
    #[IsGranted('ROLE_AGENT')]
    public function pdf(Request $request): Response
    {
        $currentUser = $this->getUser();
        $typeParam   = strtoupper($request->query->get('type', 'CONGE'));
        $allTypes    = $typeParam === 'ALL';
        $leaveType   = $allTypes ? null : (LeaveType::tryFrom($typeParam) ?? LeaveType::CONGE);
        $isRh        = $this->isGranted('ROLE_RH');
        $isChef      = $this->isGranted('ROLE_CHEF_SERVICE') && !$isRh;
        $targetId    = $request->query->get('userId');

        // Résolution de l'utilisateur cible pour le nom du fichier et les soldes
        $targetUser = null;
        if ($targetId && ($isRh || $isChef)) {
            $targetUser = $this->em->getRepository(User::class)->find($targetId);
            // Sécurité : un chef ne peut imprimer que les agents de ses services
            if ($isChef && $targetUser instanceof User
                && ($currentUser instanceof User)
                && count(array_intersect($targetUser->getServiceNumbers(), $currentUser->getServiceNumbers())) === 0
            ) {
                $targetUser = null;
                $targetId   = null;
            }
        }

        if ($targetId && $targetUser) {
            // Impression d'un agent spécifique
            $leaves = $this->leaveRepository->findByUserId($targetId);
        } elseif ($isRh) {
            $statut = $request->query->get('statut');
            $leaves = match($statut) {
                'PENDING'        => $this->leaveRepository->findAllPending(),
                'VALIDATED_CHEF' => $this->leaveRepository->findAllValidatedByChef(),
                default          => $this->leaveRepository->findAll(),
            };
        } elseif ($isChef && $currentUser instanceof User && !empty($currentUser->getServiceNumbers())) {
            $chefSvcs = $currentUser->getServiceNumbers();
            $allU     = $this->em->getRepository(User::class)->findAll();
            $svcUsers = array_filter($allU, fn(User $u) => count(array_intersect($u->getServiceNumbers(), $chefSvcs)) > 0);
            $serviceUserIds = array_map(fn(User $u) => $u->getId(), $svcUsers);
            $leaves = $this->leaveRepository->findByUserIds($serviceUserIds);
        } else {
            $leaves = $currentUser instanceof User
                ? $this->leaveRepository->findByUserId($currentUser->getId())
                : [];
        }

        if (!$allTypes) {
            $leaves = array_values(array_filter($leaves, fn($l) => $l->getType() === $leaveType));
        }

        $allUsers   = $this->em->getRepository(User::class)->findAll();
        $agentNames = [];
        foreach ($allUsers as $u) {
            $agentNames[$u->getId()] = $u->getNomComplet() ?? $u->getUsername();
        }

        // Utilisateur affiché dans l'entête PDF : agent cible ou utilisateur courant
        $pdfUser = $targetUser ?? ($currentUser instanceof User ? $currentUser : null);

        $html = $this->renderView('leave/list_pdf.html.twig', [
            'leaves'       => $leaves,
            'leave_type'   => $leaveType,
            'agent_names'  => $agentNames,
            'current_user' => $pdfUser,
            'is_rh'        => $isRh && !$targetUser,
        ]);

        $options = new \Dompdf\Options();
        $options->setIsHtml5ParserEnabled(true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->loadHtml($html);
        $dompdf->render();

        // Nom du fichier basé sur l'agent cible (ou le connecté)
        $nameUser  = $targetUser ?? ($currentUser instanceof User ? $currentUser : null);
        $prenom    = $nameUser instanceof User ? ($nameUser->getPrenom() ?? '') : '';
        $nom       = $nameUser instanceof User ? ($nameUser->getNom() ?? '') : '';
        $namePart  = trim($prenom . '_' . $nom);
        $namePart  = $namePart !== '_'
            ? preg_replace('/[^a-zA-Z0-9_\-]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $namePart))
            : 'agent';
        $typePart  = $allTypes ? 'toutes' : strtolower($leaveType->value);
        $filename  = sprintf('%s_%s_%s.pdf', strtolower($namePart), $typePart, date('d-m-Y'));

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
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
            $dateDebut      = (string) $request->request->get('dateDebut', '');
            $dateFin        = (string) $request->request->get('dateFin', '');
            // Si créneau sélectionné et dateFin absente, on utilise dateDebut
            if (($journeeEntiere || $matin || $apresMidi) && $dateFin === '') {
                $dateFin = $dateDebut;
            }
            $command        = new SubmitLeaveCommand(
                agentId:        $agentId,
                dateDebut:      $dateDebut,
                heureDebut:     ($h = $request->request->get('heureDebut', '')) !== '' ? $h : null,
                dateFin:        $dateFin,
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

        // Pré-remplissage depuis le calendrier (?dateDebut=…&dateFin=… ou ?date=…)
        $preDebut = $request->query->get('dateDebut', $request->query->get('date', ''));
        $preFin   = $request->query->get('dateFin', $preDebut);
        $formData = $preDebut !== '' ? ['dateDebut' => $preDebut, 'dateFin' => $preFin] : [];

        return $this->render('leave/new.html.twig', [
            'form_data'  => $formData,
            'users'      => $users,
            'is_rh'      => $isRh,
            'leave_type' => $leaveType,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    #[IsGranted('ROLE_AGENT')]
    public function show(string $id): Response
    {
        $leave = $this->leaveRepository->findById($id);

        if ($leave === null) {
            throw $this->createNotFoundException('Demande de congé introuvable.');
        }

        $this->denyAccessUnlessGranted(LeaveRequestVoter::LEAVE_VIEW, $leave);

        $leaveUser = $this->em->getRepository(User::class)->find($leave->getUserId());

        $isChef      = $this->isGranted('ROLE_CHEF_SERVICE');
        $currentUser = $this->getUser();
        $isOwner     = $currentUser instanceof User && $currentUser->getId() === $leave->getUserId();

        // Vérifie que le chef est bien du même service que l'agent
        $canValidateChef = false;
        if ($isChef) {
            if ($currentUser instanceof User
                && !empty($currentUser->getServiceNumbers())
                && $leaveUser instanceof User
                && count(array_intersect($leaveUser->getServiceNumbers(), $currentUser->getServiceNumbers())) > 0
                && $leave->isPending()
            ) {
                $canValidateChef = true;
            }
        }

        $auditLogs = ($this->isGranted('ROLE_RH') || $isChef || $isOwner)
            ? $this->leaveRepository->findAuditLogsByLeaveRequestId($id)
            : [];

        $rejectionLog  = null;
        if ($leave->isRejected()) {
            $logs = $auditLogs ?: $this->leaveRepository->findAuditLogsByLeaveRequestId($id);
            foreach (array_reverse($logs) as $log) {
                if ($log->getNouveauStatut()->value === 'REJECTED' && $log->getCommentaire()) {
                    $rejectionLog = $log;
                    break;
                }
            }
        }

        // Auteur et date de la validation chef (affiché pour RH/Admin)
        $validatedByChefName = null;
        $validatedByChefAt   = null;
        $allLogs = $this->leaveRepository->findAuditLogsByLeaveRequestId($id);
        foreach (array_reverse($allLogs) as $log) {
            if ($log->getNouveauStatut()->value === 'VALIDATED_CHEF' && $log->getAuteurId()) {
                $author = $this->em->getRepository(User::class)->find($log->getAuteurId());
                if ($author instanceof User) {
                    $validatedByChefName = $author->getNomComplet() ?? $author->getUsername();
                }
                $validatedByChefAt = $log->getCreatedAt();
                break;
            }
        }

        return $this->render('leave/show.html.twig', [
            'leave'                  => $leave,
            'leave_user'             => $leaveUser,
            'audit_logs'             => $auditLogs,
            'rejection_log'          => $rejectionLog,
            'is_chef'                => $isChef,
            'can_validate_chef'      => $canValidateChef,
            'validated_by_chef_name' => $validatedByChefName,
            'validated_by_chef_at'   => $validatedByChefAt,
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
    #[IsGranted('ROLE_AGENT')]
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
    #[IsGranted('ROLE_AGENT')]
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

    #[Route('/{id}/validate-chef', name: 'validate_chef', methods: ['POST'])]
    #[IsGranted('ROLE_CHEF_SERVICE')]
    public function validateByChef(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('leave_validate_chef_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_leave_show', ['id' => $id]);
        }

        $leave = $this->leaveRepository->findById($id);
        if ($leave === null) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(LeaveRequestVoter::LEAVE_VALIDATE_CHEF, $leave);

        $user = $this->getUser();

        try {
            $this->commandBus->dispatch(new ValidateByChefCommand(
                leaveRequestId:    $id,
                validatedByUserId: $user instanceof User ? $user->getId() : '',
                commentaire:       $request->request->get('commentaire'),
            ));
            $this->addFlash('success', 'Demande validée par le chef de service. En attente de validation RH.');
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

        // Demande validée (chef ou RH) : seul l'admin peut supprimer
        if (($leave->isValidatedByChef() || $leave->isApproved()) && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Impossible de supprimer une demande validée ou approuvée. Seul l\'administrateur peut effectuer cette action.');
            return $this->redirectToRoute('app_leave_show', ['id' => $id]);
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

    #[Route('/calendar', name: 'calendar', methods: ['GET'])]
    #[IsGranted('ROLE_AGENT')]
    public function calendar(): Response
    {
        $user   = $this->getUser();
        $leaves = [];
        $isRh   = $this->isGranted('ROLE_RH');
        $isChef = $this->isGranted('ROLE_CHEF_SERVICE') && !$isRh;

        if ($isRh) {
            $leaves = $this->leaveRepository->findAll();
        } elseif ($isChef && $user instanceof User) {
            $chefServices   = $user->getServiceNumbers();
            $allUsers       = $this->em->getRepository(User::class)->findAll();
            $serviceUserIds = array_map(
                fn(User $u) => $u->getId(),
                array_filter($allUsers, fn(User $u) => count(array_intersect($u->getServiceNumbers(), $chefServices)) > 0)
            );
            $leaves = $this->leaveRepository->findByUserIds($serviceUserIds);
        } else {
            $leaves = $user instanceof User ? $this->leaveRepository->findByUserId($user->getId()) : [];
        }

        // Exclure annulées et rejetées
        $leaves = array_values(array_filter($leaves, fn($l) => !$l->isCancelled() && !$l->isRejected()));

        // Index userId → nom
        $allUsers   = $this->em->getRepository(User::class)->findAll();
        $agentNames = [];
        foreach ($allUsers as $u) {
            $agentNames[$u->getId()] = $u->getNomComplet() ?? $u->getUsername();
        }

        $typeColors = [
            'CONGE'     => '#0d6efd',
            'RTT'       => '#20c997',
            'HEURE_SUP' => '#fd7e14',
        ];

        $events = [];
        foreach ($leaves as $leave) {
            $baseColor = $typeColors[$leave->getType()->value] ?? '#6c757d';
            [$bgColor, $borderColor] = match ($leave->getStatutValue()) {
                'APPROVED'       => [$baseColor,  $baseColor],   // plein = couleur du type
                'VALIDATED_CHEF' => ['#9e9e9e',   '#757575'],    // gris
                default          => ['#e53935',   '#b71c1c'],    // rouge (PENDING)
            };
            $endDate = (new \DateTimeImmutable($leave->getDateFinFormatted()))->modify('+1 day')->format('Y-m-d');
            $prefix  = ($isRh || $isChef) ? ($agentNames[$leave->getUserId()] ?? '?') . ' — ' : '';

            $events[] = [
                'title'           => $prefix . $leave->getType()->label(),
                'start'           => $leave->getDateDebutFormatted(),
                'end'             => $endDate,
                'url'             => $this->generateUrl('app_leave_show', ['id' => $leave->getId()]),
                'backgroundColor' => $bgColor,
                'borderColor'     => $borderColor,
                'textColor'       => '#fff',
            ];
        }

        return $this->render('leave/calendar.html.twig', [
            'events'  => $events,
            'is_rh'   => $isRh,
            'is_chef' => $isChef,
        ]);
    }
}
