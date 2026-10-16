<?php

declare(strict_types=1);

use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Facts\Parts\Line;
use TontonPro\Contracts\Tests\Support\Parts;
use TontonPro\Contracts\Tests\Support\Values;
use TontonPro\Contracts\Value\Quantity;
use TontonPro\Contracts\Vocabulary\LineKind;
use TontonPro\Contracts\Vocabulary\TaxTreatment;

describe('Line', function () {
    it('accepte une ligne de prestation et une remise', function () {
        expect(Parts::line(11925)->amount->amount)->toBe(11925)
            ->and(Parts::line(500, 'r1', LineKind::Deduction)->kind)->toBe(LineKind::Deduction);
    });

    it('refuse une référence, un libellé ou une catégorie invalides', function (string $ref, string $label, string $category) {
        expect(violation(fn () => new Line($ref, LineKind::Charge, $label, new Quantity('1'), $category, Values::money(100), TaxTreatment::Exempt)))
            ->toBe(ViolationCode::LineInvalid);
    })->with([
        'référence' => ['l 1', 'Repas', 'REST'],
        'libellé' => ['l1', ' ', 'REST'],
        'catégorie' => ['l1', 'Repas', 'RE ST'],
    ]);

    it('accepte chaque traitement avec son taux', function (TaxTreatment $treatment, ?int $basisPoints) {
        expect(Parts::line(100, treatment: $treatment, basisPoints: $basisPoints)->taxTreatment)->toBe($treatment);
    })->with([
        [TaxTreatment::Taxable, 1925],
        [TaxTreatment::ZeroRated, 0],
        [TaxTreatment::Exempt, null],
        [TaxTreatment::OutOfScope, null],
        [TaxTreatment::Franchise, null],
        [TaxTreatment::NotConfigured, null],
    ]);

    it('refuse un taux qui ne s\'accorde pas avec le traitement', function (TaxTreatment $treatment, ?int $basisPoints) {
        expect(violation(fn () => Parts::line(100, treatment: $treatment, basisPoints: $basisPoints)))->toBe(ViolationCode::LineTaxIncoherent);
    })->with([
        'taxable sans taux' => [TaxTreatment::Taxable, null],
        'taxable à 0' => [TaxTreatment::Taxable, 0],
        'taux zéro avec un taux' => [TaxTreatment::ZeroRated, 1925],
        'exonéré avec un taux' => [TaxTreatment::Exempt, 0],
        'non configuré avec un taux' => [TaxTreatment::NotConfigured, 1925],
    ]);
});

describe('TaxBucket', function () {
    it('accepte une case taxable, une case à taux zéro et une case non configurée', function () {
        expect(Parts::bucket(11925, 10000, 1925)->key())->toBe('taxable|1925')
            ->and(Parts::bucket(5000, 5000, 0, TaxTreatment::ZeroRated, 0)->key())->toBe('zero_rated|0')
            ->and(Parts::bucket(5000, null, null, TaxTreatment::NotConfigured, null)->key())->toBe('not_configured|-')
            ->and(Parts::bucket(1, 1, 0)->tax?->amount)->toBe(0);
    });

    it('refuse une case incohérente', function (Closure $build) {
        expect(violation($build))->toBe(ViolationCode::TaxBucketIncoherent);
    })->with([
        'base + taxe ≠ TTC' => fn () => Parts::bucket(11925, 10000, 1900),
        'non configurée avec une base' => fn () => Parts::bucket(5000, 5000, 0, TaxTreatment::NotConfigured, null),
        'connue sans base' => fn () => Parts::bucket(5000, null, null, TaxTreatment::Exempt, null),
        'exonérée avec une taxe' => fn () => Parts::bucket(5000, 4000, 1000, TaxTreatment::Exempt, null),
        'taux incohérent' => fn () => Parts::bucket(5000, 5000, 0, TaxTreatment::Taxable, 0),
    ]);

    it('refuse deux devises dans une case', function () {
        expect(violation(fn () => new TontonPro\Contracts\Facts\Parts\TaxBucket(
            TaxTreatment::Exempt, null, Values::money(100, Values::eur()), Values::money(0), Values::money(100),
        )))->toBe(ViolationCode::CurrencyMismatch);
    });
});
