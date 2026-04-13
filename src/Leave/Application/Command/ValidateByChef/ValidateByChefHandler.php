<?php

declare(strict_types=1);

namespace App\Leave\Application\Command\ValidateByChef;

use App\Leave\Domain\Entity\LeaveAuditLog;
use App\Leave\Domain\Exception\LeaveRequestNotFoundException;
use App\Leave\Domain\Repository\LeaveRequestRepositoryInterface;
use App\Leave\Domain\ValueObject\LeaveStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class ValidateByChefHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly EntityManagerInterface          $em,
    ) {
    }

    public function __invoke(ValidateByChefCommand $command): void
    {
        $leaveRequest = $this->leaveRequestRepository->findById($command->leaveRequestId);

        if ($leaveRequest === null) {
            throw new LeaveRequestNotFoundException($command->leaveRequestId);
        }

        $previousStatus = $leaveRequest->getStatut();

        $leaveRequest->validateByChef();
        $this->leaveRequestRepository->save($leaveRequest);

        $this->leaveRequestRepository->saveAuditLog(new LeaveAuditLog(
            id:             Uuid::v4()->toRfc4122(),
            leaveRequestId: $command->leaveRequestId,
            ancienStatut:   $previousStatus,
            nouveauStatut:  LeaveStatus::VALIDATED_CHEF,
            commentaire:    $command->commentaire ?? 'Validée par le chef de service.',
            auteurId:       $command->validatedByUserId,
        ));
    }
}
