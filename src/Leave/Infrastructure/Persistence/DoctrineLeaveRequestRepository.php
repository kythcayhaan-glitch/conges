<?php

declare(strict_types=1);

namespace App\Leave\Infrastructure\Persistence;

use App\Leave\Domain\Entity\LeaveAuditLog;
use App\Leave\Domain\Entity\LeaveRequest;
use App\Leave\Domain\Repository\LeaveRequestRepositoryInterface;
use App\Leave\Domain\ValueObject\LeaveStatus;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineLeaveRequestRepository implements LeaveRequestRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function save(LeaveRequest $leaveRequest): void
    {
        $this->entityManager->persist($leaveRequest);
        $this->entityManager->flush();
    }

    public function findById(string $id): ?LeaveRequest
    {
        return $this->entityManager->getRepository(LeaveRequest::class)->find($id);
    }

    /** @return LeaveRequest[] */
    public function findByUserId(string $userId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('lr')->from(LeaveRequest::class, 'lr')
            ->where('lr.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('lr.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    /** @return LeaveRequest[] */
    public function findByUserIdAndStatus(string $userId, LeaveStatus $status): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('lr')->from(LeaveRequest::class, 'lr')
            ->where('lr.userId = :userId')
            ->andWhere('lr.statut = :statut')
            ->setParameter('userId', $userId)
            ->setParameter('statut', $status)
            ->orderBy('lr.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    /** @return LeaveRequest[] */
    public function findAll(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('lr')->from(LeaveRequest::class, 'lr')
            ->orderBy('lr.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    /** @return LeaveRequest[] */
    public function findAllPending(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('lr')->from(LeaveRequest::class, 'lr')
            ->where('lr.statut = :statut')
            ->setParameter('statut', LeaveStatus::PENDING)
            ->orderBy('lr.createdAt', 'ASC')
            ->getQuery()->getResult();
    }

    public function delete(LeaveRequest $leaveRequest): void
    {
        $logs = $this->entityManager->createQueryBuilder()
            ->select('al')->from(LeaveAuditLog::class, 'al')
            ->where('al.leaveRequestId = :id')
            ->setParameter('id', $leaveRequest->getId())
            ->getQuery()->getResult();

        foreach ($logs as $log) {
            $this->entityManager->remove($log);
        }

        $this->entityManager->remove($leaveRequest);
        $this->entityManager->flush();
    }

    public function deleteAllByUserId(string $userId): void
    {
        $leaves = $this->findByUserId($userId);

        foreach ($leaves as $leave) {
            $logs = $this->entityManager->createQueryBuilder()
                ->select('al')->from(LeaveAuditLog::class, 'al')
                ->where('al.leaveRequestId = :id')
                ->setParameter('id', $leave->getId())
                ->getQuery()->getResult();

            foreach ($logs as $log) {
                $this->entityManager->remove($log);
            }
            $this->entityManager->remove($leave);
        }

        $this->entityManager->flush();
    }

    public function saveAuditLog(LeaveAuditLog $log): void
    {
        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    /** @return LeaveAuditLog[] indexés par leaveRequestId */
    public function findLastAuditLogsByLeaveRequestIds(array $leaveRequestIds): array
    {
        if (empty($leaveRequestIds)) {
            return [];
        }

        $logs = $this->entityManager->createQueryBuilder()
            ->select('al')->from(LeaveAuditLog::class, 'al')
            ->where('al.leaveRequestId IN (:ids)')
            ->setParameter('ids', $leaveRequestIds)
            ->orderBy('al.createdAt', 'DESC')
            ->getQuery()->getResult();

        $index = [];
        foreach ($logs as $log) {
            if (!isset($index[$log->getLeaveRequestId()])) {
                $index[$log->getLeaveRequestId()] = $log;
            }
        }
        return $index;
    }

    /** @return LeaveAuditLog[] */
    public function findAuditLogsByLeaveRequestId(string $leaveRequestId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('al')->from(LeaveAuditLog::class, 'al')
            ->where('al.leaveRequestId = :leaveRequestId')
            ->setParameter('leaveRequestId', $leaveRequestId)
            ->orderBy('al.createdAt', 'ASC')
            ->getQuery()->getResult();
    }
}
