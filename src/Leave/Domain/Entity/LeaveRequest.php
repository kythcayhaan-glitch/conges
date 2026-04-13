<?php

declare(strict_types=1);

namespace App\Leave\Domain\Entity;

use App\Leave\Domain\ValueObject\LeaveHours;
use App\Leave\Domain\ValueObject\LeavePeriod;
use App\Leave\Domain\ValueObject\LeaveStatus;
use App\Leave\Domain\ValueObject\LeaveType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ORM\Entity]
#[ORM\Table(name: 'leave_requests')]
class LeaveRequest
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['leave:read'])]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, name: 'user_id')]
    private string $userId;

    #[ORM\Column(type: 'float')]
    private float $heures;

    #[ORM\Column(type: 'date_immutable', name: 'date_debut')]
    private \DateTimeImmutable $dateDebut;

    #[ORM\Column(type: 'string', length: 5, name: 'heure_debut', nullable: true)]
    private ?string $heureDebut;

    #[ORM\Column(type: 'date_immutable', name: 'date_fin')]
    private \DateTimeImmutable $dateFin;

    #[ORM\Column(type: 'string', length: 5, name: 'heure_fin', nullable: true)]
    private ?string $heureFin;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $matin = false;

    #[ORM\Column(type: 'boolean', name: 'apres_midi', options: ['default' => false])]
    private bool $apresMidi = false;

    #[ORM\Column(type: 'boolean', name: 'journee_entiere', options: ['default' => false])]
    private bool $journeeEntiere = false;

    #[ORM\Column(type: 'string', length: 10, enumType: LeaveType::class, options: ['default' => 'CONGE'])]
    private LeaveType $type;

    #[ORM\Column(type: 'string', length: 20, enumType: LeaveStatus::class)]
    private LeaveStatus $statut;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['leave:read'])]
    private ?string $motif;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    #[Groups(['leave:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    #[Groups(['leave:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $userId,
        LeaveHours $heures,
        LeavePeriod $period,
        ?string $motif = null,
        ?string $heureDebut = null,
        ?string $heureFin = null,
        bool $matin = false,
        bool $apresMidi = false,
        bool $journeeEntiere = false,
        LeaveType $type = LeaveType::CONGE,
    ) {
        $this->id             = $id;
        $this->userId         = $userId;
        $this->heures         = $heures->getValue();
        $this->dateDebut      = $period->getStart();
        $this->heureDebut     = $heureDebut;
        $this->dateFin        = $period->getEnd();
        $this->heureFin       = $heureFin;
        $this->matin          = $matin;
        $this->apresMidi      = $apresMidi;
        $this->journeeEntiere = $journeeEntiere;
        $this->type           = $type;
        $this->statut         = LeaveStatus::PENDING;
        $this->motif      = $motif;
        $this->createdAt  = new \DateTimeImmutable();
        $this->updatedAt  = new \DateTimeImmutable();
    }

    public function edit(LeaveHours $heures, LeavePeriod $period, ?string $motif, ?string $heureDebut, ?string $heureFin, bool $matin = false, bool $apresMidi = false, bool $journeeEntiere = false): void
    {
        if (!$this->isPending()) {
            throw new \DomainException('Seules les demandes en attente peuvent être modifiées.');
        }
        $this->heures         = $heures->getValue();
        $this->dateDebut      = $period->getStart();
        $this->heureDebut     = $heureDebut;
        $this->dateFin        = $period->getEnd();
        $this->heureFin       = $heureFin;
        $this->matin          = $matin;
        $this->apresMidi      = $apresMidi;
        $this->journeeEntiere = $journeeEntiere;
        $this->motif          = $motif;
        $this->updatedAt      = new \DateTimeImmutable();
    }

    public function approve(): void         { $this->transition(LeaveStatus::APPROVED); }
    public function validateByChef(): void  { $this->transition(LeaveStatus::VALIDATED_CHEF); }
    public function reject(): void          { $this->transition(LeaveStatus::REJECTED); }
    public function cancel(): void          { $this->transition(LeaveStatus::CANCELLED); }

    private function transition(LeaveStatus $newStatus): void
    {
        if (!$this->statut->canTransitionTo($newStatus)) {
            throw new \DomainException(sprintf(
                'Impossible de passer la demande de congé du statut "%s" vers "%s".',
                $this->statut->label(), $newStatus->label()
            ));
        }
        $this->statut    = $newStatus;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string         { return $this->id; }
    public function getUserId(): string     { return $this->userId; }
    public function getHeureDebut(): ?string  { return $this->heureDebut; }
    public function getHeureFin(): ?string    { return $this->heureFin; }
    public function isMatin(): bool           { return $this->matin; }
    public function isApresMidi(): bool       { return $this->apresMidi; }
    public function isJourneeEntiere(): bool  { return $this->journeeEntiere; }
    public function getType(): LeaveType      { return $this->type; }
    public function getHeures(): LeaveHours { return new LeaveHours($this->heures); }
    public function getPeriod(): LeavePeriod { return new LeavePeriod($this->dateDebut, $this->dateFin); }
    public function getStatut(): LeaveStatus { return $this->statut; }
    public function getMotif(): ?string  { return $this->motif; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function isPending(): bool          { return $this->statut === LeaveStatus::PENDING; }
    public function isValidatedByChef(): bool  { return $this->statut === LeaveStatus::VALIDATED_CHEF; }
    public function isApproved(): bool         { return $this->statut === LeaveStatus::APPROVED; }
    public function isRejected(): bool         { return $this->statut === LeaveStatus::REJECTED; }
    public function isCancelled(): bool        { return $this->statut === LeaveStatus::CANCELLED; }

    // Serialization helpers
    #[Groups(['leave:read'])] #[SerializedName('userId')]
    public function getUserIdValue(): string { return $this->userId; }

    #[Groups(['leave:read'])] #[SerializedName('heures')]
    public function getHeuresValue(): float { return $this->heures; }

    #[Groups(['leave:read'])] #[SerializedName('dateDebut')]
    public function getDateDebutFormatted(): string { return $this->dateDebut->format('Y-m-d'); }

    #[Groups(['leave:read'])] #[SerializedName('dateFin')]
    public function getDateFinFormatted(): string { return $this->dateFin->format('Y-m-d'); }

    #[Groups(['leave:read'])] #[SerializedName('statut')]
    public function getStatutValue(): string { return $this->statut->value; }

    #[Groups(['leave:read'])] #[SerializedName('statutLabel')]
    public function getStatutLabel(): string { return $this->statut->label(); }

    #[Groups(['leave:read'])] #[SerializedName('motif')]
    public function getMotifValue(): ?string { return $this->motif; }

    #[Groups(['leave:read'])] #[SerializedName('createdAt')]
    public function getCreatedAtFormatted(): string { return $this->createdAt->format(\DateTimeInterface::ATOM); }

    #[Groups(['leave:read'])] #[SerializedName('updatedAt')]
    public function getUpdatedAtFormatted(): string { return $this->updatedAt->format(\DateTimeInterface::ATOM); }
}
