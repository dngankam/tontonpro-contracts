<?php

declare(strict_types=1);

use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Tests\Support\Parts;
use TontonPro\Contracts\Tests\Support\Values;
use TontonPro\Contracts\Vocabulary\LineKind;
use TontonPro\Contracts\Vocabulary\PriceBasis;
use TontonPro\Contracts\Vocabulary\TaxTreatment;

describe('LineSet — acceptés', function () {
    it('accepte une note TTC (forme hôtelière)', function () {
        $set = Parts::ttc();

        expect($set->total()->amount)->toBe(11925)
            ->and($set->netTotal()?->amount)->toBe(10000)
            ->and($set->taxTotal()?->amount)->toBe(1925);
    });

    it('accepte une vente HT avec remise sur le total (forme restaurant)', function () {
        $set = Parts::restaurantHt();

        expect($set->total()->amount)->toBe(14310)
            ->and($set->netTotal()?->amount)->toBe(12000)
            ->and($set->taxTotal()?->amount)->toBe(2310);
    });

    it('accepte une ligne non configurée en TTC, sans base ni taxe connues', function () {
        $set = Parts::set(
            [Parts::line(5000, treatment: TaxTreatment::NotConfigured, basisPoints: null)],
            [Parts::bucket(5000, null, null, TaxTreatment::NotConfigured, null)],
        );

        expect($set->total()->amount)->toBe(5000)->and($set->netTotal())->toBeNull()->and($set->taxTotal())->toBeNull();
    });

    it('accepte plusieurs cases, chacune avec sa ligne', function () {
        $set = Parts::set(
            [Parts::line(11925, 'l1'), Parts::line(500, 'l2', treatment: TaxTreatment::OutOfScope, basisPoints: null)],
            [Parts::bucket(11925, 10000, 1925), Parts::bucket(500, 500, 0, TaxTreatment::OutOfScope, null)],
        );

        expect($set->total()->amount)->toBe(12425);
    });

    it('L7 : accepte une remise de 100 % qualifiée, détaillée ligne par ligne', function () {
        $set = Parts::compensated(Parts::gift());

        expect($set->lines)->toHaveCount(2)
            ->and($set->total()->isZero())->toBeTrue()
            ->and($set->zeroQualification?->reason->code)->toBe('commercial_gesture');
    });

    it('L7 : accepte une remise sur le total qualifiée, prix de référence absent', function () {
        $gift = new TontonPro\Contracts\Value\Complimentary(TontonPro\Contracts\Vocabulary\ComplimentaryReason::serviceRecovery(), Values::manager());

        expect(Parts::set([Parts::line(5000)], [Parts::bucket(0, 0, 0)], 5000, PriceBasis::TaxIncluded, $gift)->discount->amount)->toBe(5000);
    });

    it('L7 : une gratuité ligne par ligne n\'exige pas de qualification d\'ensemble', function () {
        expect(Parts::freeSet()->zeroQualification)->toBeNull();
    });
});

describe('LineSet — L7 refusés', function () {
    it('refuse une remise de 100 % sans qualification', function () {
        expect(violation(fn () => Parts::compensated()))->toBe(ViolationCode::ZeroTotalWithoutQualification)
            ->and(violation(fn () => Parts::set([Parts::line(5000)], [Parts::bucket(0, 0, 0)], 5000)))->toBe(ViolationCode::ZeroTotalWithoutQualification);
    });

    it('refuse une qualification sur un total positif', function () {
        expect(violation(fn () => Parts::set([Parts::line(11925)], [Parts::bucket(11925, 10000, 1925)], 0, PriceBasis::TaxIncluded, Parts::gift())))
            ->toBe(ViolationCode::ZeroQualificationUnexpected);
    });

    it('refuse un prix de référence dans une autre devise que les lignes', function () {
        $gift = new TontonPro\Contracts\Value\Complimentary(
            TontonPro\Contracts\Vocabulary\ComplimentaryReason::commercialGesture(), Values::manager(), Values::money(50, Values::eur()),
        );

        expect(violation(fn () => Parts::compensated($gift)))->toBe(ViolationCode::ComplimentaryReferencePriceInvalid);
    });
});

describe('LineSet — refusés', function () {
    it('L1 : refuse un ensemble sans ligne de prestation', function () {
        expect(violation(fn () => Parts::set([], [])))->toBe(ViolationCode::LinesEmpty)
            ->and(violation(fn () => Parts::set([Parts::line(100, kind: LineKind::Deduction)], [Parts::bucket(0, 0, 0)])))->toBe(ViolationCode::LinesEmpty);
    });

    it('L2 : refuse deux lignes de même référence', function () {
        expect(violation(fn () => Parts::set([Parts::line(100), Parts::line(100)], [Parts::bucket(200, 168, 32)])))->toBe(ViolationCode::LineRefDuplicate);
    });

    it('L3 : refuse deux devises', function () {
        expect(violation(fn () => Parts::set([Parts::line(100, currency: Values::eur())], [Parts::bucket(100, 84, 16)])))->toBe(ViolationCode::CurrencyMismatch)
            ->and(violation(fn () => Parts::set([Parts::line(100)], [Parts::bucket(100, 84, 16, currency: Values::eur())])))->toBe(ViolationCode::CurrencyMismatch);
    });

    it('L4 : refuse une ventilation qui ne correspond pas aux lignes', function (Closure $build) {
        expect(violation($build))->toBe(ViolationCode::TaxBreakdownMismatch);
    })->with([
        'case en double' => fn () => Parts::set([Parts::line(100)], [Parts::bucket(50, 42, 8), Parts::bucket(50, 42, 8)]),
        'ligne sans case' => fn () => Parts::set([Parts::line(100)], [Parts::bucket(100, 100, 0, TaxTreatment::Exempt, null)]),
        'case sans ligne' => fn () => Parts::set([Parts::line(100)], [Parts::bucket(100, 84, 16), Parts::bucket(0, 0, 0, TaxTreatment::Exempt, null)]),
    ]);

    it('L5 : refuse un net négatif', function () {
        expect(violation(fn () => Parts::set([Parts::line(100), Parts::line(200, 'r1', LineKind::Deduction)], [Parts::bucket(0, 0, 0)])))
            ->toBe(ViolationCode::LinesTotalNegative)
            ->and(violation(fn () => Parts::set([Parts::line(100)], [Parts::bucket(0, 0, 0)], 200)))->toBe(ViolationCode::LinesTotalNegative);
    });

    it('L6 : refuse des lignes qui ne se réconcilient pas avec la ventilation', function (Closure $build) {
        expect(violation($build))->toBe(ViolationCode::LinesBreakdownUnreconciled);
    })->with([
        'TTC' => fn () => Parts::set([Parts::line(11925)], [Parts::bucket(11900, 10000, 1900)]),
        'HT' => fn () => Parts::set([Parts::line(10000)], [Parts::bucket(11925, 9999, 1926)], 0, PriceBasis::TaxExcluded),
        'HT oubliant la remise' => fn () => Parts::set([Parts::line(10000)], [Parts::bucket(11925, 10000, 1925)], 1000, PriceBasis::TaxExcluded),
        'HT non configuré' => fn () => Parts::set(
            [Parts::line(5000, treatment: TaxTreatment::NotConfigured, basisPoints: null)],
            [Parts::bucket(5000, null, null, TaxTreatment::NotConfigured, null)],
            0,
            PriceBasis::TaxExcluded,
        ),
    ]);

    it('exige un total strictement positif quand le fait le demande', function () {
        expect(violation(fn () => Parts::freeSet()->requirePositiveTotal('charges')))->toBe(ViolationCode::LinesTotalNotPositive)
            ->and(violation(fn () => Parts::ttc()->requirePositiveTotal('charges')))->toBeNull();
    });
});
