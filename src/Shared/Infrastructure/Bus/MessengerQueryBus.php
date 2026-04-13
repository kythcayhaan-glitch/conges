<?php

// src/Shared/Infrastructure/Bus/MessengerQueryBus.php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Domain\Bus\QueryBusInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Implémentation du QueryBus via Symfony Messenger.
 * Route chaque requête vers son handler et retourne le résultat.
 */
final class MessengerQueryBus implements QueryBusInterface
{
    public function __construct(
        private readonly MessageBusInterface $queryBus
    ) {
    }

    /**
     * Dispatch une requête et retourne la valeur retournée par le handler.
     *
     * @throws \Throwable si le handler lève une exception
     */
    public function ask(object $query): mixed
    {
        try {
            $envelope = $this->queryBus->dispatch($query);

            /** @var HandledStamp $stamp */
            $stamp = $envelope->last(HandledStamp::class);

            if ($stamp === null) {
                throw new \LogicException(
                    sprintf('Aucun handler trouvé pour la requête "%s".', $query::class)
                );
            }

            return $stamp->getResult();
        } catch (HandlerFailedException $e) {
            $exceptions = $e->getWrappedExceptions();

            if (!empty($exceptions)) {
                throw reset($exceptions);
            }

            throw $e;
        }
    }
}
