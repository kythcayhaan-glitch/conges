<?php

declare(strict_types=1);

namespace App\Leave\Application\Command\SubmitLeave;

use App\Leave\Domain\Entity\LeaveAuditLog;
use App\Leave\Domain\Entity\LeaveRequest;
use App\Leave\Domain\Repository\LeaveRequestRepositoryInterface;
use App\Leave\Domain\ValueObject\LeaveHours;
use App\Leave\Domain\ValueObject\LeavePeriod;
use App\Leave\Domain\ValueObject\LeaveStatus;
use App\Leave\Domain\ValueObject\LeaveType;
use App\Security\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class SubmitLeaveHandler
{
    public function __construct(
        private readonly EntityManagerInterface          $em,
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
    ) {
    }

    public function __invoke(SubmitLeaveCommand $command): string
    {
        $user = $this->em->getRepository(User::class)->find($command->agentId);

        if ($user === null) {
            throw new \DomainException(sprintf('Utilisateur introuvable : "%s".', $command->agentId));
        }

        $period = LeavePeriod::fromStrings($command->dateDebut, $command->dateFin);

        if ($command->type === LeaveType::RTT) {
            $days = self::computeWorkingDays($command->dateDebut, $command->dateFin);
            if ($days <= 0) {
                throw new \DomainException('La période ne contient aucun jour ouvré (lundi–vendredi).');
            }
            $leaveHours = new LeaveHours($days);
        } elseif ($command->journeeEntiere || $command->matin || $command->apresMidi) {
            $leaveHours = new LeaveHours(self::computeCreneauHours(
                $command->dateDebut, $command->matin, $command->apresMidi, $command->journeeEntiere
            ));
        } else {
            if (!$command->heureDebut || !$command->heureFin) {
                throw new \DomainException('Veuillez renseigner les heures de début et de fin ou sélectionner un créneau.');
            }
            $hours = self::computeWorkingHours(
                $command->dateDebut, $command->heureDebut,
                $command->dateFin,   $command->heureFin,
            );
            if ($hours <= 0) {
                throw new \DomainException('L\'heure de fin doit être postérieure à l\'heure de début.');
            }
            $leaveHours = new LeaveHours($hours);
        }

        $leaveRequestId = Uuid::v4()->toRfc4122();

        $leaveRequest = new LeaveRequest(
            id:         $leaveRequestId,
            userId:     $user->getId(),
            heures:     $leaveHours,
            period:     $period,
            motif:      $command->motif,
            heureDebut: $command->heureDebut,
            heureFin:   $command->heureFin,
            matin:          $command->matin,
            apresMidi:      $command->apresMidi,
            journeeEntiere: $command->journeeEntiere,
            type:           $command->type,
        );

        $this->leaveRequestRepository->save($leaveRequest);

        $this->leaveRequestRepository->saveAuditLog(new LeaveAuditLog(
            id:             Uuid::v4()->toRfc4122(),
            leaveRequestId: $leaveRequestId,
            ancienStatut:   null,
            nouveauStatut:  LeaveStatus::PENDING,
            commentaire:    'Demande soumise.',
            auteurId:       $user->getId(),
        ));

        return $leaveRequestId;
    }

    public static function computeWorkingDays(string $dateDebut, string $dateFin): float
    {
        $current = new \DateTimeImmutable($dateDebut);
        $end     = new \DateTimeImmutable($dateFin);
        $days    = 0;
        while ($current->format('Y-m-d') <= $end->format('Y-m-d')) {
            if ((int) $current->format('N') <= 5) { // lundi=1 … vendredi=5
                $days++;
            }
            $current = $current->modify('+1 day');
        }
        return (float) $days;
    }

    public static function computeCreneauHours(string $date, bool $matin, bool $apresMidi, bool $journeeEntiere = false): float
    {
        $dayOfWeek = (int) (new \DateTimeImmutable($date))->format('N'); // 1=lundi
        if ($journeeEntiere) {
            return $dayOfWeek === 1 ? 8.0 : 7.75;
        }
        $hours = 0.0;
        if ($matin)     $hours += 4.0;
        if ($apresMidi) $hours += ($dayOfWeek === 1 ? 4.0 : 3.75);
        return $hours;
    }

    /**
     * Calcule les heures de travail réelles entre deux dates/heures en tenant compte
     * des horaires : 8h-12h et 13h45-17h30 (13h30-17h30 le lundi).
     */
    public static function computeWorkingHours(
        string $dateDebut, string $heureDebut,
        string $dateFin,   string $heureFin,
    ): float {
        $start = new \DateTimeImmutable($dateDebut . ' ' . $heureDebut);
        $end   = new \DateTimeImmutable($dateFin   . ' ' . $heureFin);

        if ($end->getTimestamp() <= $start->getTimestamp()) {
            return 0.0;
        }

        $total   = 0.0;
        $current = new \DateTimeImmutable($dateDebut);
        $endDate = new \DateTimeImmutable($dateFin);

        while ($current->format('Y-m-d') <= $endDate->format('Y-m-d')) {
            $dayStr   = $current->format('Y-m-d');
            $isMonday = (int) $current->format('N') === 1;
            $lunchEnd = $isMonday ? '13:30' : '13:45';

            $morningStart   = new \DateTimeImmutable($dayStr . ' 08:00');
            $morningEnd     = new \DateTimeImmutable($dayStr . ' 12:00');
            $afternoonStart = new \DateTimeImmutable($dayStr . ' ' . $lunchEnd);
            $afternoonEnd   = new \DateTimeImmutable($dayStr . ' 17:30');

            $dayStart = $dayStr === $dateDebut ? $start : $morningStart;
            $dayEnd   = $dayStr === $dateFin   ? $end   : $afternoonEnd;

            // Intersection avec le matin
            $mStart = $dayStart > $morningStart ? $dayStart : $morningStart;
            $mEnd   = $dayEnd   < $morningEnd   ? $dayEnd   : $morningEnd;
            if ($mEnd->getTimestamp() > $mStart->getTimestamp()) {
                $total += ($mEnd->getTimestamp() - $mStart->getTimestamp()) / 3600;
            }

            // Intersection avec l'après-midi
            $aStart = $dayStart > $afternoonStart ? $dayStart : $afternoonStart;
            $aEnd   = $dayEnd   < $afternoonEnd   ? $dayEnd   : $afternoonEnd;
            if ($aEnd->getTimestamp() > $aStart->getTimestamp()) {
                $total += ($aEnd->getTimestamp() - $aStart->getTimestamp()) / 3600;
            }

            $current = $current->modify('+1 day');
        }

        return round($total, 2);
    }
}
