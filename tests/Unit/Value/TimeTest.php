<?php

declare(strict_types=1);

use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Value\BusinessDate;
use TontonPro\Contracts\Value\UtcInstant;

describe('UtcInstant', function () {
    it('accepte un instant UTC et le sérialise avec ses microsecondes', function () {
        $instant = new UtcInstant(new DateTimeImmutable('2026-10-16 09:30:00', new DateTimeZone('UTC')));

        expect($instant->toString())->toBe('2026-10-16T09:30:00.000000Z')
            ->and(UtcInstant::fromString('2026-10-16T09:30:00Z')->toString())->toBe('2026-10-16T09:30:00.000000Z')
            ->and(UtcInstant::fromString('2026-10-16T09:30:00.123456Z')->toString())->toBe('2026-10-16T09:30:00.123456Z')
            ->and(new UtcInstant(new DateTimeImmutable('2026-10-16T09:30:00+00:00')))->toBeInstanceOf(UtcInstant::class);
    });

    it('refuse un instant dans un autre fuseau, sans le convertir', function (string $zone) {
        expect(violation(fn () => new UtcInstant(new DateTimeImmutable('2026-10-16 09:30:00', new DateTimeZone($zone)))))
            ->toBe(ViolationCode::InstantNotUtc);
    })->with(['+01:00', 'Africa/Douala', 'décalage nul mais pas UTC' => 'Africa/Abidjan']);

    it('refuse une chaîne hors du format UTC attendu', function (string $value) {
        expect(violation(fn () => UtcInstant::fromString($value)))->toBe(ViolationCode::InstantInvalid);
    })->with([
        'avec un décalage' => '2026-10-16T09:30:00+01:00',
        'sans T' => '2026-10-16 09:30:00Z',
        'jour inexistant' => '2026-02-30T09:30:00Z',
        'heure inexistante' => '2026-10-16T24:00:00Z',
        'millisecondes' => '2026-10-16T09:30:00.123Z',
    ]);
});

describe('BusinessDate', function () {
    it('accepte une journée valide et compare par chaîne', function () {
        expect((new BusinessDate('2026-10-17'))->isAfter(new BusinessDate('2026-10-16')))->toBeTrue()
            ->and((new BusinessDate('2026-10-16'))->isAfter(new BusinessDate('2026-10-16')))->toBeFalse()
            ->and((new BusinessDate('2028-02-29'))->value)->toBe('2028-02-29');
    });

    it('refuse une journée invalide', function (string $value) {
        expect(violation(fn () => new BusinessDate($value)))->toBe(ViolationCode::BusinessDateInvalid);
    })->with(['2026-02-30', '16/10/2026', '2026-10-16T00:00', '2026-1-6', '']);
});
