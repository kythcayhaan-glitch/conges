<?php

declare(strict_types=1);

namespace App\Leave\Domain\ValueObject;

enum LeaveType: string
{
    case CONGE    = 'CONGE';
    case RTT      = 'RTT';
    case HEURE_SUP = 'HEURE_SUP';

    public function label(): string
    {
        return match($this) {
            self::CONGE     => 'Congé',
            self::RTT       => 'RTT',
            self::HEURE_SUP => 'Heures sup.',
        };
    }
}
