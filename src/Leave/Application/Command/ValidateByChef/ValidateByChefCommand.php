<?php

declare(strict_types=1);

namespace App\Leave\Application\Command\ValidateByChef;

final readonly class ValidateByChefCommand
{
    public function __construct(
        public string  $leaveRequestId,
        public string  $validatedByUserId,
        public ?string $commentaire = null,
    ) {
    }
}
