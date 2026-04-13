<?php

// src/Shared/Domain/Bus/QueryBusInterface.php

declare(strict_types=1);

namespace App\Shared\Domain\Bus;

/**
 * Interface du bus de requêtes (CQRS).
 * Dispatch une requête et retourne un résultat.
 */
interface QueryBusInterface
{
    /**
     * Dispatch une requête vers son handler et retourne le résultat.
     *
     * @param object $query La requête à dispatcher
     *
     * @return mixed Le résultat de la requête
     */
    public function ask(object $query): mixed;
}
