<?php

// src/Leave/Domain/Exception/InsufficientLeaveBalanceException.php

declare(strict_types=1);

namespace App\Leave\Domain\Exception;

use App\Shared\Domain\ValueObject\LeaveBalance;
use App\Leave\Domain\ValueObject\LeaveHours;

/**
 * Exception levée lorsqu'un agent tente de poser plus d'heures qu'il n'en dispose.
 */
final class InsufficientLeaveBalanceException extends \DomainException
{
    public function __construct(LeaveBalance $balance, LeaveHours $requested)
    {
        parent::__construct(
            sprintf(
                'Solde insuffisant : %.2fh disponible, %.2fh demandée.',
                $balance->getValue(),
                $requested->getValue()
            ),
            422
        );
    }
}
