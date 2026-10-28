<?php

declare(strict_types=1);

use TontonPro\Contracts\Dashboard\DashboardFigure;
use TontonPro\Contracts\Dashboard\DashboardFigureKind;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Value\BusinessDate;
use TontonPro\Contracts\Value\Currency;
use TontonPro\Contracts\Value\Money;

describe('indicateurs acceptés', function () {
    it('portent une valeur entière selon leur nature', function () {
        $xaf = new Currency('XAF', 0);

        expect(DashboardFigure::count('hotelpro::dashboard.arrivals', 0)->toArray())->toBe([
            'labelKey' => 'hotelpro::dashboard.arrivals',
            'kind' => 'count',
            'value' => 0,
            'currency' => null,
            'date' => null,
            'hintKey' => null,
            'routeName' => null,
        ])->and(DashboardFigure::percentBasisPoints('hotelpro::dashboard.occupancy', 8250)->toArray()['value'])->toBe(8250)
            ->and(DashboardFigure::money('hotelpro::dashboard.collected', new Money(152500, $xaf), 'hotelpro::dashboard.collected_hint', 'hotelpro.cash')->toArray())->toBe([
                'labelKey' => 'hotelpro::dashboard.collected',
                'kind' => 'money',
                'value' => 152500,
                'currency' => ['code' => 'XAF', 'decimals' => 0],
                'date' => null,
                'hintKey' => 'hotelpro::dashboard.collected_hint',
                'routeName' => 'hotelpro.cash',
            ]);
    });

    it('acceptent un taux au-delà de 100 % et un montant nul', function () {
        expect(DashboardFigure::percentBasisPoints('l', 10500)->value)->toBe(10500)
            ->and(DashboardFigure::money('l', Money::zero(new Currency('EUR', 2)))->currency?->decimals)->toBe(2);
    });

    it('nomment une journée sans valeur ni devise', function () {
        expect(DashboardFigure::businessDate('hotelpro::dashboard.day_to_close', new BusinessDate('2026-10-27'))->toArray())->toBe([
            'labelKey' => 'hotelpro::dashboard.day_to_close',
            'kind' => 'business_date',
            'value' => null,
            'currency' => null,
            'date' => '2026-10-27',
            'hintKey' => null,
            'routeName' => null,
        ]);
    });
});

describe('indicateurs refusés', function () {
    it('refuse un montant négatif, comme un fait', function () {
        expect(violation(fn () => new DashboardFigure('l', DashboardFigureKind::Money, -1, new Currency('XAF', 0))))->toBe(ViolationCode::AmountNegative)
            ->and(violation(fn () => DashboardFigure::money('l', new Money(-1, new Currency('XAF', 0)))))->toBe(ViolationCode::AmountNegative);
    });

    it('refuse un indicateur mal formé', function (Closure $build, string $field) {
        expect(violationAt($build))->toBe(ViolationCode::DashboardFigureMalformed->value.'@'.$field);
    })->with([
        'montant sans devise' => [fn () => new DashboardFigure('l', DashboardFigureKind::Money, 100), 'currency'],
        'nombre négatif' => [fn () => DashboardFigure::count('l', -1), 'value'],
        'taux négatif' => [fn () => DashboardFigure::percentBasisPoints('l', -1), 'value'],
        'nombre avec devise' => [fn () => new DashboardFigure('l', DashboardFigureKind::Count, 1, new Currency('XAF', 0)), 'currency'],
        'nombre sans valeur' => [fn () => new DashboardFigure('l', DashboardFigureKind::Count, null), 'value'],
        'nombre avec journée' => [fn () => new DashboardFigure('l', DashboardFigureKind::Count, 1, null, new BusinessDate('2026-10-27')), 'value'],
        'journée sans date' => [fn () => new DashboardFigure('l', DashboardFigureKind::BusinessDate, null), 'date'],
        'journée avec valeur' => [fn () => new DashboardFigure('l', DashboardFigureKind::BusinessDate, 0, null, new BusinessDate('2026-10-27')), 'date'],
        'journée avec devise' => [fn () => new DashboardFigure('l', DashboardFigureKind::BusinessDate, null, new Currency('XAF', 0), new BusinessDate('2026-10-27')), 'date'],
        'libellé absent' => [fn () => DashboardFigure::count(' ', 1), 'labelKey'],
        'indication vide' => [fn () => DashboardFigure::count('l', 1, ''), 'hintKey'],
        'route vide' => [fn () => DashboardFigure::count('l', 1, null, ' '), 'routeName'],
    ]);
});
