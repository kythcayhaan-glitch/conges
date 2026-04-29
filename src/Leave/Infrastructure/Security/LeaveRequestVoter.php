<?php

declare(strict_types=1);

namespace App\Leave\Infrastructure\Security;

use App\Leave\Domain\Entity\LeaveRequest;
use App\Security\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class LeaveRequestVoter extends Voter
{
    public const LEAVE_VIEW          = 'LEAVE_VIEW';
    public const LEAVE_CANCEL        = 'LEAVE_CANCEL';
    public const LEAVE_APPROVE       = 'LEAVE_APPROVE';
    public const LEAVE_REJECT        = 'LEAVE_REJECT';
    public const LEAVE_VALIDATE_CHEF = 'LEAVE_VALIDATE_CHEF';

    private const SUPPORTED_ATTRIBUTES = [
        self::LEAVE_VIEW,
        self::LEAVE_CANCEL,
        self::LEAVE_APPROVE,
        self::LEAVE_REJECT,
        self::LEAVE_VALIDATE_CHEF,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::SUPPORTED_ATTRIBUTES, true)
            && $subject instanceof LeaveRequest;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return false;
        }

        /** @var LeaveRequest $leave */
        $leave  = $subject;
        $roles  = $user->getRoles();
        $isRh    = in_array('ROLE_RH', $roles, true);
        $isAdmin = in_array('ROLE_ADMIN', $roles, true);
        $isChef  = in_array('ROLE_CHEF_SERVICE', $roles, true);

        return match ($attribute) {
            self::LEAVE_VIEW          => $this->canView($leave, $user, $isRh, $isAdmin, $isChef),
            self::LEAVE_CANCEL        => $this->canCancel($leave, $user, $isRh, $isAdmin, $isChef),
            self::LEAVE_APPROVE       => $this->canApprove($leave, $isRh, $isAdmin),
            self::LEAVE_REJECT        => $this->canReject($leave, $user, $isRh, $isAdmin, $isChef),
            self::LEAVE_VALIDATE_CHEF => $this->canValidateChef($leave, $user, $isAdmin, $isChef),
            default                   => false,
        };
    }

    private function canView(LeaveRequest $leave, UserInterface $user, bool $isRh, bool $isAdmin, bool $isChef): bool
    {
        if ($isRh || $isAdmin) {
            return true;
        }
        if ($this->isOwner($leave, $user)) {
            return true;
        }
        if ($this->isSameService($leave, $user)) {
            return true;
        }
        return false;
    }

    private function canCancel(LeaveRequest $leave, UserInterface $user, bool $isRh, bool $isAdmin, bool $isChef): bool
    {
        if ($isAdmin) {
            return !$leave->isRejected() && !$leave->isCancelled();
        }
        // Demande validée (chef ou RH) : seul l'admin peut annuler
        if ($leave->isValidatedByChef() || $leave->isApproved()) {
            return false;
        }
        if ($isRh) {
            return $leave->isPending();
        }
        if ($isChef && $this->isSameService($leave, $user)) {
            return $leave->isPending();
        }
        // Propriétaire : seulement PENDING
        return $this->isOwner($leave, $user) && $leave->isPending();
    }

    private function canApprove(LeaveRequest $leave, bool $isRh, bool $isAdmin): bool
    {
        if ($isAdmin) {
            // Admin peut approuver depuis PENDING ou VALIDATED_CHEF
            return $leave->isPending() || $leave->isValidatedByChef();
        }
        if ($isRh) {
            // RH approuve uniquement depuis VALIDATED_CHEF
            return $leave->isValidatedByChef();
        }
        return false;
    }

    private function canReject(LeaveRequest $leave, UserInterface $user, bool $isRh, bool $isAdmin, bool $isChef): bool
    {
        if ($isAdmin) {
            return $leave->isPending() || $leave->isValidatedByChef();
        }
        if ($isRh) {
            return $leave->isPending() || $leave->isValidatedByChef();
        }
        if ($isChef && $this->isSameService($leave, $user)) {
            // Chef peut rejeter uniquement depuis PENDING
            return $leave->isPending();
        }
        return false;
    }

    private function canValidateChef(LeaveRequest $leave, UserInterface $user, bool $isAdmin, bool $isChef): bool
    {
        if ($isAdmin) {
            return $leave->isPending();
        }
        if ($isChef && $this->isSameService($leave, $user)) {
            return $leave->isPending();
        }
        return false;
    }

    private function isOwner(LeaveRequest $leave, UserInterface $user): bool
    {
        return $user instanceof User && $leave->getUserId() === $user->getId();
    }

    private function isSameService(LeaveRequest $leave, UserInterface $viewer): bool
    {
        if (!$viewer instanceof User || empty($viewer->getServiceNumbers())) {
            return false;
        }

        $agent = $this->em->getRepository(User::class)->find($leave->getUserId());
        if (!$agent instanceof User || empty($agent->getServiceNumbers())) {
            return false;
        }

        // Même service direct
        if (count(array_intersect($viewer->getServiceNumbers(), $agent->getServiceNumbers())) > 0) {
            return true;
        }

        // Via chef : un chef gère à la fois le service du viewer et celui de l'agent
        $allUsers = $this->em->getRepository(User::class)->findAll();
        foreach ($allUsers as $u) {
            if (!$u instanceof User || !in_array('ROLE_CHEF_SERVICE', $u->getRoles(), true)) {
                continue;
            }
            $chefServices = $u->getServiceNumbers();
            if (count(array_intersect($viewer->getServiceNumbers(), $chefServices)) > 0
                && count(array_intersect($agent->getServiceNumbers(), $chefServices)) > 0
            ) {
                return true;
            }
        }

        return false;
    }
}
