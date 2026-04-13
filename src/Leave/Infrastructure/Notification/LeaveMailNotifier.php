<?php

declare(strict_types=1);

namespace App\Leave\Infrastructure\Notification;

use App\Leave\Domain\Entity\LeaveRequest;
use App\Security\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class LeaveMailNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $fromAddress,
    ) {
    }

    public function notifyApproved(User $agent, LeaveRequest $leave): void
    {
        $this->send(
            $agent,
            'Votre demande de congé a été approuvée',
            sprintf(
                "Bonjour %s,\n\nVotre demande de congé du %s au %s (%.2f h) a été approuvée.\n\nCordialement,\nLe service RH",
                $agent->getNomComplet() ?? $agent->getUsername(),
                (new \DateTimeImmutable($leave->getDateDebutFormatted()))->format('d/m/Y'),
                (new \DateTimeImmutable($leave->getDateFinFormatted()))->format('d/m/Y'),
                $leave->getHeuresValue(),
            )
        );
    }

    public function notifyRejected(User $agent, LeaveRequest $leave, ?string $commentaire): void
    {
        $motif = $commentaire ? "\n\nMotif : " . $commentaire : '';

        $this->send(
            $agent,
            'Votre demande de congé a été rejetée',
            sprintf(
                "Bonjour %s,\n\nVotre demande de congé du %s au %s (%.2f h) a été rejetée.%s\n\nCordialement,\nLe service RH",
                $agent->getNomComplet() ?? $agent->getUsername(),
                (new \DateTimeImmutable($leave->getDateDebutFormatted()))->format('d/m/Y'),
                (new \DateTimeImmutable($leave->getDateFinFormatted()))->format('d/m/Y'),
                $leave->getHeuresValue(),
                $motif,
            )
        );
    }

    public function notifyCancelled(User $agent, LeaveRequest $leave): void
    {
        $this->send(
            $agent,
            'Votre demande de congé a été annulée',
            sprintf(
                "Bonjour %s,\n\nVotre demande de congé du %s au %s (%.2f h) a été annulée.\n\nCordialement,\nLe service RH",
                $agent->getNomComplet() ?? $agent->getUsername(),
                (new \DateTimeImmutable($leave->getDateDebutFormatted()))->format('d/m/Y'),
                (new \DateTimeImmutable($leave->getDateFinFormatted()))->format('d/m/Y'),
                $leave->getHeuresValue(),
            )
        );
    }

    private function send(User $agent, string $subject, string $body): void
    {
        $email = (new Email())
            ->from($this->fromAddress)
            ->to($agent->getEmail())
            ->subject($subject)
            ->text($body);

        $this->mailer->send($email);
    }
}
