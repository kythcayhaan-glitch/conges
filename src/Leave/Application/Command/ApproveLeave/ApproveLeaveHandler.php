<?php

// src/Leave/Application/Command/ApproveLeave/ApproveLeaveHandler.php

declare(strict_types=1);

namespace App\Leave\Application\Command\ApproveLeave;

use App\Leave\Domain\Entity\LeaveAuditLog;
use App\Leave\Domain\Exception\InsufficientLeaveBalanceException;
use App\Leave\Domain\Exception\LeaveRequestNotFoundException;
use App\Leave\Domain\Repository\LeaveRequestRepositoryInterface;
use App\Leave\Domain\Service\LeaveDebitService;
use App\Leave\Domain\ValueObject\LeaveStatus;
use App\Leave\Infrastructure\Notification\LeaveMailNotifier;
use App\Security\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Handler de la commande ApproveLeaveCommand.
 * Approuve la demande et crée une entrée d'audit.
 * Note : le débit du solde a déjà été effectué lors de la soumission.
 */
#[AsMessageHandler]
final class ApproveLeaveHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly EntityManagerInterface          $em,
        private readonly LeaveDebitService               $leaveDebitService,
        private readonly LeaveMailNotifier               $notifier,
    ) {
    }

    /**
     * Approuve une demande de congé en attente.
     *
     * @throws LeaveRequestNotFoundException si la demande n'existe pas
     * @throws \DomainException              si la transition de statut est invalide
     */
    public function __invoke(ApproveLeaveCommand $command): void
    {
        $leaveRequest = $this->leaveRequestRepository->findById($command->leaveRequestId);

        if ($leaveRequest === null) {
            throw new LeaveRequestNotFoundException($command->leaveRequestId);
        }

        $previousStatus = $leaveRequest->getStatut();

        $user = $this->em->getRepository(User::class)->find($leaveRequest->getUserId());

        if ($user === null) {
            throw new \DomainException('Utilisateur associé introuvable.');
        }

        $balance = match($leaveRequest->getType()) {
            \App\Leave\Domain\ValueObject\LeaveType::RTT       => $user->getRttBalance(),
            \App\Leave\Domain\ValueObject\LeaveType::HEURE_SUP => $user->getHeureSupBalance(),
            default                                             => $user->getLeaveBalance(),
        };

        if (!$balance->isSufficientFor($leaveRequest->getHeures())) {
            throw new InsufficientLeaveBalanceException($balance, $leaveRequest->getHeures());
        }

        $leaveRequest->approve();
        $this->leaveDebitService->debit($user, $leaveRequest->getHeures(), $leaveRequest->getType());
        $this->leaveRequestRepository->save($leaveRequest);

        // Audit trail
        $auditLog = new LeaveAuditLog(
            id: Uuid::v4()->toRfc4122(),
            leaveRequestId: $command->leaveRequestId,
            ancienStatut: $previousStatus,
            nouveauStatut: LeaveStatus::APPROVED,
            commentaire: $command->commentaire ?? 'Demande approuvée par le responsable RH.',
            auteurId: $command->approvedByUserId
        );

        $this->leaveRequestRepository->saveAuditLog($auditLog);

        try {
            $this->notifier->notifyApproved($user, $leaveRequest);
        } catch (\Throwable) {
            // L'email est non bloquant
        }
    }
}
