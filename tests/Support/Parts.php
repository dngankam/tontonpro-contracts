<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Tests\Support;

use TontonPro\Contracts\Facts\Parts\Allocation;
use TontonPro\Contracts\Facts\Parts\Line;
use TontonPro\Contracts\Facts\Parts\LineSet;
use TontonPro\Contracts\Facts\Parts\TaxBucket;
use TontonPro\Contracts\Facts\Parts\TreasuryMovement;
use TontonPro\Contracts\Facts\Parts\TreasuryOperation;
use TontonPro\Contracts\Value\Complimentary;
use TontonPro\Contracts\Value\Currency;
use TontonPro\Contracts\Value\FactReference;
use TontonPro\Contracts\Value\Quantity;
use TontonPro\Contracts\Value\TaxRate;
use TontonPro\Contracts\Vocabulary\AllocationNature;
use TontonPro\Contracts\Vocabulary\ComplimentaryReason;
use TontonPro\Contracts\Vocabulary\LineKind;
use TontonPro\Contracts\Vocabulary\MovementNature;
use TontonPro\Contracts\Vocabulary\PriceBasis;
use TontonPro\Contracts\Vocabulary\TaxTreatment;

/**
 * Fabriques de composants valides : lignes, ventilations, opérations de trésorerie.
 */
final class Parts
{
    public static function line(
        int $amount,
        string $ref = 'l1',
        LineKind $kind = LineKind::Charge,
        TaxTreatment $treatment = TaxTreatment::Taxable,
        ?int $basisPoints = 1925,
        ?string $serviceDate = null,
        ?FactReference $recognizedBy = null,
        ?Complimentary $complimentary = null,
        ?Currency $currency = null,
    ): Line {
        return new Line(
            lineRef: $ref,
            kind: $kind,
            label: 'Prestation',
            quantity: new Quantity('1'),
            revenueCategory: 'HEB',
            amount: Values::money($amount, $currency),
            taxTreatment: $treatment,
            taxRate: $basisPoints === null ? null : new TaxRate($basisPoints),
            serviceDate: $serviceDate === null ? null : Values::date($serviceDate),
            recognizedBy: $recognizedBy,
            complimentary: $complimentary,
        );
    }

    public static function bucket(
        int $gross,
        ?int $net,
        ?int $tax,
        TaxTreatment $treatment = TaxTreatment::Taxable,
        ?int $basisPoints = 1925,
        ?Currency $currency = null,
    ): TaxBucket {
        return new TaxBucket(
            $treatment,
            $basisPoints === null ? null : new TaxRate($basisPoints),
            $net === null ? null : Values::money($net, $currency),
            $tax === null ? null : Values::money($tax, $currency),
            Values::money($gross, $currency),
        );
    }

    /**
     * @param list<Line>      $lines
     * @param list<TaxBucket> $breakdown
     */
    public static function set(
        array $lines,
        array $breakdown,
        int $discount = 0,
        PriceBasis $basis = PriceBasis::TaxIncluded,
        ?Complimentary $zeroQualification = null,
    ): LineSet {
        return new LineSet($basis, $lines, Values::money($discount), $breakdown, $zeroQualification);
    }

    /**
     * Une prestation de 5 000 annulée par une remise de 5 000 (remise de 100 %).
     */
    public static function compensated(?Complimentary $zeroQualification = null): LineSet
    {
        return self::set(
            [self::line(5000, 'l1'), self::line(5000, 'r1', LineKind::Deduction)],
            [self::bucket(0, 0, 0)],
            0,
            PriceBasis::TaxIncluded,
            $zeroQualification,
        );
    }

    /**
     * Base TTC, une ligne taxable (forme d'une note d'hôtel).
     */
    public static function ttc(
        int $gross = 11925,
        int $net = 10000,
        int $tax = 1925,
        ?string $serviceDate = null,
        ?FactReference $recognizedBy = null,
    ): LineSet {
        return new LineSet(
            PriceBasis::TaxIncluded,
            [self::line($gross, serviceDate: $serviceDate, recognizedBy: $recognizedBy)],
            Values::money(0),
            [self::bucket($gross, $net, $tax)],
        );
    }

    /**
     * Base HT avec remise sur la vente (forme d'une vente de restaurant) :
     * (10 000 + 5 000 − 3 000) HT = 12 000, TVA 2 310, TTC 14 310.
     */
    public static function restaurantHt(): LineSet
    {
        return new LineSet(
            PriceBasis::TaxExcluded,
            [self::line(10000, 'l1'), self::line(5000, 'l2')],
            Values::money(3000),
            [self::bucket(14310, 12000, 2310)],
        );
    }

    public static function gift(): Complimentary
    {
        return new Complimentary(ComplimentaryReason::commercialGesture(), Values::manager(), Values::money(15000));
    }

    /**
     * Une seule ligne offerte, à 0, qualifiée.
     */
    public static function freeSet(?string $serviceDate = null): LineSet
    {
        return new LineSet(
            PriceBasis::TaxIncluded,
            [self::line(0, serviceDate: $serviceDate, complimentary: self::gift())],
            Values::money(0),
            [self::bucket(0, 0, 0)],
        );
    }

    public static function movement(int $amount, string $ref = 'm1', MovementNature $nature = MovementNature::Received): TreasuryMovement
    {
        return new TreasuryMovement($ref, $nature, Values::xafFund(), Values::cash(), Values::money($amount), Values::money($amount));
    }

    /**
     * 100 EUR reçus en caisse EUR, contre-valeur 65 596 XAF (655,957 × 100 = 65 595,7 arrondi).
     */
    public static function eurReceived(int $amount = 10000, int $counterValue = 65596): TreasuryMovement
    {
        return new TreasuryMovement(
            'm1',
            MovementNature::Received,
            Values::eurFund(),
            Values::cash(),
            Values::money($amount, Values::eur()),
            Values::money($counterValue),
            null,
            [Values::rate()],
        );
    }

    public static function allocation(AllocationNature $nature, int $amount, string $ref = 'a1'): Allocation
    {
        return match ($nature) {
            AllocationNature::SettleReceivable => new Allocation($ref, $nature, Values::money($amount), Values::ref('invoice.issued', 'invoice:88')),
            AllocationNature::TipPayable => new Allocation($ref, $nature, Values::money($amount), null, 'fonds collectif'),
            default => new Allocation($ref, $nature, Values::money($amount)),
        };
    }

    /**
     * L'exemple de l'expert : 100 EUR (65 596) − 4 596 rendus = 60 000 réglés + 1 000 de pourboire.
     */
    public static function expert(): TreasuryOperation
    {
        return TreasuryOperation::collection(
            [self::eurReceived(), self::movement(4596, 'm2', MovementNature::ChangeReturned)],
            [self::allocation(AllocationNature::SettleReceivable, 60000), self::allocation(AllocationNature::TipPayable, 1000, 'a2')],
        );
    }

    /**
     * Sa variante sans pourboire : 65 596 − 5 596 = 60 000.
     */
    public static function expertWithoutTip(): TreasuryOperation
    {
        return TreasuryOperation::collection(
            [self::eurReceived(), self::movement(5596, 'm2', MovementNature::ChangeReturned)],
            [self::allocation(AllocationNature::SettleReceivable, 60000)],
        );
    }

    public static function payout(AllocationNature $nature, int $amount = 20000): TreasuryOperation
    {
        return TreasuryOperation::disbursement(
            [self::movement($amount, 'm1', MovementNature::PaidOut)],
            [self::allocation($nature, $amount)],
        );
    }
}
