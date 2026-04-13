<?php

declare(strict_types=1);

namespace App\Leave\Domain\Repository;

use App\Leave\Domain\Entity\LeaveAuditLog;
use App\Leave\Domain\Entity\LeaveRequest;
use App\Leave\Domain\ValueObject\LeaveStatus;

interface LeaveRequestRepositoryInterface
{
    public function save(LeaveRequest $leaveRequest): void;
    public function findById(string $id): ?LeaveRequest;

    /** @return LeaveRequest[] */
    public function findByUserId(string $userId): array;

    /** @return LeaveRequest[] */
    public function findByUserIdAndStatus(string $userId, LeaveStatus $status): array;

    /** @return LeaveRequest[] */
    public function findAll(): array;

    /** @return LeaveRequest[] */
    public function findAllPending(): array;

    /** @return LeaveRequest[] */
    public function findAllValidatedByChef(): array;

    /**
     * @param  string[]       $userIds
     * @return LeaveRequest[]
     */
    public function findByUserIds(array $userIds): array;

    /**
     * @param  string[]       $userIds
     * @return LeaveRequest[]
     */
    public function findPendingByUserIds(array $userIds): array;

    public function delete(LeaveRequest $leaveRequest): void;

    public function deleteAllByUserId(string $userId): void;

    public function saveAuditLog(LeaveAuditLog $log): void;

    /** @return LeaveAuditLog[] */
    public function findAuditLogsByLeaveRequestId(string $leaveRequestId): array;

    /**
     * @param  string[]       $leaveRequestIds
     * @return LeaveAuditLog[]  indexés par leaveRequestId
     */
    public function findLastAuditLogsByLeaveRequestIds(array $leaveRequestIds): array;
}
