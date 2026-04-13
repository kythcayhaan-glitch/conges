<?php

// src/Leave/Application/Command/SubmitLeave/SubmitLeaveCommand.php

declare(strict_types=1);

namespace App\Leave\Application\Command\SubmitLeave;

use App\Leave\Domain\ValueObject\LeaveType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Commande pour soumettre une nouvelle demande de congé.
 * Portée par le CommandBus vers SubmitLeaveHandler.
 */
final class SubmitLeaveCommand
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'identifiant de l\'agent est obligatoire.')]
        #[Assert\Uuid(message: 'L\'identifiant de l\'agent doit être un UUID valide.')]
        public readonly string $agentId,

        #[Assert\NotBlank(message: 'La date de début est obligatoire.')]
        #[Assert\Date(message: 'La date de début doit être au format Y-m-d.')]
        public readonly string $dateDebut,

        #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/', message: 'L\'heure de début doit être au format HH:MM.')]
        public readonly ?string $heureDebut,

        #[Assert\NotBlank(message: 'La date de fin est obligatoire.')]
        #[Assert\Date(message: 'La date de fin doit être au format Y-m-d.')]
        public readonly string $dateFin,

        #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/', message: 'L\'heure de fin doit être au format HH:MM.')]
        public readonly ?string $heureFin,

        #[Assert\Length(max: 1000, maxMessage: 'Le motif ne peut pas dépasser {{ limit }} caractères.')]
        public readonly ?string $motif,

        public readonly bool $matin = false,
        public readonly bool $apresMidi = false,
        public readonly bool $journeeEntiere = false,
        public readonly LeaveType $type = LeaveType::CONGE,
    ) {
    }
}
