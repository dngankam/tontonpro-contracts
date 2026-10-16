<?php

declare(strict_types=1);

use TontonPro\Contracts\Company\SameCompanyAnswer;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Value\TaxRate;
use TontonPro\Contracts\Vocabulary\AdjustmentDirection;
use TontonPro\Contracts\Vocabulary\AdjustmentReason;
use TontonPro\Contracts\Vocabulary\AllocationNature;
use TontonPro\Contracts\Vocabulary\BillingStatus;
use TontonPro\Contracts\Vocabulary\CancellationReason;
use TontonPro\Contracts\Vocabulary\ComplimentaryReason;
use TontonPro\Contracts\Vocabulary\CreditNoteReason;
use TontonPro\Contracts\Vocabulary\CreditScope;
use TontonPro\Contracts\Vocabulary\LineKind;
use TontonPro\Contracts\Vocabulary\MovementNature;
use TontonPro\Contracts\Vocabulary\PartyKind;
use TontonPro\Contracts\Vocabulary\PaymentMeansKind;
use TontonPro\Contracts\Vocabulary\PriceBasis;
use TontonPro\Contracts\Vocabulary\RateRole;
use TontonPro\Contracts\Vocabulary\TaxTreatment;
use TontonPro\Contracts\Vocabulary\TreasuryDirection;

function vocabulary(string $class, string $code, ?string $why = null): CreditNoteReason|CancellationReason|AdjustmentReason|ComplimentaryReason|PaymentMeansKind
{
    return match ($class) {
        CreditNoteReason::class => new CreditNoteReason($code, $why),
        CancellationReason::class => new CancellationReason($code, $why),
        AdjustmentReason::class => new AdjustmentReason($code, $why),
        ComplimentaryReason::class => new ComplimentaryReason($code, $why),
        PaymentMeansKind::class => new PaymentMeansKind($code, $why),
        default => throw new LogicException($class),
    };
}

dataset('régime A', [
    'CreditNoteReason' => [CreditNoteReason::class, 'pricing_error'],
    'CancellationReason' => [CancellationReason::class, 'entry_error'],
    'AdjustmentReason' => [AdjustmentReason::class, 'early_departure'],
    'ComplimentaryReason' => [ComplimentaryReason::class, 'commercial_gesture'],
    'PaymentMeansKind' => [PaymentMeansKind::class, 'cash'],
]);

describe('régime A — ouvert, justifié', function () {
    it('accepte une valeur connue sans justification', function (string $class, string $known) {
        $value = vocabulary($class, $known);

        expect($value->isKnown())->toBeTrue()->and($value->toArray())->toBe(['code' => $known, 'justification' => null]);
    })->with('régime A');

    it('refuse une valeur inconnue non espacée par module', function (string $class) {
        expect(violation(fn () => vocabulary($class, 'overbooking_gesture', 'raison')))->toBe(ViolationCode::VocabularyCodeUnknown);
    })->with('régime A');

    it('refuse une valeur étendue sans justification', function (string $class) {
        expect(violation(fn () => vocabulary($class, 'x.hotelpro.overbooking_gesture')))->toBe(ViolationCode::VocabularyJustificationMissing)
            ->and(violation(fn () => vocabulary($class, 'x.hotelpro.overbooking_gesture', '  ')))->toBe(ViolationCode::VocabularyJustificationMissing);
    })->with('régime A');

    it('accepte une valeur étendue et justifiée, qui n\'est pas connue', function (string $class) {
        $value = vocabulary($class, 'x.hotelpro.overbooking_gesture', 'Surréservation compensée');

        expect($value->isKnown())->toBeFalse()->and($value->justification)->toBe('Surréservation compensée');
    })->with('régime A');
});

describe('listes du régime A', function () {
    it('porte les sept motifs d\'avoir', function () {
        expect(CreditNoteReason::knownCodes())->toBe([
            'commercial_discount', 'pricing_error', 'tax_treatment_error', 'service_cancelled',
            'goods_returned', 'wrong_debtor_replaced', 'duplicate_invoice',
        ])->and(CreditNoteReason::duplicateInvoice()->code)->toBe('duplicate_invoice');
    });

    it('porte les listes provisoires d\'annulation, d\'ajustement et de gratuité', function () {
        expect(CancellationReason::knownCodes())->toBe(['entry_error', 'duplicate_sale', 'not_served', 'customer_dispute'])
            ->and(AdjustmentReason::knownCodes())->toBe(['entry_error', 'early_departure', 'service_not_rendered', 'charge_reversed'])
            ->and(ComplimentaryReason::knownCodes())->toBe(['commercial_gesture', 'service_recovery', 'promotional_invitation', 'staff_benefit']);
    });

    it('porte les moyens de paiement, sans report en chambre ni prise en charge', function () {
        expect(PaymentMeansKind::knownCodes())->toBe(['cash', 'card', 'cheque', 'electronic_money']);
    });

    it('offre un constructeur nommé par valeur connue', function () {
        $named = [
            CreditNoteReason::commercialDiscount(), CreditNoteReason::pricingError(), CreditNoteReason::taxTreatmentError(),
            CreditNoteReason::serviceCancelled(), CreditNoteReason::goodsReturned(), CreditNoteReason::wrongDebtorReplaced(),
            CancellationReason::entryError(), CancellationReason::duplicateSale(), CancellationReason::notServed(), CancellationReason::customerDispute(),
            AdjustmentReason::entryError(), AdjustmentReason::earlyDeparture(), AdjustmentReason::serviceNotRendered(), AdjustmentReason::chargeReversed(),
            ComplimentaryReason::commercialGesture(), ComplimentaryReason::serviceRecovery(), ComplimentaryReason::promotionalInvitation(), ComplimentaryReason::staffBenefit(),
            PaymentMeansKind::cash(), PaymentMeansKind::card(), PaymentMeansKind::cheque(), PaymentMeansKind::electronicMoney(),
        ];

        foreach ($named as $value) {
            expect($value->isKnown())->toBeTrue();
        }
    });
});

describe('régime B — fermé, étendu par version', function () {
    it('a exactement les valeurs annoncées', function (string $enum, array $values) {
        /** @var class-string<BackedEnum> $enum */
        expect(array_map(fn (BackedEnum $case) => $case->value, $enum::cases()))->toBe($values);
    })->with([
        'TaxTreatment' => [TaxTreatment::class, ['taxable', 'zero_rated', 'exempt', 'out_of_scope', 'franchise', 'not_configured']],
        'MovementNature' => [MovementNature::class, ['received', 'change_returned', 'paid_out']],
        'AllocationNature' => [AllocationNature::class, ['settle_receivable', 'advance', 'tip_payable', 'customer_balance', 'security_deposit']],
        'RateRole' => [RateRole::class, ['proposed', 'accounting', 'bank_executed']],
        'PriceBasis' => [PriceBasis::class, ['tax_included', 'tax_excluded']],
        'LineKind' => [LineKind::class, ['charge', 'deduction']],
        'CreditScope' => [CreditScope::class, ['partial', 'total']],
        'AdjustmentDirection' => [AdjustmentDirection::class, ['decrease', 'increase']],
        'BillingStatus' => [BillingStatus::class, ['not_invoiced', 'invoiced_in_advance']],
        'PartyKind' => [PartyKind::class, ['person', 'organization']],
        'TreasuryDirection' => [TreasuryDirection::class, ['collection', 'disbursement']],
        'SameCompanyAnswer' => [SameCompanyAnswer::class, ['same', 'different', 'unresolved', 'no_authority']],
    ]);

    it('accorde taux et traitement fiscal', function () {
        expect(TaxTreatment::Taxable->acceptsRate(new TaxRate(1925)))->toBeTrue()
            ->and(TaxTreatment::Taxable->acceptsRate(new TaxRate(0)))->toBeFalse()
            ->and(TaxTreatment::Taxable->acceptsRate(null))->toBeFalse()
            ->and(TaxTreatment::ZeroRated->acceptsRate(new TaxRate(0)))->toBeTrue()
            ->and(TaxTreatment::ZeroRated->acceptsRate(null))->toBeFalse()
            ->and(TaxTreatment::Exempt->acceptsRate(null))->toBeTrue()
            ->and(TaxTreatment::OutOfScope->acceptsRate(new TaxRate(0)))->toBeFalse();
    });
});
