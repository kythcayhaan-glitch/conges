<?php

declare(strict_types=1);

namespace App\Leave\Application\Command\CancelLeave;

use App\Leave\Domain\Entity\LeaveAuditLog;
use App\Leave\Domain\Exception\LeaveRequestNotFoundException;
use App\Leave\Domain\Repository\LeaveRequestRepositoryInterface;
use App\Leave\Domain\Service\LeaveDebitService;
use App\Leave\Domain\ValueObject\LeaveStatus;
use App\Leave\Infrastructure\Notification\LeaveMailNotifier;
use App\Security\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class CancelLeaveHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly EntityManagerInterface          $em,
        private readonly LeaveDebitService               $leaveDebitService,
        private readonly LeaveMailNotifier               $notifier,
    ) {
    }

    public function __invoke(CancelLeaveCommand $command): void
    {
        $leaveRequest = $this->leaveRequestRepository->findById($command->leaveRequestId);

        if ($leaveRequest === null) {
            throw new LeaveRequestNotFoundException($command->leaveRequestId);
        }

        $previousStatus = $leaveRequest->getStatut();
        $wasApproved    = $leaveRequest->isApproved();

        $leaveRequest->cancel();

        // Créditer uniquement si la demande était approuvée (solde déjà débité)
        if ($wasApproved) {
            $user = $this->em->getRepository(User::class)->find($leaveRequest->getUserId());
            if ($user === null) {
                throw new \DomainException('Utilisateur associé introuvable.');
            }
            $this->leaveDebitService->credit($user, $leaveRequest->getHeures(), $leaveRequest->getType());
        }

        $this->leaveRequestRepository->save($leaveRequest);

        $this->leaveRequestRepository->saveAuditLog(new LeaveAuditLog(
            id:             Uuid::v4()->toRfc4122(),
            leaveRequestId: $command->leaveRequestId,
            ancienStatut:   $previousStatus,
            nouveauStatut:  LeaveStatus::CANCELLED,
            commentaire:    $command->commentaire ?? 'Demande annulée.',
            auteurId:       $command->cancelledByUserId,
        ));

        $notifUser = $this->em->getRepository(User::class)->find($leaveRequest->getUserId());
        if ($notifUser !== null) {
            try {
                $this->notifier->notifyCancelled($notifUser, $leaveRequest);
            } catch (\Throwable) {
                // L'email est non bloquant
            }
        }
    }
}
