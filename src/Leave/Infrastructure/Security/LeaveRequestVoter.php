<?php

declare(strict_types=1);

namespace App\Leave\Infrastructure\Security;

use App\Leave\Domain\Entity\LeaveRequest;
use App\Security\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class LeaveRequestVoter extends Voter
{
    public const LEAVE_VIEW    = 'LEAVE_VIEW';
    public const LEAVE_CANCEL  = 'LEAVE_CANCEL';
    public const LEAVE_APPROVE = 'LEAVE_APPROVE';
    public const LEAVE_REJECT  = 'LEAVE_REJECT';

    private const SUPPORTED_ATTRIBUTES = [
        self::LEAVE_VIEW, self::LEAVE_CANCEL, self::LEAVE_APPROVE, self::LEAVE_REJECT,
    ];

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

        /** @var LeaveRequest $leaveRequest */
        $leaveRequest = $subject;

        return match ($attribute) {
            self::LEAVE_VIEW    => in_array('ROLE_RH', $user->getRoles(), true) || $this->isOwner($leaveRequest, $user),
            self::LEAVE_CANCEL  => in_array('ROLE_RH', $user->getRoles(), true)
                ? ($leaveRequest->isPending() || $leaveRequest->isApproved())
                : ($this->isOwner($leaveRequest, $user) && $leaveRequest->isPending()),
            self::LEAVE_APPROVE => in_array('ROLE_RH', $user->getRoles(), true),
            self::LEAVE_REJECT  => in_array('ROLE_RH', $user->getRoles(), true),
            default             => false,
        };
    }

    private function isOwner(LeaveRequest $leaveRequest, UserInterface $user): bool
    {
        return $user instanceof User && $leaveRequest->getUserId() === $user->getId();
    }
}
