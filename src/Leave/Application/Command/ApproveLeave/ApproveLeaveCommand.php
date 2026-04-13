<?php

// src/Leave/Application/Command/ApproveLeave/ApproveLeaveCommand.php

declare(strict_types=1);

namespace App\Leave\Application\Command\ApproveLeave;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Commande pour approuver une demande de congé.
 * Réservée aux utilisateurs avec le rôle ROLE_RH.
 */
final class ApproveLeaveCommand
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'identifiant de la demande est obligatoire.')]
        #[Assert\Uuid(message: 'L\'identifiant de la demande doit être un UUID valide.')]
        public readonly string $leaveRequestId,

        #[Assert\NotBlank(message: 'L\'identifiant de l\'approbateur est obligatoire.')]
        #[Assert\Uuid(message: 'L\'identifiant de l\'approbateur doit être un UUID valide.')]
        public readonly string $approvedByUserId,

        public readonly ?string $commentaire = null,
    ) {
    }
}
