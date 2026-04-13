<?php

// src/Leave/Domain/Entity/LeaveAuditLog.php

declare(strict_types=1);

namespace App\Leave\Domain\Entity;

use App\Leave\Domain\ValueObject\LeaveStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Entité Doctrine représentant une entrée dans l'historique d'audit d'une demande de congé.
 * Chaque changement de statut crée une nouvelle entrée immuable.
 */
#[ORM\Entity]
#[ORM\Table(name: 'leave_audit_logs')]
class LeaveAuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['audit:read'])]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, name: 'leave_request_id')]
    #[Groups(['audit:read'])]
    private string $leaveRequestId;

    #[ORM\Column(type: 'string', length: 20, enumType: LeaveStatus::class, nullable: true, name: 'ancien_statut')]
    #[Groups(['audit:read'])]
    private ?LeaveStatus $ancienStatut;

    #[ORM\Column(type: 'string', length: 20, enumType: LeaveStatus::class, name: 'nouveau_statut')]
    #[Groups(['audit:read'])]
    private LeaveStatus $nouveauStatut;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['audit:read'])]
    private ?string $commentaire;

    /**
     * Identifiant de l'auteur de l'action (UUID de l'utilisateur Symfony Security).
     */
    #[ORM\Column(type: 'string', length: 36, nullable: true, name: 'auteur_id')]
    #[Groups(['audit:read'])]
    private ?string $auteurId;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    #[Groups(['audit:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $leaveRequestId,
        ?LeaveStatus $ancienStatut,
        LeaveStatus $nouveauStatut,
        ?string $commentaire = null,
        ?string $auteurId = null
    ) {
        $this->id             = $id;
        $this->leaveRequestId = $leaveRequestId;
        $this->ancienStatut   = $ancienStatut;
        $this->nouveauStatut  = $nouveauStatut;
        $this->commentaire    = $commentaire;
        $this->auteurId       = $auteurId;
        $this->createdAt      = new \DateTimeImmutable();
    }

    /**
     * Retourne l'identifiant du log.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Retourne l'identifiant de la demande de congé concernée.
     */
    public function getLeaveRequestId(): string
    {
        return $this->leaveRequestId;
    }

    /**
     * Retourne le statut précédent (null si c'est la création).
     */
    public function getAncienStatut(): ?LeaveStatus
    {
        return $this->ancienStatut;
    }

    /**
     * Retourne le nouveau statut après l'action.
     */
    public function getNouveauStatut(): LeaveStatus
    {
        return $this->nouveauStatut;
    }

    /**
     * Retourne le commentaire associé à l'action, s'il existe.
     */
    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    /**
     * Retourne l'identifiant de l'auteur de l'action.
     */
    public function getAuteurId(): ?string
    {
        return $this->auteurId;
    }

    /**
     * Retourne la date et heure de l'action.
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
