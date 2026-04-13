<?php

declare(strict_types=1);

namespace App\Leave\Application\Command\EditLeave;

use App\Leave\Domain\Entity\LeaveAuditLog;
use App\Leave\Application\Command\SubmitLeave\SubmitLeaveHandler;
use App\Leave\Domain\Exception\LeaveRequestNotFoundException;
use App\Leave\Domain\Repository\LeaveRequestRepositoryInterface;
use App\Leave\Domain\ValueObject\LeaveHours;
use App\Leave\Domain\ValueObject\LeavePeriod;
use App\Leave\Domain\ValueObject\LeaveStatus;
use App\Leave\Domain\ValueObject\LeaveType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class EditLeaveHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
    ) {
    }

    public function __invoke(EditLeaveCommand $command): void
    {
        $leaveRequest = $this->leaveRequestRepository->findById($command->leaveRequestId);

        if ($leaveRequest === null) {
            throw new LeaveRequestNotFoundException($command->leaveRequestId);
        }

        if (!$leaveRequest->isPending()) {
            throw new \DomainException('Seules les demandes en attente peuvent être modifiées.');
        }

        $period   = LeavePeriod::fromStrings($command->dateDebut, $command->dateFin);
        $oldHours = $leaveRequest->getHeures();

        if ($leaveRequest->getType() === LeaveType::RTT) {
            $days = SubmitLeaveHandler::computeWorkingDays($command->dateDebut, $command->dateFin);
            if ($days <= 0) {
                throw new \DomainException('La période ne contient aucun jour ouvré (lundi–vendredi).');
            }
            $newHours = new LeaveHours($days);
        } elseif ($command->journeeEntiere || $command->matin || $command->apresMidi) {
            $newHours = new LeaveHours(SubmitLeaveHandler::computeCreneauHours(
                $command->dateDebut, $command->matin, $command->apresMidi, $command->journeeEntiere
            ));
        } else {
            if (!$command->heureDebut || !$command->heureFin) {
                throw new \DomainException('Veuillez renseigner les heures de début et de fin ou sélectionner un créneau.');
            }
            $hours = SubmitLeaveHandler::computeWorkingHours(
                $command->dateDebut, $command->heureDebut,
                $command->dateFin,   $command->heureFin,
            );
            if ($hours <= 0) {
                throw new \DomainException('L\'heure de fin doit être postérieure à l\'heure de début.');
            }
            $newHours = new LeaveHours($hours);
        }

        // Le type ne change pas lors d'une édition
        $leaveRequest->edit($newHours, $period, $command->motif, $command->heureDebut, $command->heureFin, $command->matin, $command->apresMidi, $command->journeeEntiere);
        $this->leaveRequestRepository->save($leaveRequest);

        $this->leaveRequestRepository->saveAuditLog(new LeaveAuditLog(
            id:             Uuid::v4()->toRfc4122(),
            leaveRequestId: $command->leaveRequestId,
            ancienStatut:   LeaveStatus::PENDING,
            nouveauStatut:  LeaveStatus::PENDING,
            commentaire:    sprintf('Demande modifiée (%.2f h → %.2f h).', $oldHours->getValue(), $newHours->getValue()),
            auteurId:       $command->editedByUserId,
        ));
    }
}
