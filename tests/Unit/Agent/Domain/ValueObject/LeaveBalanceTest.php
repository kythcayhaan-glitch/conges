<?php

// tests/Unit/Agent/Domain/ValueObject/LeaveBalanceTest.php

declare(strict_types=1);

namespace App\Tests\Unit\Agent\Domain\ValueObject;

use App\Agent\Domain\ValueObject\LeaveBalance;
use App\Leave\Domain\ValueObject\LeaveHours;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le Value Object LeaveBalance.
 * Couvre construction, débit, crédit, solde suffisant et cas limites.
 */
final class LeaveBalanceTest extends TestCase
{
    // =========================================================================
    // Tests de construction valide
    // =========================================================================

    #[Test]
    #[DataProvider('validBalanceProvider')]
    public function it_creates_valid_balance(float $value): void
    {
        $balance = new LeaveBalance($value);

        self::assertSame($value, $balance->getValue());
    }

    /**
     * @return array<string, array{float}>
     */
    public static function validBalanceProvider(): array
    {
        return [
            'zéro (solde épuisé)'   => [0.0],
            'solde standard'        => [208.0],
            'solde partiel'         => [56.50],
            'solde élevé'           => [999.99],
            'valeur décimale'       => [0.01],
        ];
    }

    // =========================================================================
    // Tests de construction invalide
    // =========================================================================

    #[Test]
    #[DataProvider('invalidBalanceProvider')]
    public function it_throws_on_invalid_balance(float $value, string $expectedMessage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches($expectedMessage);

        new LeaveBalance($value);
    }

    /**
     * @return array<string, array{float, string}>
     */
    public static function invalidBalanceProvider(): array
    {
        return [
            'négatif'        => [-0.01, '/négatif/'],
            'très négatif'   => [-208.0, '/négatif/'],
            'infini positif' => [INF, '/finie/'],
            'infini négatif' => [-INF, '/finie/'],
            'NaN'            => [NAN, '/finie/'],
        ];
    }

    // =========================================================================
    // Tests de la factory zero()
    // =========================================================================

    #[Test]
    public function it_creates_zero_balance(): void
    {
        $balance = LeaveBalance::zero();

        self::assertSame(0.0, $balance->getValue());
    }

    // =========================================================================
    // Tests de débit
    // =========================================================================

    #[Test]
    public function it_debits_hours_from_balance(): void
    {
        $balance  = new LeaveBalance(208.0);
        $hours    = new LeaveHours(7.5);
        $newBalance = $balance->debit($hours);

        self::assertSame(200.5, $newBalance->getValue());
    }

    #[Test]
    public function it_allows_debit_to_exact_zero(): void
    {
        $balance    = new LeaveBalance(7.5);
        $hours      = new LeaveHours(7.5);
        $newBalance = $balance->debit($hours);

        self::assertSame(0.0, $newBalance->getValue());
    }

    #[Test]
    public function it_throws_when_debit_exceeds_balance(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Solde insuffisant/');

        $balance = new LeaveBalance(5.0);
        $hours   = new LeaveHours(7.5);

        $balance->debit($hours);
    }

    #[Test]
    public function it_is_immutable_after_debit(): void
    {
        $original = new LeaveBalance(208.0);
        $hours    = new LeaveHours(8.0);
        $new      = $original->debit($hours);

        // L'original ne doit pas être modifié
        self::assertSame(208.0, $original->getValue());
        self::assertSame(200.0, $new->getValue());
        self::assertNotSame($original, $new);
    }

    // =========================================================================
    // Tests de crédit
    // =========================================================================

    #[Test]
    public function it_credits_hours_to_balance(): void
    {
        $balance    = new LeaveBalance(100.0);
        $hours      = new LeaveHours(8.0);
        $newBalance = $balance->credit($hours);

        self::assertSame(108.0, $newBalance->getValue());
    }

    #[Test]
    public function it_credits_to_zero_balance(): void
    {
        $balance    = LeaveBalance::zero();
        $hours      = new LeaveHours(7.5);
        $newBalance = $balance->credit($hours);

        self::assertSame(7.5, $newBalance->getValue());
    }

    #[Test]
    public function it_is_immutable_after_credit(): void
    {
        $original = new LeaveBalance(100.0);
        $hours    = new LeaveHours(8.0);
        $new      = $original->credit($hours);

        self::assertSame(100.0, $original->getValue());
        self::assertSame(108.0, $new->getValue());
    }

    // =========================================================================
    // Tests de isSufficientFor
    // =========================================================================

    #[Test]
    #[DataProvider('sufficientBalanceProvider')]
    public function it_correctly_checks_sufficiency(
        float $balance,
        float $requested,
        bool $expected
    ): void {
        $leaveBalance = new LeaveBalance($balance);
        $leaveHours   = new LeaveHours($requested);

        self::assertSame($expected, $leaveBalance->isSufficientFor($leaveHours));
    }

    /**
     * @return array<string, array{float, float, bool}>
     */
    public static function sufficientBalanceProvider(): array
    {
        return [
            'balance > demande'         => [208.0, 7.5, true],
            'balance = demande exacte'  => [7.5, 7.5, true],
            'balance < demande'         => [5.0, 7.5, false],
            'solde zéro, demande > 0'   => [0.0, 0.01, false],
        ];
    }

    // =========================================================================
    // Tests d'égalité
    // =========================================================================

    #[Test]
    public function it_considers_equal_balances_as_equal(): void
    {
        $a = new LeaveBalance(208.0);
        $b = new LeaveBalance(208.0);

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function it_considers_different_balances_as_not_equal(): void
    {
        $a = new LeaveBalance(208.0);
        $b = new LeaveBalance(207.0);

        self::assertFalse($a->equals($b));
    }

    // =========================================================================
    // Tests de représentation string
    // =========================================================================

    #[Test]
    #[DataProvider('toStringProvider')]
    public function it_formats_to_string_correctly(float $value, string $expected): void
    {
        $balance = new LeaveBalance($value);

        self::assertSame($expected, (string) $balance);
    }

    /**
     * @return array<string, array{float, string}>
     */
    public static function toStringProvider(): array
    {
        return [
            'zéro'    => [0.0, '0.00h'],
            'entier'  => [208.0, '208.00h'],
            'décimal' => [56.50, '56.50h'],
        ];
    }

    // =========================================================================
    // Tests de débit puis crédit (cycle complet)
    // =========================================================================

    #[Test]
    public function it_correctly_handles_debit_then_credit_cycle(): void
    {
        $initial    = new LeaveBalance(208.0);
        $hours      = new LeaveHours(7.5);

        $afterDebit  = $initial->debit($hours);
        $afterCredit = $afterDebit->credit($hours);

        self::assertSame(208.0, $initial->getValue());
        self::assertSame(200.5, $afterDebit->getValue());
        // Après restitution, on doit retrouver la valeur initiale
        self::assertTrue($afterCredit->equals($initial));
    }

    // =========================================================================
    // Tests d'immutabilité (readonly)
    // =========================================================================

    #[Test]
    public function it_is_immutable_and_readonly(): void
    {
        $balance    = new LeaveBalance(208.0);
        $reflection = new \ReflectionClass($balance);

        self::assertTrue($reflection->isReadOnly(), 'LeaveBalance doit être une classe readonly.');
    }
}
