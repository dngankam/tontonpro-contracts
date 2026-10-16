<?php

declare(strict_types=1);

use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Facts\SecurityDepositApplied;
use TontonPro\Contracts\Facts\SecurityDepositReturned;
use TontonPro\Contracts\Tests\Support\Facts;
use TontonPro\Contracts\Tests\Support\Parts;
use TontonPro\Contracts\Tests\Support\Values;
use TontonPro\Contracts\Vocabulary\AllocationNature;

describe('PaymentRecorded', function () {
    it('accepte l\'encaissement de l\'expert, avec taux comptable et dérogation', function () {
        expect(Facts::paymentRecorded()->idempotencyKey()->toString())->toBe('hotelpro|payment:771|payment.recorded|1')
            ->and(Facts::paymentWithAccountingRate()->operation->movements[0]->accountingCounterValue?->amount)->toBe(65596);
    });

    it('refuse un décaissement', function () {
        expect(violation(fn () => Facts::paymentRecorded(Parts::payout(AllocationNature::Advance))))->toBe(ViolationCode::TreasuryDirectionInvalid);
    });

    it('refuse une affectation de caution', function () {
        expect(violation(fn () => Facts::paymentRecorded(Facts::securityDepositReceived()->operation)))->toBe(ViolationCode::AllocationNatureNotAllowed);
    });

    it('refuse des contre-valeurs hors de la monnaie de l\'établissement', function () {
        expect(violation(fn () => new TontonPro\Contracts\Facts\PaymentRecorded(
            Values::header(Values::source('payment:1', 'hotelpro'), Values::hotel(Values::eur())), Parts::expert(), null, null,
        )))->toBe(ViolationCode::CurrencyMismatch);
    });
});

describe('RefundPaid', function () {
    it('accepte le remboursement d\'un acompte et d\'une somme due', function (AllocationNature $nature) {
        expect(Facts::refundPaid(Parts::payout($nature))->explanation)->toBe('Séjour annulé dans les délais');
    })->with([AllocationNature::Advance, AllocationNature::CustomerBalance]);

    it('refuse un encaissement, une nature non permise, une explication vide', function () {
        expect(violation(fn () => Facts::refundPaid(Parts::expert())))->toBe(ViolationCode::TreasuryDirectionInvalid)
            ->and(violation(fn () => Facts::refundPaid(Parts::payout(AllocationNature::SecurityDeposit))))->toBe(ViolationCode::AllocationNatureNotAllowed)
            ->and(violation(fn () => new TontonPro\Contracts\Facts\RefundPaid(
                Values::hotelHeader('refund:3'), Parts::payout(AllocationNature::Advance), null, ' ', null,
            )))->toBe(ViolationCode::ExplanationMissing);
    });
});

describe('cautions', function () {
    it('accepte une caution reçue, restituée et affectée', function () {
        expect(Facts::securityDepositReceived()->idempotencyKey()->toString())->toBe('hotelpro|deposit:2|security_deposit.received|1')
            ->and(Facts::securityDepositReturned()->deposit->factType)->toBe('security_deposit.received')
            ->and(Facts::securityDepositApplied()->amount->amount)->toBe(11925);
    });

    it('refuse une caution reçue qui règle autre chose qu\'une caution, ou qui décaisse', function () {
        expect(violation(fn () => Facts::securityDepositReceived(Parts::expert())))->toBe(ViolationCode::AllocationNatureNotAllowed)
            ->and(violation(fn () => Facts::securityDepositReceived(Parts::payout(AllocationNature::SecurityDeposit))))->toBe(ViolationCode::TreasuryDirectionInvalid);
    });

    it('refuse une restitution qui encaisse, ou qui rend autre chose qu\'une caution', function () {
        expect(violation(fn () => Facts::securityDepositReturned(Facts::securityDepositReceived()->operation)))->toBe(ViolationCode::TreasuryDirectionInvalid)
            ->and(violation(fn () => Facts::securityDepositReturned(Parts::payout(AllocationNature::Advance))))->toBe(ViolationCode::AllocationNatureNotAllowed);
    });

    it('refuse une restitution ou une affectation qui ne désignent pas une caution reçue', function () {
        expect(violation(fn () => new SecurityDepositReturned(
            Values::hotelHeader('deposit_return:1'), Values::ref('payment.recorded', 'payment:1'), Parts::payout(AllocationNature::SecurityDeposit),
        )))->toBe(ViolationCode::FactReferenceTypeInvalid)
            ->and(violation(fn () => new SecurityDepositApplied(
                Values::hotelHeader('deposit_use:1'), Values::ref('payment.recorded', 'payment:1'), Values::ref('invoice.issued', 'invoice:88'), Values::money(100),
            )))->toBe(ViolationCode::FactReferenceTypeInvalid);
    });

    it('refuse une affectation de caution vers autre chose qu\'une facture, nulle ou en devise', function (Closure $build, ViolationCode $code) {
        expect(violation($build))->toBe($code);
    })->with([
        'pas une facture' => [fn () => new SecurityDepositApplied(Values::hotelHeader('deposit_use:1'), Facts::deposit(), Values::ref('sale.completed', 'sell:1'), Values::money(100)), ViolationCode::FactReferenceTypeInvalid],
        'nulle' => [fn () => new SecurityDepositApplied(Values::hotelHeader('deposit_use:1'), Facts::deposit(), Values::ref('invoice.issued', 'invoice:88'), Values::money(0)), ViolationCode::AmountNotPositive],
        'en devise' => [fn () => new SecurityDepositApplied(Values::hotelHeader('deposit_use:1'), Facts::deposit(), Values::ref('invoice.issued', 'invoice:88'), Values::money(100, Values::eur())), ViolationCode::CurrencyMismatch],
    ]);
});
