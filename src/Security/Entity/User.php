<?php

declare(strict_types=1);

namespace App\Security\Entity;

use App\Shared\Domain\ValueObject\LeaveBalance;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_user_username', columns: ['username'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private string $username;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(type: 'float', options: ['default' => 0.0])]
    private float $leaveBalanceValue = 0.0;

    #[ORM\Column(type: 'float', name: 'initial_leave_balance_value', options: ['default' => 0.0])]
    private float $initialLeaveBalanceValue = 0.0;

    #[ORM\Column(type: 'float', name: 'rtt_balance_value', options: ['default' => 0.0])]
    private float $rttBalanceValue = 0.0;

    #[ORM\Column(type: 'float', name: 'initial_rtt_balance_value', options: ['default' => 0.0])]
    private float $initialRttBalanceValue = 0.0;

    #[ORM\Column(type: 'float', name: 'heure_sup_balance_value', options: ['default' => 0.0])]
    private float $heureSupBalanceValue = 0.0;

    #[ORM\Column(type: 'float', name: 'initial_heure_sup_balance_value', options: ['default' => 0.0])]
    private float $initialHeureSupBalanceValue = 0.0;

    /** @var string[] */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    private string $password;

    /** @var string[] */
    #[ORM\Column(type: 'json', name: 'service_numbers', options: ['default' => '[]'])]
    private array $serviceNumbers = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $email,
        array $roles = ['ROLE_AGENT'],
        ?string $username = null,
        ?string $nom = null,
        ?string $prenom = null,
        float $leaveBalanceValue = 0.0,
        float $rttBalanceValue = 0.0,
        float $heureSupBalanceValue = 0.0,
        array $serviceNumbers = [],
    ) {
        $this->id                   = $id;
        $this->email                = $email;
        $this->roles                = $roles;
        $this->username             = $username ?? strstr($email, '@', true);
        $this->nom                  = $nom;
        $this->prenom               = $prenom;
        $this->leaveBalanceValue    = $leaveBalanceValue;
        $this->rttBalanceValue      = $rttBalanceValue;
        $this->heureSupBalanceValue = $heureSupBalanceValue;
        $this->serviceNumbers       = $serviceNumbers;
        $this->updatedAt            = new \DateTimeImmutable();
    }

    public function getUserIdentifier(): string { return $this->username; }
    public function getUsername(): string        { return $this->username; }
    public function setUsername(string $v): void { $this->username = $v; }

    public function getRoles(): array
    {
        $roles   = $this->roles;
        $roles[] = 'ROLE_AGENT';
        return array_unique($roles);
    }

    public function setRoles(array $roles): void
    {
        $this->roles = array_values(array_unique($roles));
    }

    public function getPassword(): string              { return $this->password; }
    public function setPassword(string $password): void { $this->password = $password; }
    public function eraseCredentials(): void {}

    public function getId(): string    { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $v): void { $this->email = $v; }

    public function getNom(): ?string      { return $this->nom; }
    public function setNom(?string $v): void { $this->nom = $v; $this->updatedAt = new \DateTimeImmutable(); }

    public function getPrenom(): ?string      { return $this->prenom; }
    public function setPrenom(?string $v): void { $this->prenom = $v; $this->updatedAt = new \DateTimeImmutable(); }

    public function getNomComplet(): ?string
    {
        if ($this->prenom === null && $this->nom === null) {
            return null;
        }
        return trim(($this->prenom ?? '') . ' ' . ($this->nom ?? ''));
    }

    public function getLeaveBalance(): LeaveBalance
    {
        return new LeaveBalance($this->leaveBalanceValue);
    }

    public function applyLeaveBalance(LeaveBalance $balance): void
    {
        $this->leaveBalanceValue = $balance->getValue();
        $this->updatedAt         = new \DateTimeImmutable();
    }

    public function getInitialLeaveBalance(): LeaveBalance
    {
        return new LeaveBalance($this->initialLeaveBalanceValue);
    }

    public function applyInitialLeaveBalance(LeaveBalance $balance): void
    {
        $this->initialLeaveBalanceValue = $balance->getValue();
        $this->updatedAt                = new \DateTimeImmutable();
    }

    public function getRttBalance(): LeaveBalance
    {
        return new LeaveBalance($this->rttBalanceValue);
    }

    public function applyRttBalance(LeaveBalance $balance): void
    {
        $this->rttBalanceValue = $balance->getValue();
        $this->updatedAt       = new \DateTimeImmutable();
    }

    public function getInitialRttBalance(): LeaveBalance
    {
        return new LeaveBalance($this->initialRttBalanceValue);
    }

    public function applyInitialRttBalance(LeaveBalance $balance): void
    {
        $this->initialRttBalanceValue = $balance->getValue();
        $this->updatedAt              = new \DateTimeImmutable();
    }

    public function getHeureSupBalance(): LeaveBalance
    {
        return new LeaveBalance($this->heureSupBalanceValue);
    }

    public function applyHeureSupBalance(LeaveBalance $balance): void
    {
        $this->heureSupBalanceValue = $balance->getValue();
        $this->updatedAt            = new \DateTimeImmutable();
    }

    public function getInitialHeureSupBalance(): LeaveBalance
    {
        return new LeaveBalance($this->initialHeureSupBalanceValue);
    }

    public function applyInitialHeureSupBalance(LeaveBalance $balance): void
    {
        $this->initialHeureSupBalanceValue = $balance->getValue();
        $this->updatedAt                   = new \DateTimeImmutable();
    }

    /** @return string[] */
    public function getServiceNumbers(): array { return $this->serviceNumbers; }

    /**
     * Accepte un tableau de numéros bruts (ex: ['3', '11']) et les normalise en 2 chiffres.
     * @param string[] $numbers
     */
    public function setServiceNumbers(array $numbers): void
    {
        $normalized = [];
        foreach ($numbers as $n) {
            $n = trim((string) $n);
            if ($n !== '') {
                $normalized[] = str_pad($n, 2, '0', STR_PAD_LEFT);
            }
        }
        $this->serviceNumbers = array_values(array_unique($normalized));
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function hasServiceNumber(string $sn): bool
    {
        return in_array(str_pad(trim($sn), 2, '0', STR_PAD_LEFT), $this->serviceNumbers, true);
    }

    /** Retourne les numéros formatés séparés par des virgules, pour affichage. */
    public function getServiceNumbersDisplay(): string
    {
        return implode(', ', $this->serviceNumbers);
    }

    public function hasAgentProfile(): bool
    {
        return $this->nom !== null && $this->prenom !== null;
    }
}
