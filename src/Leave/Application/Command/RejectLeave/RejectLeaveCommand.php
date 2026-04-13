<?php

// src/Leave/Application/Command/RejectLeave/RejectLeaveCommand.php

declare(strict_types=1);

namespace App\Leave\Application\Command\RejectLeave;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Commande pour rejeter une demande de congé.
 * Réservée aux utilisateurs avec le rôle ROLE_RH.
 */
final class RejectLeaveCommand
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'identifiant de la demande est obligatoire.')]
        #[Assert\Uuid(message: 'L\'identifiant de la demande doit être un UUID valide.')]
        public readonly string $leaveRequestId,

        #[Assert\NotBlank(message: 'L\'identifiant du responsable est obligatoire.')]
        #[Assert\Uuid(message: 'L\'identifiant du responsable doit être un UUID valide.')]
        public readonly string $rejectedByUserId,

        #[Assert\Length(
            max: 500,
            maxMessage: 'Le motif ne peut pas dépasser {{ limit }} caractères.'
        )]
        public readonly ?string $commentaire,
    ) {
    }
}
