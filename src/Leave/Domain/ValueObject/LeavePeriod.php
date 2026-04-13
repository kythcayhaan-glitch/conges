<?php

// src/Leave/Domain/ValueObject/LeavePeriod.php

declare(strict_types=1);

namespace App\Leave\Domain\ValueObject;

/**
 * Value Object immuable représentant une période de congé (date début / date fin).
 * La date de début peut être égale à la date de fin (congé sur une journée).
 */
final readonly class LeavePeriod
{
    /**
     * @throws \InvalidArgumentException si start > end
     */
    public function __construct(
        private \DateTimeImmutable $start,
        private \DateTimeImmutable $end
    ) {
        if ($start > $end) {
            throw new \InvalidArgumentException(
                sprintf(
                    'La date de début (%s) ne peut pas être postérieure à la date de fin (%s).',
                    $start->format('Y-m-d'),
                    $end->format('Y-m-d')
                )
            );
        }
    }

    /**
     * Crée une LeavePeriod depuis des chaînes de dates ISO.
     *
     * @throws \InvalidArgumentException si les formats sont invalides ou si start > end
     */
    public static function fromStrings(string $start, string $end): self
    {
        $startDate = \DateTimeImmutable::createFromFormat('Y-m-d', $start);
        $endDate   = \DateTimeImmutable::createFromFormat('Y-m-d', $end);

        if ($startDate === false) {
            throw new \InvalidArgumentException(
                sprintf('Format de date de début invalide : "%s". Format attendu : Y-m-d.', $start)
            );
        }

        if ($endDate === false) {
            throw new \InvalidArgumentException(
                sprintf('Format de date de fin invalide : "%s". Format attendu : Y-m-d.', $end)
            );
        }

        return new self(
            $startDate->setTime(0, 0),
            $endDate->setTime(0, 0)
        );
    }

    /**
     * Retourne la date de début de la période.
     */
    public function getStart(): \DateTimeImmutable
    {
        return $this->start;
    }

    /**
     * Retourne la date de fin de la période.
     */
    public function getEnd(): \DateTimeImmutable
    {
        return $this->end;
    }

    /**
     * Calcule le nombre de jours calendaires de la période.
     */
    public function durationInDays(): int
    {
        return (int) $this->start->diff($this->end)->days;
    }

    /**
     * Vérifie si cette période chevauche une autre période.
     */
    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $this->end > $other->start;
    }

    /**
     * Vérifie l'égalité avec une autre LeavePeriod.
     */
    public function equals(self $other): bool
    {
        return $this->start == $other->start && $this->end == $other->end;
    }
}
