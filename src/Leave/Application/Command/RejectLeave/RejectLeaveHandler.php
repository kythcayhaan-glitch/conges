<?php

declare(strict_types=1);

namespace App\Leave\Application\Command\RejectLeave;

use App\Leave\Domain\Entity\LeaveAuditLog;
use App\Leave\Domain\Exception\LeaveRequestNotFoundException;
use App\Leave\Domain\Repository\LeaveRequestRepositoryInterface;
use App\Leave\Domain\ValueObject\LeaveStatus;
use App\Leave\Infrastructure\Notification\LeaveMailNotifier;
use App\Security\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class RejectLeaveHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly EntityManagerInterface          $em,
        private readonly LeaveMailNotifier               $notifier,
    ) {
    }

    public function __invoke(RejectLeaveCommand $command): void
    {
        $leaveRequest = $this->leaveRequestRepository->findById($command->leaveRequestId);

        if ($leaveRequest === null) {
            throw new LeaveRequestNotFoundException($command->leaveRequestId);
        }

        $previousStatus = $leaveRequest->getStatut();

        $leaveRequest->reject();
        $this->leaveRequestRepository->save($leaveRequest);

        $this->leaveRequestRepository->saveAuditLog(new LeaveAuditLog(
            id:             Uuid::v4()->toRfc4122(),
            leaveRequestId: $command->leaveRequestId,
            ancienStatut:   $previousStatus,
            nouveauStatut:  LeaveStatus::REJECTED,
            commentaire:    $command->commentaire,
            auteurId:       $command->rejectedByUserId,
        ));

        $user = $this->em->getRepository(User::class)->find($leaveRequest->getUserId());
        if ($user !== null) {
            try {
                $this->notifier->notifyRejected($user, $leaveRequest, $command->commentaire);
            } catch (\Throwable) {
                // L'email est non bloquant
            }
        }
    }
}
