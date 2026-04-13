<?php

// src/Leave/Infrastructure/Controller/LeaveRequestController.php

declare(strict_types=1);

namespace App\Leave\Infrastructure\Controller;

use App\Leave\Application\Command\ApproveLeave\ApproveLeaveCommand;
use App\Leave\Application\Command\CancelLeave\CancelLeaveCommand;
use App\Leave\Application\Command\RejectLeave\RejectLeaveCommand;
use App\Leave\Application\Command\SubmitLeave\SubmitLeaveCommand;
use App\Leave\Domain\Exception\LeaveRequestNotFoundException;
use App\Leave\Domain\Repository\LeaveRequestRepositoryInterface;
use App\Leave\Infrastructure\Security\LeaveRequestVoter;
use App\Security\Entity\User;
use App\Shared\Domain\Bus\CommandBusInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Controller REST pour la gestion des demandes de congé.
 * Délègue toute la logique métier aux handlers via le CommandBus.
 */
#[Route('/api/leave', name: 'api_leave_')]
final class LeaveRequestController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface              $commandBus,
        private readonly LeaveRequestRepositoryInterface  $leaveRequestRepository,
        private readonly ValidatorInterface               $validator,
        private readonly SerializerInterface              $serializer,
    ) {
    }

    /**
     * Soumet une nouvelle demande de congé.
     * L'agent ne peut soumettre que pour lui-même.
     *
     * POST /api/leave
     */
    #[Route('', name: 'submit', methods: ['POST'])]
    #[IsGranted('ROLE_AGENT')]
    public function submit(Request $request): JsonResponse
    {
        $data    = $request->toArray();
        $user    = $this->getUser();
        $agentId = $this->resolveAgentId($user);

        // Si ROLE_RH, peut soumettre pour n'importe quel agent ; sinon forcé sur son propre ID
        if ($this->isGranted('ROLE_RH') && isset($data['agentId'])) {
            $agentId = $data['agentId'];
        }

        $command = new SubmitLeaveCommand(
            agentId:    $agentId,
            dateDebut:  $data['dateDebut'] ?? '',
            heureDebut: $data['heureDebut'] ?? '',
            dateFin:    $data['dateFin'] ?? '',
            heureFin:   $data['heureFin'] ?? '',
            motif:      ($data['motif'] ?? '') !== '' ? $data['motif'] : null,
        );

        $violations = $this->validator->validate($command);

        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        $leaveRequestId = $this->commandBus->dispatch($command);

        return new JsonResponse(
            ['id' => $leaveRequestId, 'message' => 'Demande de congé soumise avec succès.'],
            Response::HTTP_CREATED
        );
    }

    /**
     * Liste les demandes de congé.
     * - ROLE_AGENT : uniquement ses propres demandes.
     * - ROLE_RH    : toutes les demandes en attente ou toutes avec filtre.
     *
     * GET /api/leave
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_AGENT')]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if ($this->isGranted('ROLE_RH') && $request->query->get('pending') === 'true') {
            $leaveRequests = $this->leaveRequestRepository->findAllPending();
        } elseif ($this->isGranted('ROLE_RH') && $request->query->has('userId')) {
            $leaveRequests = $this->leaveRequestRepository->findByUserId(
                $request->query->get('userId')
            );
        } else {
            $leaveRequests = $this->leaveRequestRepository->findByUserId(
                $this->resolveAgentId($user)
            );
        }

        $json = $this->serializer->serialize($leaveRequests, 'json', ['groups' => ['leave:read']]);

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    /**
     * Retourne le détail d'une demande de congé.
     *
     * GET /api/leave/{id}
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[IsGranted('ROLE_AGENT')]
    public function show(string $id): JsonResponse
    {
        $leaveRequest = $this->leaveRequestRepository->findById($id);

        if ($leaveRequest === null) {
            throw new LeaveRequestNotFoundException($id);
        }

        $this->denyAccessUnlessGranted(LeaveRequestVoter::LEAVE_VIEW, $leaveRequest);

        $json = $this->serializer->serialize($leaveRequest, 'json', ['groups' => ['leave:read']]);

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    /**
     * Approuve une demande de congé.
     * Réservé à ROLE_RH.
     *
     * PATCH /api/leave/{id}/approve
     */
    #[Route('/{id}/approve', name: 'approve', methods: ['PATCH'])]
    #[IsGranted('ROLE_RH')]
    public function approve(string $id, Request $request): JsonResponse
    {
        $leaveRequest = $this->leaveRequestRepository->findById($id);

        if ($leaveRequest === null) {
            throw new LeaveRequestNotFoundException($id);
        }

        $this->denyAccessUnlessGranted(LeaveRequestVoter::LEAVE_APPROVE, $leaveRequest);

        $data = $request->toArray();
        $user = $this->getUser();

        $command = new ApproveLeaveCommand(
            leaveRequestId:   $id,
            approvedByUserId: $this->resolveUserId($user),
            commentaire:      $data['commentaire'] ?? null,
        );

        $violations = $this->validator->validate($command);

        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        $this->commandBus->dispatch($command);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Rejette une demande de congé.
     * Réservé à ROLE_RH.
     *
     * PATCH /api/leave/{id}/reject
     */
    #[Route('/{id}/reject', name: 'reject', methods: ['PATCH'])]
    #[IsGranted('ROLE_RH')]
    public function reject(string $id, Request $request): JsonResponse
    {
        $leaveRequest = $this->leaveRequestRepository->findById($id);

        if ($leaveRequest === null) {
            throw new LeaveRequestNotFoundException($id);
        }

        $this->denyAccessUnlessGranted(LeaveRequestVoter::LEAVE_REJECT, $leaveRequest);

        $data = $request->toArray();
        $user = $this->getUser();

        $command = new RejectLeaveCommand(
            leaveRequestId:   $id,
            rejectedByUserId: $this->resolveUserId($user),
            commentaire:      ($data['commentaire'] ?? '') !== '' ? $data['commentaire'] : null,
        );

        $violations = $this->validator->validate($command);

        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        $this->commandBus->dispatch($command);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Annule une demande de congé.
     * - Agent : peut annuler ses propres demandes PENDING uniquement.
     * - RH    : peut annuler PENDING ou APPROVED.
     *
     * PATCH /api/leave/{id}/cancel
     */
    #[Route('/{id}/cancel', name: 'cancel', methods: ['PATCH'])]
    #[IsGranted('ROLE_AGENT')]
    public function cancel(string $id, Request $request): JsonResponse
    {
        $leaveRequest = $this->leaveRequestRepository->findById($id);

        if ($leaveRequest === null) {
            throw new LeaveRequestNotFoundException($id);
        }

        $this->denyAccessUnlessGranted(LeaveRequestVoter::LEAVE_CANCEL, $leaveRequest);

        $data = $request->toArray();
        $user = $this->getUser();

        $command = new CancelLeaveCommand(
            leaveRequestId:    $id,
            cancelledByUserId: $this->resolveUserId($user),
            commentaire:       $data['commentaire'] ?? null,
        );

        $violations = $this->validator->validate($command);

        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        $this->commandBus->dispatch($command);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Retourne l'historique d'audit d'une demande de congé.
     *
     * GET /api/leave/{id}/audit
     */
    #[Route('/{id}/audit', name: 'audit', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function audit(string $id): JsonResponse
    {
        $leaveRequest = $this->leaveRequestRepository->findById($id);

        if ($leaveRequest === null) {
            throw new LeaveRequestNotFoundException($id);
        }

        $logs = $this->leaveRequestRepository->findAuditLogsByLeaveRequestId($id);
        $json = $this->serializer->serialize($logs, 'json', ['groups' => ['audit:read']]);

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    private function resolveAgentId(?UserInterface $user): string
    {
        return $user instanceof User ? $user->getId() : '';
    }

    /**
     * Retourne l'UUID interne de l'utilisateur connecté (pour les logs d'audit).
     */
    private function resolveUserId(?UserInterface $user): string
    {
        if ($user instanceof User) {
            return $user->getId();
        }

        return $user?->getUserIdentifier() ?? '';
    }

    /**
     * Formate les violations de validation en réponse JSON 422.
     */
    private function validationErrorResponse(
        \Symfony\Component\Validator\ConstraintViolationListInterface $violations
    ): JsonResponse {
        $errors = [];

        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }

        return new JsonResponse(
            ['error' => 'Les données fournies sont invalides.', 'violations' => $errors],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
