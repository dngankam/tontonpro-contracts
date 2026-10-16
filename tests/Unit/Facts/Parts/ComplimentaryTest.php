<?php

declare(strict_types=1);

use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Facts\InvoiceIssued;
use TontonPro\Contracts\Facts\Parts\LineSet;
use TontonPro\Contracts\Facts\SaleCompleted;
use TontonPro\Contracts\Facts\ServiceConsumed;
use TontonPro\Contracts\Tests\Support\Facts;
use TontonPro\Contracts\Tests\Support\Parts;
use TontonPro\Contracts\Tests\Support\Values;
use TontonPro\Contracts\Value\Complimentary;
use TontonPro\Contracts\Vocabulary\ComplimentaryReason;
use TontonPro\Contracts\Vocabulary\CreditScope;
use TontonPro\Contracts\Vocabulary\LineKind;
use TontonPro\Contracts\Vocabulary\PriceBasis;
use TontonPro\Contracts\Vocabulary\TaxTreatment;

describe('Complimentary', function () {
    it('accepte une gratuité avec ou sans prix de référence', function () {
        expect(Parts::gift()->referencePrice?->amount)->toBe(15000)
            ->and((new Complimentary(ComplimentaryReason::staffBenefit(), Values::manager()))->referencePrice)->toBeNull();
    });

    it('refuse un prix de référence nul : on n\'invente pas de prix', function () {
        expect(violation(fn () => new Complimentary(ComplimentaryReason::serviceRecovery(), Values::manager(), Values::money(0))))
            ->toBe(ViolationCode::ComplimentaryReferencePriceInvalid);
    });
});

describe('ligne à 0', function () {
    it('accepte une ligne à 0 qualifiée de gratuité, taxable ou hors champ', function () {
        expect(Parts::line(0, complimentary: Parts::gift())->complimentary)->not->toBeNull()
            ->and(Parts::line(0, treatment: TaxTreatment::OutOfScope, basisPoints: null, complimentary: Parts::gift())->amount->isZero())->toBeTrue();
    });

    it('refuse une ligne à 0 sans qualification', function () {
        expect(violation(fn () => Parts::line(0)))->toBe(ViolationCode::ZeroChargeWithoutQualification)
            ->and(violation(fn () => Parts::line(0, treatment: TaxTreatment::NotConfigured, basisPoints: null)))->toBe(ViolationCode::ZeroChargeWithoutQualification);
    });

    it('refuse une qualification sur un traitement fiscal non configuré', function () {
        expect(violation(fn () => Parts::line(0, treatment: TaxTreatment::NotConfigured, basisPoints: null, complimentary: Parts::gift())))
            ->toBe(ViolationCode::ComplimentaryTaxNotConfigured);
    });

    it('refuse une qualification sur une ligne qui ne vaut pas 0', function () {
        expect(violation(fn () => Parts::line(15000, complimentary: Parts::gift())))->toBe(ViolationCode::ComplimentaryAmountNotZero);
    });

    it('refuse une qualification sur une remise', function () {
        expect(violation(fn () => Parts::line(0, kind: LineKind::Deduction, complimentary: Parts::gift())))->toBe(ViolationCode::ComplimentaryNotOnCharge);
    });

    it('refuse un prix de référence dans une autre devise que la ligne', function () {
        $gift = new Complimentary(ComplimentaryReason::commercialGesture(), Values::manager(), Values::money(2000, Values::eur()));

        expect(violation(fn () => Parts::line(0, complimentary: $gift)))->toBe(ViolationCode::ComplimentaryReferencePriceInvalid);
    });

    it('refuse un prix de référence hors de la devise de l\'établissement', function () {
        $gift = new Complimentary(ComplimentaryReason::commercialGesture(), Values::manager(), Values::money(2000, Values::eur()));
        $eurLines = new LineSet(
            PriceBasis::TaxIncluded,
            [Parts::line(0, complimentary: $gift, currency: Values::eur())],
            Values::money(0, Values::eur()),
            [Parts::bucket(0, 0, 0, currency: Values::eur())],
        );

        expect(violation(fn () => Facts::saleCompleted($eurLines)))->toBe(ViolationCode::CurrencyMismatch);
    });
});

describe('faits à total nul', function () {
    it('accepte un repas offert (vente à 0)', function () {
        $sale = Facts::saleCompleted(Parts::freeSet());

        expect($sale)->toBeInstanceOf(SaleCompleted::class)->and($sale->charges->total()->isZero())->toBeTrue();
    });

    it('accepte une nuitée offerte (prestation consommée à 0)', function () {
        expect(Facts::serviceConsumed(Parts::freeSet('2026-10-16')))->toBeInstanceOf(ServiceConsumed::class);
    });

    it('accepte une facture à 0', function () {
        expect(Facts::invoiceIssued(Parts::freeSet()))->toBeInstanceOf(InvoiceIssued::class);
    });

    it('refuse un avoir à 0', function () {
        expect(violation(fn () => Facts::creditNoteIssued(Parts::freeSet())))->toBe(ViolationCode::LinesTotalNotPositive)
            ->and(violation(fn () => Facts::creditNoteIssued(Parts::freeSet(), CreditScope::Total)))->toBe(ViolationCode::LinesTotalNotPositive);
    });

    it('refuse un report en chambre à 0', function () {
        $free = new LineSet(
            PriceBasis::TaxIncluded,
            [Parts::line(0, recognizedBy: Facts::originSale(), complimentary: Parts::gift())],
            Values::money(0),
            [Parts::bucket(0, 0, 0)],
        );

        expect(violation(fn () => Facts::roomChargeAttached(charges: $free)))->toBe(ViolationCode::LinesTotalNotPositive);
    });

    it('refuse un ajustement à 0 et un avoir compensé à 0, même qualifié', function () {
        expect(violation(fn () => Facts::creditNoteIssued(Parts::compensated(Parts::gift()))))->toBe(ViolationCode::LinesTotalNotPositive)
            ->and(violation(fn () => new TontonPro\Contracts\Facts\LinkedAdjustment(
                Values::hotelHeader('adjustment:4', '2026-10-17'),
                Values::ref('service.consumed', 'room_night:9012'),
                TontonPro\Contracts\Vocabulary\AdjustmentReason::entryError(),
                'Erreur',
                Values::date(),
                TontonPro\Contracts\Vocabulary\AdjustmentDirection::Decrease,
                Parts::freeSet(),
            )))->toBe(ViolationCode::LinesTotalNotPositive);
    });

    it('accepte une vente à 0 par remise de 100 % qualifiée, et garde son détail', function () {
        expect(Facts::saleCompleted(Parts::compensated(Parts::gift()))->charges->lines)->toHaveCount(2);
    });

    it('refuse une vente à 0 par remise de 100 % sans qualification', function () {
        expect(violation(fn () => Facts::saleCompleted(Parts::compensated())))->toBe(ViolationCode::ZeroTotalWithoutQualification);
    });
});
