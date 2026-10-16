<?php

declare(strict_types=1);

use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Facts\CreditNoteIssued;
use TontonPro\Contracts\Facts\InvoiceIssued;
use TontonPro\Contracts\Facts\ServiceConsumed;
use TontonPro\Contracts\Tests\Support\Facts;
use TontonPro\Contracts\Tests\Support\Parts;
use TontonPro\Contracts\Tests\Support\Values;
use TontonPro\Contracts\Value\FactReference;
use TontonPro\Contracts\Value\Money;
use TontonPro\Contracts\Vocabulary\BillingStatus;
use TontonPro\Contracts\Vocabulary\CreditNoteReason;
use TontonPro\Contracts\Vocabulary\CreditScope;

function creditNote(
    string $series = 'AV',
    ?FactReference $original = null,
    ?Money $originalTotal = null,
    CreditScope $scope = CreditScope::Partial,
    string $explanation = 'Erreur de prix',
    ?FactReference $replacement = null,
): CreditNoteIssued {
    return new CreditNoteIssued(
        Values::hotelHeader('credit_note:9'), $series, '2026-0001', $original ?? Values::ref('invoice.issued', 'invoice:88'),
        'FA', '2026-0001', $originalTotal ?? Values::money(11925), $scope, CreditNoteReason::pricingError(),
        $explanation, Values::party(), $replacement, Parts::ttc(5963, 5000, 963),
    );
}

describe('ServiceConsumed', function () {
    it('construit une nuitée consommée datée de la journée', function () {
        expect(Facts::serviceConsumed()->idempotencyKey()->toString())->toBe('hotelpro|room_night:9012|service.consumed|1');
    });

    it('accepte une prestation facturée d\'avance qui désigne sa facture', function () {
        expect(new ServiceConsumed(
            Values::hotelHeader('room_night:9012'), Values::source('stay:4', 'hotelpro'), Values::source('folio:3', 'hotelpro'),
            Values::party(), Parts::ttc(serviceDate: '2026-10-16'), BillingStatus::InvoicedInAdvance, Values::source('invoice:80', 'hotelpro'),
        ))->toBeInstanceOf(ServiceConsumed::class);
    });

    it('refuse une ligne datée d\'une autre journée, ou sans date', function (?string $date) {
        expect(violation(fn () => Facts::serviceConsumed(Parts::ttc(serviceDate: $date))))->toBe(ViolationCode::ServiceDateMismatch);
    })->with(['2026-10-15', null]);

    it('refuse un état de facturation incohérent avec la facture d\'avance', function (BillingStatus $status, ?string $invoice) {
        expect(violation(fn () => new ServiceConsumed(
            Values::hotelHeader('room_night:9012'), Values::source('stay:4', 'hotelpro'), Values::source('folio:3', 'hotelpro'),
            Values::party(), Parts::ttc(serviceDate: '2026-10-16'), $status, $invoice === null ? null : Values::source($invoice, 'hotelpro'),
        )))->toBe(ViolationCode::BillingStatusIncoherent);
    })->with([
        'facturée d\'avance sans facture' => [BillingStatus::InvoicedInAdvance, null],
        'non facturée avec une facture' => [BillingStatus::NotInvoiced, 'invoice:80'],
    ]);

    it('refuse des lignes hors de la monnaie de l\'établissement', function () {
        expect(violation(fn () => new ServiceConsumed(
            Values::header(Values::source('room_night:1', 'hotelpro'), Values::hotel(Values::eur())), Values::source('stay:4', 'hotelpro'),
            Values::source('folio:3', 'hotelpro'), Values::party(), Parts::ttc(serviceDate: '2026-10-16'), BillingStatus::NotInvoiced,
        )))->toBe(ViolationCode::CurrencyMismatch);
    });
});

describe('InvoiceIssued', function () {
    it('construit une facture et sa clé', function () {
        expect(Facts::invoiceIssued()->idempotencyKey()->toString())->toBe('hotelpro|invoice:88|invoice.issued|1');
    });

    it('refuse une série ou un numéro vides, une autre devise', function (Closure $build, ViolationCode $code) {
        expect(violation($build))->toBe($code);
    })->with([
        'série' => [fn () => new InvoiceIssued(Values::hotelHeader('invoice:88'), '', '1', Values::party(), null, Parts::ttc()), ViolationCode::DocumentNumberMissing],
        'numéro' => [fn () => new InvoiceIssued(Values::hotelHeader('invoice:88'), 'FA', ' ', Values::party(), null, Parts::ttc()), ViolationCode::DocumentNumberMissing],
        'devise' => [fn () => new InvoiceIssued(Values::header(establishment: Values::hotel(Values::eur())), 'FA', '1', Values::party(), null, Parts::ttc()), ViolationCode::CurrencyMismatch],
    ]);
});

describe('CreditNoteIssued', function () {
    it('accepte un avoir partiel et un avoir total avec remplacement', function () {
        expect(Facts::creditNoteIssued()->charges->total()->amount)->toBe(5963)
            ->and(creditNote(originalTotal: Values::money(5963), scope: CreditScope::Total, replacement: Values::ref('invoice.issued', 'invoice:90')))
            ->toBeInstanceOf(CreditNoteIssued::class);
    });

    it('refuse un avoir dans la série des factures', function () {
        expect(violation(fn () => creditNote(series: 'FA')))->toBe(ViolationCode::CreditNoteSeriesNotDistinct);
    });

    it('refuse un avoir qui dépasse la facture', function () {
        expect(violation(fn () => creditNote(originalTotal: Values::money(5000))))->toBe(ViolationCode::CreditNoteExceedsInvoice);
    });

    it('refuse une portée incohérente avec le montant', function () {
        expect(violation(fn () => creditNote(scope: CreditScope::Total)))->toBe(ViolationCode::CreditNoteScopeIncoherent)
            ->and(violation(fn () => creditNote(originalTotal: Values::money(5963))))->toBe(ViolationCode::CreditNoteScopeIncoherent);
    });

    it('refuse une origine ou un remplacement qui ne sont pas des factures', function () {
        expect(violation(fn () => creditNote(original: Values::ref('sale.completed', 'sell:1'))))->toBe(ViolationCode::FactReferenceTypeInvalid)
            ->and(violation(fn () => creditNote(replacement: Values::ref('sale.completed', 'sell:1'))))->toBe(ViolationCode::FactReferenceTypeInvalid);
    });

    it('refuse une explication vide et un total d\'origine nul', function () {
        expect(violation(fn () => creditNote(explanation: '')))->toBe(ViolationCode::ExplanationMissing)
            ->and(violation(fn () => creditNote(originalTotal: Values::money(0))))->toBe(ViolationCode::AmountNotPositive);
    });
});

describe('LinkedAdjustment', function () {
    it('accepte la correction d\'une prestation passée ou du jour', function (string $date) {
        expect(Facts::linkedAdjustment($date)->idempotencyKey()->toString())->toBe('hotelpro|adjustment:4|adjustment.linked|1');
    })->with(['2026-10-16', '2026-10-17']);

    it('refuse de corriger une prestation à venir', function () {
        expect(violation(fn () => Facts::linkedAdjustment('2026-10-18')))->toBe(ViolationCode::AdjustmentBeforeService);
    });

    it('refuse un ajustement sans explication', function () {
        expect(violation(fn () => new TontonPro\Contracts\Facts\LinkedAdjustment(
            Values::hotelHeader('adjustment:4'), Values::ref('service.consumed', 'room_night:9012'),
            TontonPro\Contracts\Vocabulary\AdjustmentReason::chargeReversed(), ' ', Values::date(),
            TontonPro\Contracts\Vocabulary\AdjustmentDirection::Increase, Parts::ttc(),
        )))->toBe(ViolationCode::ExplanationMissing);
    });
});
