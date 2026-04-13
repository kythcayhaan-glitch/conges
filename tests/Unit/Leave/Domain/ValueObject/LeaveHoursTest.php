<?php

// tests/Unit/Leave/Domain/ValueObject/LeaveHoursTest.php

declare(strict_types=1);

namespace App\Tests\Unit\Leave\Domain\ValueObject;

use App\Leave\Domain\ValueObject\LeaveHours;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le Value Object LeaveHours.
 * Couvre les cas limites, les valeurs valides et les cas d'erreur.
 */
final class LeaveHoursTest extends TestCase
{
    // =========================================================================
    // Tests de construction valide
    // =========================================================================

    #[Test]
    #[DataProvider('validHoursProvider')]
    public function it_creates_valid_leave_hours(float $hours): void
    {
        $leaveHours = new LeaveHours($hours);

        self::assertSame($hours, $leaveHours->getValue());
    }

    /**
     * @return array<string, array{float}>
     */
    public static function validHoursProvider(): array
    {
        return [
            'valeur minimale positive' => [PHP_FLOAT_EPSILON],
            'une heure exacte'         => [1.0],
            'demi-journée (3.5h)'      => [3.5],
            'journée standard (7.5h)'  => [7.5],
            'journée (8h)'             => [8.0],
            'solde annuel typique'     => [208.0],
            'valeur décimale fine'     => [0.25],
            'valeur élevée'            => [999.99],
        ];
    }

    // =========================================================================
    // Tests de construction invalide
    // =========================================================================

    #[Test]
    #[DataProvider('invalidHoursProvider')]
    public function it_throws_on_invalid_hours(float $hours, string $expectedMessage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches($expectedMessage);

        new LeaveHours($hours);
    }

    /**
     * @return array<string, array{float, string}>
     */
    public static function invalidHoursProvider(): array
    {
        return [
            'zéro'          => [0.0, '/strictement positif/'],
            'négatif'       => [-1.0, '/strictement positif/'],
            'très négatif'  => [-999.0, '/strictement positif/'],
            'infini positif'=> [INF, '/finie/'],
            'infini négatif'=> [-INF, '/finie/'],
            'NaN'           => [NAN, '/finie/'],
        ];
    }

    // =========================================================================
    // Tests d'égalité
    // =========================================================================

    #[Test]
    public function it_considers_equal_values_as_equal(): void
    {
        $a = new LeaveHours(7.5);
        $b = new LeaveHours(7.5);

        self::assertTrue($a->equals($b));
        self::assertTrue($b->equals($a));
    }

    #[Test]
    public function it_considers_different_values_as_not_equal(): void
    {
        $a = new LeaveHours(7.5);
        $b = new LeaveHours(8.0);

        self::assertFalse($a->equals($b));
    }

    // =========================================================================
    // Tests de comparaison
    // =========================================================================

    #[Test]
    public function it_returns_true_when_less_than_or_equal(): void
    {
        $small = new LeaveHours(7.5);
        $large = new LeaveHours(8.0);

        self::assertTrue($small->isLessThanOrEqualTo($large));
        self::assertTrue($small->isLessThanOrEqualTo(new LeaveHours(7.5)));
    }

    #[Test]
    public function it_returns_false_when_greater(): void
    {
        $large = new LeaveHours(8.0);
        $small = new LeaveHours(7.5);

        self::assertFalse($large->isLessThanOrEqualTo($small));
    }

    // =========================================================================
    // Tests de représentation string
    // =========================================================================

    #[Test]
    #[DataProvider('toStringProvider')]
    public function it_formats_to_string_correctly(float $hours, string $expected): void
    {
        $leaveHours = new LeaveHours($hours);

        self::assertSame($expected, (string) $leaveHours);
    }

    /**
     * @return array<string, array{float, string}>
     */
    public static function toStringProvider(): array
    {
        return [
            'entier'    => [8.0, '8.00h'],
            'décimal'   => [7.5, '7.50h'],
            'fin'       => [0.25, '0.25h'],
            'grand'     => [208.0, '208.00h'],
        ];
    }

    // =========================================================================
    // Tests d'immutabilité
    // =========================================================================

    #[Test]
    public function it_is_immutable_and_readonly(): void
    {
        $leaveHours = new LeaveHours(7.5);

        // Vérifier que la classe est readonly (PHP 8.2+)
        $reflection = new \ReflectionClass($leaveHours);
        self::assertTrue($reflection->isReadOnly(), 'LeaveHours doit être une classe readonly.');
    }
}
