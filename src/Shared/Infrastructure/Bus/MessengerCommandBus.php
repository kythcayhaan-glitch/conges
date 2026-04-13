<?php

// src/Shared/Infrastructure/Bus/MessengerCommandBus.php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Domain\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Implémentation du CommandBus via Symfony Messenger.
 * Route chaque commande vers son handler de façon synchrone.
 */
final class MessengerCommandBus implements CommandBusInterface
{
    public function __construct(
        private readonly MessageBusInterface $commandBus
    ) {
    }

    /**
     * Dispatch une commande via Symfony Messenger.
     * Retourne la valeur du HandledStamp si le handler en retourne une (ex : UUID créé).
     * Déballe les HandlerFailedException pour exposer l'exception domaine originale.
     *
     * @throws \Throwable si le handler lève une exception
     */
    public function dispatch(object $command): mixed
    {
        try {
            $envelope = $this->commandBus->dispatch($command);

            /** @var HandledStamp|null $stamp */
            $stamp = $envelope->last(HandledStamp::class);

            return $stamp?->getResult();
        } catch (HandlerFailedException $e) {
            $exceptions = $e->getWrappedExceptions();

            if (!empty($exceptions)) {
                throw reset($exceptions);
            }

            throw $e;
        }
    }
}
