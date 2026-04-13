<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Leave\Domain\ValueObject\LeaveHours;

final readonly class LeaveBalance
{
    public function __construct(private float $value)
    {
        if (!is_finite($value)) {
            throw new \InvalidArgumentException('LeaveBalance doit être une valeur numérique finie.');
        }
        if ($value < 0.0) {
            throw new \InvalidArgumentException(
                sprintf('LeaveBalance ne peut pas être négatif, %.2f fourni.', $value)
            );
        }
    }

    public static function zero(): self { return new self(0.0); }

    public function getValue(): float { return $this->value; }

    public function debit(LeaveHours $hours): self
    {
        $newValue = $this->value - $hours->getValue();
        if ($newValue < 0.0) {
            throw new \DomainException(sprintf(
                'Solde insuffisant : %.2fh disponible, %.2fh demandée.',
                $this->value, $hours->getValue()
            ));
        }
        return new self($newValue);
    }

    public function credit(LeaveHours $hours): self
    {
        return new self($this->value + $hours->getValue());
    }

    public function isSufficientFor(LeaveHours $hours): bool
    {
        return $this->value >= $hours->getValue() - PHP_FLOAT_EPSILON;
    }

    public function __toString(): string
    {
        return number_format($this->value, 2) . 'h';
    }
}
