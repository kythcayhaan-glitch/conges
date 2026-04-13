<?php

// src/Shared/Domain/Bus/CommandBusInterface.php

declare(strict_types=1);

namespace App\Shared\Domain\Bus;

/**
 * Interface du bus de commandes (CQRS).
 * Dispatch une commande sans retourner de résultat (fire-and-forget).
 */
interface CommandBusInterface
{
    /**
     * Dispatch une commande vers son handler.
     * Retourne la valeur éventuellement retournée par le handler (ex : UUID créé).
     *
     * @param object $command La commande à dispatcher
     */
    public function dispatch(object $command): mixed;
}
