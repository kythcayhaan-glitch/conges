<?php

// src/Shared/Domain/ValueObject/Uuid.php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use Symfony\Component\Uid\Uuid as SymfonyUuid;

/**
 * Value Object abstrait représentant un identifiant UUID v4.
 * Chaque domaine étend cette classe pour créer son propre type d'identifiant.
 */
abstract readonly class Uuid
{
    private string $value;

    /**
     * @throws \InvalidArgumentException si la valeur n'est pas un UUID valide
     */
    public function __construct(string $value)
    {
        if (!SymfonyUuid::isValid($value)) {
            throw new \InvalidArgumentException(
                sprintf('"%s" n\'est pas un UUID valide.', $value)
            );
        }

        $this->value = strtolower($value);
    }

    /**
     * Génère un nouvel UUID v4 et retourne une instance du type concret.
     */
    public static function generate(): static
    {
        return new static(SymfonyUuid::v4()->toRfc4122());
    }

    /**
     * Retourne la représentation string de l'UUID.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Vérifie l'égalité avec un autre UUID.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
