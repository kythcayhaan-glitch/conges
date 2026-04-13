<?php

// src/Leave/Domain/ValueObject/LeaveStatus.php

declare(strict_types=1);

namespace App\Leave\Domain\ValueObject;

/**
 * Enum représentant le statut d'une demande de congé.
 * Encapsule toutes les transitions valides (pattern State).
 */
enum LeaveStatus: string
{
    case PENDING        = 'PENDING';
    case VALIDATED_CHEF = 'VALIDATED_CHEF';
    case APPROVED       = 'APPROVED';
    case REJECTED       = 'REJECTED';
    case CANCELLED      = 'CANCELLED';

    /**
     * Vérifie si la transition vers un nouveau statut est autorisée.
     */
    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions(), true);
    }

    /**
     * Retourne la liste des statuts vers lesquels on peut transitionner.
     *
     * @return self[]
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING        => [self::VALIDATED_CHEF, self::APPROVED, self::REJECTED, self::CANCELLED],
            self::VALIDATED_CHEF => [self::APPROVED, self::REJECTED, self::CANCELLED],
            self::APPROVED       => [self::CANCELLED],
            self::REJECTED       => [],
            self::CANCELLED      => [],
        };
    }

    /**
     * Indique si ce statut est terminal (aucune transition possible).
     */
    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Indique si le congé dans ce statut bloque le solde de l'agent.
     * Seul APPROVED déduit réellement le solde de façon définitive.
     */
    public function deductsBalance(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * Libellé lisible en français.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING        => 'En attente',
            self::VALIDATED_CHEF => 'Validée chef',
            self::APPROVED       => 'Approuvée',
            self::REJECTED       => 'Rejetée',
            self::CANCELLED      => 'Annulée',
        };
    }
}
