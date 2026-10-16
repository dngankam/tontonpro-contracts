<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Tests\Support;

use TontonPro\Contracts\Company\SameCompanyAnswer;
use TontonPro\Contracts\Facts\CreditNoteIssued;
use TontonPro\Contracts\Facts\InvoiceIssued;
use TontonPro\Contracts\Facts\LinkedAdjustment;
use TontonPro\Contracts\Facts\MoneyFact;
use TontonPro\Contracts\Facts\Parts\LineSet;
use TontonPro\Contracts\Facts\Parts\TreasuryMovement;
use TontonPro\Contracts\Facts\Parts\TreasuryOperation;
use TontonPro\Contracts\Facts\PaymentRecorded;
use TontonPro\Contracts\Facts\RefundPaid;
use TontonPro\Contracts\Facts\RoomChargeAttached;
use TontonPro\Contracts\Facts\SaleCancelled;
use TontonPro\Contracts\Facts\SaleChargedToRoom;
use TontonPro\Contracts\Facts\SaleCompleted;
use TontonPro\Contracts\Facts\SecurityDepositApplied;
use TontonPro\Contracts\Facts\SecurityDepositReceived;
use TontonPro\Contracts\Facts\SecurityDepositReturned;
use TontonPro\Contracts\Facts\ServiceConsumed;
use TontonPro\Contracts\Value\FactReference;
use TontonPro\Contracts\Value\RateOverride;
use TontonPro\Contracts\Vocabulary\AdjustmentDirection;
use TontonPro\Contracts\Vocabulary\AdjustmentReason;
use TontonPro\Contracts\Vocabulary\AllocationNature;
use TontonPro\Contracts\Vocabulary\BillingStatus;
use TontonPro\Contracts\Vocabulary\CancellationReason;
use TontonPro\Contracts\Vocabulary\CreditNoteReason;
use TontonPro\Contracts\Vocabulary\CreditScope;
use TontonPro\Contracts\Vocabulary\MovementNature;
use TontonPro\Contracts\Vocabulary\RateRole;

/**
 * Fabriques des 13 faits, valides.
 */
final class Facts
{
    public static function saleCompleted(?LineSet $charges = null): SaleCompleted
    {
        return new SaleCompleted(Values::header(), '2026-000123', null, $charges ?? Parts::restaurantHt());
    }

    public static function saleCancelled(): SaleCancelled
    {
        return new SaleCancelled(Values::header(), '2026-000123', Values::money(14310), CancellationReason::entryError(), 'Saisie sur la mauvaise table');
    }

    public static function saleChargedToRoom(SameCompanyAnswer $check = SameCompanyAnswer::NoAuthority): SaleChargedToRoom
    {
        return new SaleChargedToRoom(
            Values::header(),
            '2026-000123',
            Values::money(14310),
            Values::source('folio:3', 'hotelpro'),
            Values::party(),
            Values::hotel(),
            $check,
        );
    }

    public static function originSale(): FactReference
    {
        return Values::ref('sale.completed', 'sell:48213', 'restopro');
    }

    public static function roomChargeAttached(SameCompanyAnswer $check = SameCompanyAnswer::Same, ?LineSet $charges = null): RoomChargeAttached
    {
        return new RoomChargeAttached(
            Values::hotelHeader('room_charge:5'),
            Values::source('folio:3', 'hotelpro'),
            Values::party(),
            self::originSale(),
            Values::outlet(),
            $charges ?? Parts::ttc(recognizedBy: self::originSale()),
            $check,
        );
    }

    public static function serviceConsumed(?LineSet $charges = null): ServiceConsumed
    {
        return new ServiceConsumed(
            Values::hotelHeader('room_night:9012'),
            Values::source('stay:4', 'hotelpro'),
            Values::source('folio:3', 'hotelpro'),
            Values::party(),
            $charges ?? Parts::ttc(serviceDate: '2026-10-16'),
            BillingStatus::NotInvoiced,
        );
    }

    public static function invoiceIssued(?LineSet $charges = null): InvoiceIssued
    {
        return new InvoiceIssued(
            Values::hotelHeader('invoice:88'),
            'FA',
            '2026-0001',
            Values::party(),
            Values::source('folio:3', 'hotelpro'),
            $charges ?? Parts::ttc(),
        );
    }

    public static function creditNoteIssued(?LineSet $charges = null, CreditScope $scope = CreditScope::Partial): CreditNoteIssued
    {
        return new CreditNoteIssued(
            Values::hotelHeader('credit_note:9'),
            'AV',
            '2026-0001',
            Values::ref('invoice.issued', 'invoice:88'),
            'FA',
            '2026-0001',
            Values::money(11925),
            $scope,
            CreditNoteReason::pricingError(),
            'Nuitée facturée au mauvais tarif',
            Values::party(),
            null,
            $charges ?? Parts::ttc(5963, 5000, 963),
        );
    }

    public static function paymentRecorded(?TreasuryOperation $operation = null): PaymentRecorded
    {
        return new PaymentRecorded(Values::hotelHeader('payment:771'), $operation ?? Parts::expert(), null, 'cash_session:5');
    }

    public static function refundPaid(?TreasuryOperation $operation = null): RefundPaid
    {
        return new RefundPaid(
            Values::hotelHeader('refund:3'),
            $operation ?? Parts::payout(AllocationNature::Advance),
            Values::party(),
            'Séjour annulé dans les délais',
            'cash_session:5',
        );
    }

    public static function securityDepositReceived(?TreasuryOperation $operation = null): SecurityDepositReceived
    {
        return new SecurityDepositReceived(
            Values::hotelHeader('deposit:2'),
            Values::source('stay:4', 'hotelpro'),
            Values::party(),
            $operation ?? TreasuryOperation::collection(
                [Parts::movement(50000)],
                [Parts::allocation(AllocationNature::SecurityDeposit, 50000)],
            ),
        );
    }

    public static function deposit(): FactReference
    {
        return Values::ref('security_deposit.received', 'deposit:2');
    }

    public static function securityDepositReturned(?TreasuryOperation $operation = null): SecurityDepositReturned
    {
        return new SecurityDepositReturned(
            Values::hotelHeader('deposit_return:1'),
            self::deposit(),
            $operation ?? Parts::payout(AllocationNature::SecurityDeposit, 50000),
        );
    }

    public static function securityDepositApplied(): SecurityDepositApplied
    {
        return new SecurityDepositApplied(
            Values::hotelHeader('deposit_use:1'),
            self::deposit(),
            Values::ref('invoice.issued', 'invoice:88'),
            Values::money(11925),
        );
    }

    public static function linkedAdjustment(string $originalServiceDate = '2026-10-16'): LinkedAdjustment
    {
        return new LinkedAdjustment(
            Values::hotelHeader('adjustment:4', '2026-10-17'),
            Values::ref('service.consumed', 'room_night:9012'),
            AdjustmentReason::earlyDeparture(),
            'Départ le 16 au lieu du 18',
            Values::date($originalServiceDate),
            AdjustmentDirection::Decrease,
            Parts::ttc(),
        );
    }

    /**
     * Un encaissement qui exerce les champs facultatifs : taux comptable,
     * contre-valeur comptable, dérogation, valeur étendue justifiée.
     */
    public static function paymentWithAccountingRate(): PaymentRecorded
    {
        $movement = new TreasuryMovement(
            'm1',
            MovementNature::Received,
            Values::eurFund(),
            Values::cash(),
            Values::money(10000, Values::eur()),
            Values::money(65000),
            Values::money(65596),
            [Values::rate(), Values::rate(RateRole::Accounting)],
            new RateOverride(Values::manager(), 'Client fidèle : taux arrondi'),
        );

        return self::paymentRecorded(TreasuryOperation::collection(
            [$movement],
            [Parts::allocation(AllocationNature::SettleReceivable, 65000)],
        ));
    }

    /**
     * @return array<string, MoneyFact>
     */
    public static function all(): array
    {
        return [
            'sale.completed' => self::saleCompleted(),
            'sale.cancelled' => self::saleCancelled(),
            'sale.charged_to_room' => self::saleChargedToRoom(),
            'room_charge.attached' => self::roomChargeAttached(),
            'service.consumed' => self::serviceConsumed(),
            'invoice.issued' => self::invoiceIssued(),
            'credit_note.issued' => self::creditNoteIssued(),
            'payment.recorded' => self::paymentRecorded(),
            'refund.paid' => self::refundPaid(),
            'security_deposit.received' => self::securityDepositReceived(),
            'security_deposit.returned' => self::securityDepositReturned(),
            'security_deposit.applied' => self::securityDepositApplied(),
            'adjustment.linked' => self::linkedAdjustment(),
        ];
    }
}
