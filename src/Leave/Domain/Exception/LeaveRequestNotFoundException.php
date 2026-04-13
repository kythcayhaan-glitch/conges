<?php

// src/Leave/Domain/Exception/LeaveRequestNotFoundException.php

declare(strict_types=1);

namespace App\Leave\Domain\Exception;

/**
 * Exception levée lorsqu'une demande de congé est introuvable.
 */
final class LeaveRequestNotFoundException extends \DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(
            sprintf('Demande de congé introuvable avec l\'identifiant "%s".', $id),
            404
        );
    }
}
