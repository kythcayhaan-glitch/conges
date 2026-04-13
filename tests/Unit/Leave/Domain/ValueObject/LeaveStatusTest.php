<?php

// tests/Unit/Leave/Domain/ValueObject/LeaveStatusTest.php

declare(strict_types=1);

namespace App\Tests\Unit\Leave\Domain\ValueObject;

use App\Leave\Domain\ValueObject\LeaveStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'enum LeaveStatus.
 * Couvre toutes les transitions valides et invalides.
 */
final class LeaveStatusTest extends TestCase
{
    #[Test]
    #[DataProvider('validTransitionsProvider')]
    public function it_allows_valid_transitions(LeaveStatus $from, LeaveStatus $to): void
    {
        self::assertTrue($from->canTransitionTo($to));
    }

    /**
     * @return array<string, array{LeaveStatus, LeaveStatus}>
     */
    public static function validTransitionsProvider(): array
    {
        return [
            'PENDING → APPROVED'  => [LeaveStatus::PENDING, LeaveStatus::APPROVED],
            'PENDING → REJECTED'  => [LeaveStatus::PENDING, LeaveStatus::REJECTED],
            'PENDING → CANCELLED' => [LeaveStatus::PENDING, LeaveStatus::CANCELLED],
            'APPROVED → CANCELLED'=> [LeaveStatus::APPROVED, LeaveStatus::CANCELLED],
        ];
    }

    #[Test]
    #[DataProvider('invalidTransitionsProvider')]
    public function it_forbids_invalid_transitions(LeaveStatus $from, LeaveStatus $to): void
    {
        self::assertFalse($from->canTransitionTo($to));
    }

    /**
     * @return array<string, array{LeaveStatus, LeaveStatus}>
     */
    public static function invalidTransitionsProvider(): array
    {
        return [
            'REJECTED → APPROVED'  => [LeaveStatus::REJECTED, LeaveStatus::APPROVED],
            'REJECTED → PENDING'   => [LeaveStatus::REJECTED, LeaveStatus::PENDING],
            'REJECTED → CANCELLED' => [LeaveStatus::REJECTED, LeaveStatus::CANCELLED],
            'CANCELLED → PENDING'  => [LeaveStatus::CANCELLED, LeaveStatus::PENDING],
            'CANCELLED → APPROVED' => [LeaveStatus::CANCELLED, LeaveStatus::APPROVED],
            'CANCELLED → REJECTED' => [LeaveStatus::CANCELLED, LeaveStatus::REJECTED],
            'APPROVED → PENDING'   => [LeaveStatus::APPROVED, LeaveStatus::PENDING],
            'APPROVED → REJECTED'  => [LeaveStatus::APPROVED, LeaveStatus::REJECTED],
        ];
    }

    #[Test]
    public function it_identifies_terminal_statuses(): void
    {
        self::assertTrue(LeaveStatus::REJECTED->isTerminal());
        self::assertTrue(LeaveStatus::CANCELLED->isTerminal());
        self::assertFalse(LeaveStatus::PENDING->isTerminal());
        self::assertFalse(LeaveStatus::APPROVED->isTerminal());
    }

    #[Test]
    public function it_has_correct_labels(): void
    {
        self::assertSame('En attente', LeaveStatus::PENDING->label());
        self::assertSame('Approuvée', LeaveStatus::APPROVED->label());
        self::assertSame('Rejetée', LeaveStatus::REJECTED->label());
        self::assertSame('Annulée', LeaveStatus::CANCELLED->label());
    }

    #[Test]
    public function it_creates_from_string_value(): void
    {
        self::assertSame(LeaveStatus::PENDING, LeaveStatus::from('PENDING'));
        self::assertSame(LeaveStatus::APPROVED, LeaveStatus::from('APPROVED'));
    }
}
