<?php

// src/Leave/Domain/ValueObject/LeaveHours.php

declare(strict_types=1);

namespace App\Leave\Domain\ValueObject;

/**
 * Value Object immuable représentant un nombre d'heures de congé.
 * Toujours strictement positif.
 */
final readonly class LeaveHours
{
    /**
     * @throws \InvalidArgumentException si la valeur est <= 0 ou non finie
     */
    public function __construct(
        private float $value
    ) {
        if (!is_finite($value)) {
            throw new \InvalidArgumentException(
                'LeaveHours doit être une valeur numérique finie.'
            );
        }

        if ($value <= 0.0) {
            throw new \InvalidArgumentException(
                sprintf('LeaveHours doit être strictement positif, %.2f fourni.', $value)
            );
        }
    }

    /**
     * Retourne la valeur décimale des heures.
     */
    public function getValue(): float
    {
        return $this->value;
    }

    /**
     * Vérifie l'égalité avec un autre LeaveHours.
     */
    public function equals(self $other): bool
    {
        return abs($this->value - $other->value) < PHP_FLOAT_EPSILON;
    }

    /**
     * Vérifie si ce nombre d'heures est inférieur ou égal à un autre.
     */
    public function isLessThanOrEqualTo(self $other): bool
    {
        return $this->value <= $other->value + PHP_FLOAT_EPSILON;
    }

    public function __toString(): string
    {
        return number_format($this->value, 2) . 'h';
    }
}
