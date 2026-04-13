<?php

// src/Shared/Infrastructure/EventListener/ExceptionListener.php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventListener;

use App\Leave\Domain\Exception\InsufficientLeaveBalanceException;
use App\Leave\Domain\Exception\LeaveRequestNotFoundException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Listener centralisé pour transformer toutes les exceptions en réponses JSON cohérentes.
 * Responsabilité unique : convertir les exceptions en réponses HTTP JSON.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class ExceptionListener
{
    /**
     * Intercepte toutes les exceptions et retourne une JsonResponse formatée.
     */
    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();

        [$statusCode, $message, $errors] = $this->resolveException($exception);

        $body = ['error' => $message];

        if (!empty($errors)) {
            $body['violations'] = $errors;
        }

        $event->setResponse(
            new JsonResponse($body, $statusCode)
        );
    }

    /**
     * Résout le code HTTP, le message et les erreurs de validation depuis l'exception.
     *
     * @return array{int, string, array<string, string>}
     */
    private function resolveException(\Throwable $exception): array
    {
        // Exceptions de validation Symfony
        if ($exception instanceof ValidationFailedException) {
            $errors = [];
            foreach ($exception->getViolations() as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }
            return [422, 'Les données fournies sont invalides.', $errors];
        }

        // Exception domaine : solde insuffisant
        if ($exception instanceof InsufficientLeaveBalanceException) {
            return [422, $exception->getMessage(), []];
        }

        // Exceptions domaine : entité introuvable
        if ($exception instanceof LeaveRequestNotFoundException) {
            return [404, $exception->getMessage(), []];
        }

        // Transition de statut invalide
        if ($exception instanceof \DomainException) {
            return [$exception->getCode() ?: 422, $exception->getMessage(), []];
        }

        // InvalidArgumentException (Value Object invalide)
        if ($exception instanceof \InvalidArgumentException) {
            return [400, $exception->getMessage(), []];
        }

        // Exceptions HTTP Symfony
        if ($exception instanceof AccessDeniedHttpException) {
            return [403, 'Accès interdit.', []];
        }

        if ($exception instanceof NotFoundHttpException) {
            return [404, 'Ressource introuvable.', []];
        }

        if ($exception instanceof UnprocessableEntityHttpException) {
            return [422, $exception->getMessage(), []];
        }

        if ($exception instanceof HttpExceptionInterface) {
            return [$exception->getStatusCode(), $exception->getMessage(), []];
        }

        // Erreur serveur générique (ne pas exposer les détails en production)
        return [500, 'Une erreur interne est survenue.', []];
    }
}
