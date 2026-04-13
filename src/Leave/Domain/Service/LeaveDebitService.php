<?php

declare(strict_types=1);

namespace App\Leave\Domain\Service;

use App\Leave\Domain\Exception\InsufficientLeaveBalanceException;
use App\Leave\Domain\ValueObject\LeaveHours;
use App\Leave\Domain\ValueObject\LeaveType;
use App\Security\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class LeaveDebitService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function debit(User $user, LeaveHours $hours, LeaveType $type = LeaveType::CONGE): void
    {
        $current = match($type) {
            LeaveType::RTT       => $user->getRttBalance(),
            LeaveType::HEURE_SUP => $user->getHeureSupBalance(),
            default              => $user->getLeaveBalance(),
        };

        if (!$current->isSufficientFor($hours)) {
            throw new InsufficientLeaveBalanceException($current, $hours);
        }

        match($type) {
            LeaveType::RTT       => $user->applyRttBalance($current->debit($hours)),
            LeaveType::HEURE_SUP => $user->applyHeureSupBalance($current->debit($hours)),
            default              => $user->applyLeaveBalance($current->debit($hours)),
        };

        $this->em->flush();
    }

    public function credit(User $user, LeaveHours $hours, LeaveType $type = LeaveType::CONGE): void
    {
        match($type) {
            LeaveType::RTT       => $user->applyRttBalance($user->getRttBalance()->credit($hours)),
            LeaveType::HEURE_SUP => $user->applyHeureSupBalance($user->getHeureSupBalance()->credit($hours)),
            default              => $user->applyLeaveBalance($user->getLeaveBalance()->credit($hours)),
        };

        $this->em->flush();
    }
}
