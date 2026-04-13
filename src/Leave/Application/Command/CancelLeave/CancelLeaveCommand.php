<?php

// src/Leave/Application/Command/CancelLeave/CancelLeaveCommand.php

declare(strict_types=1);

namespace App\Leave\Application\Command\CancelLeave;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Commande pour annuler une demande de congé.
 * - Un agent peut annuler sa propre demande si elle est en statut PENDING.
 * - Seul ROLE_RH peut annuler une demande en statut APPROVED.
 */
final class CancelLeaveCommand
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'identifiant de la demande est obligatoire.')]
        #[Assert\Uuid(message: 'L\'identifiant de la demande doit être un UUID valide.')]
        public readonly string $leaveRequestId,

        #[Assert\NotBlank(message: 'L\'identifiant de l\'auteur est obligatoire.')]
        #[Assert\Uuid(message: 'L\'identifiant de l\'auteur doit être un UUID valide.')]
        public readonly string $cancelledByUserId,

        public readonly ?string $commentaire = null,
    ) {
    }
}
