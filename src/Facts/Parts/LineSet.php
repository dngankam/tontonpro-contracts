<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts\Parts;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\Complimentary;
use TontonPro\Contracts\Value\Currency;
use TontonPro\Contracts\Value\Money;
use TontonPro\Contracts\Vocabulary\LineKind;
use TontonPro\Contracts\Vocabulary\PriceBasis;

/**
 * Les lignes d'un fait, la remise sur le total et la ventilation fiscale.
 *
 * L1 au moins une ligne `charge` ; L2 références uniques ; L3 une seule devise ;
 * L4 une case par (traitement, taux), chaque ligne a sa case et chaque case a
 * sa ligne ; L5 net des lignes (Σ charges − Σ remises − remise) ≥ 0 ;
 * L6 ce net est égal au Σ TTC (base TTC) ou au Σ HT (base HT).
 *
 * L5 admet un total nul (gratuité) ; c'est le FAIT qui exige un total
 * strictement positif quand il le doit (`requirePositiveTotal()`).
 *
 * L7 un net nul obtenu par COMPENSATION (remises qui annulent des prestations
 * positives) exige sa qualification (`zeroQualification`), comme une gratuité ;
 * cette qualification est interdite quand le net est positif. Les lignes
 * restent détaillées, même quand elles se compensent.
 */
final readonly class LineSet
{
    /**
     * @param list<Line>      $lines
     * @param list<TaxBucket> $breakdown
     */
    public function __construct(
        public PriceBasis $basis,
        public array $lines,
        public Money $discount,
        public array $breakdown,
        public ?Complimentary $zeroQualification = null,
    ) {
        $this->requireCharge();
        $this->requireSingleCurrency();
        $this->requireMatchingBreakdown();

        $net = $this->linesNet();

        if ($net < 0) {
            throw new ContractViolation(
                ViolationCode::LinesTotalNegative,
                'lines',
                sprintf('Le net des lignes est négatif (%d) : les remises dépassent les prestations.', $net),
            );
        }

        $this->requireReconciled($net);
        $this->requireQualifiedZero($net);
    }

    public function currency(): Currency
    {
        return $this->discount->currency;
    }

    /**
     * Total TTC : la somme des cases.
     */
    public function total(): Money
    {
        return Money::sum(array_map(static fn (TaxBucket $bucket): Money => $bucket->gross, $this->breakdown), $this->currency());
    }

    public function netTotal(): ?Money
    {
        $nets = [];

        foreach ($this->breakdown as $bucket) {
            if ($bucket->net === null) {
                return null;
            }

            $nets[] = $bucket->net;
        }

        return Money::sum($nets, $this->currency());
    }

    public function taxTotal(): ?Money
    {
        $taxes = [];

        foreach ($this->breakdown as $bucket) {
            if ($bucket->tax === null) {
                return null;
            }

            $taxes[] = $bucket->tax;
        }

        return Money::sum($taxes, $this->currency());
    }

    /**
     * Le contrôle des faits dont le total doit être strictement positif
     * (avoir, report, ajustement) : un avoir à 0 n'a pas de sens.
     */
    public function requirePositiveTotal(string $field): void
    {
        if ($this->linesNet() === 0) {
            throw new ContractViolation(ViolationCode::LinesTotalNotPositive, $field, 'Le total de ce fait doit être strictement positif.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'basis' => $this->basis->value,
            'lines' => array_map(static fn (Line $line): array => $line->toArray(), $this->lines),
            'discount' => $this->discount->toArray(),
            'breakdown' => array_map(static fn (TaxBucket $bucket): array => $bucket->toArray(), $this->breakdown),
            'zeroQualification' => $this->zeroQualification?->toArray(),
        ];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('basis', 'lines', 'discount', 'breakdown', 'zeroQualification');
        $qualification = $reader->nullableObject('zeroQualification');

        return new self(
            $reader->enum('basis', PriceBasis::class),
            array_map(Line::fromReader(...), $reader->objects('lines')),
            Money::fromReader($reader->object('discount')),
            array_map(TaxBucket::fromReader(...), $reader->objects('breakdown')),
            $qualification === null ? null : Complimentary::fromReader($qualification),
        );
    }

    private function linesNet(): int
    {
        $charges = [];
        $deductions = [$this->discount];

        foreach ($this->lines as $line) {
            if ($line->kind === LineKind::Charge) {
                $charges[] = $line->amount;
            } else {
                $deductions[] = $line->amount;
            }
        }

        return Money::sum($charges, $this->currency())->amount - Money::sum($deductions, $this->currency())->amount;
    }

    private function requireCharge(): void
    {
        foreach ($this->lines as $line) {
            if ($line->kind === LineKind::Charge) {
                return;
            }
        }

        throw new ContractViolation(ViolationCode::LinesEmpty, 'lines', 'Un fait porte au moins une ligne de prestation.');
    }

    private function requireSingleCurrency(): void
    {
        $currency = $this->currency();
        $refs = [];

        foreach ($this->lines as $index => $line) {
            if (isset($refs[$line->lineRef])) {
                throw new ContractViolation(
                    ViolationCode::LineRefDuplicate,
                    sprintf('lines[%d].lineRef', $index),
                    sprintf('La référence de ligne « %s » est en double.', $line->lineRef),
                );
            }

            $refs[$line->lineRef] = true;

            if (! $line->amount->currency->equals($currency)) {
                throw $this->mismatch(sprintf('lines[%d].amount', $index));
            }
        }

        foreach ($this->breakdown as $index => $bucket) {
            if (! $bucket->currency()->equals($currency)) {
                throw $this->mismatch(sprintf('breakdown[%d].gross', $index));
            }
        }
    }

    private function requireMatchingBreakdown(): void
    {
        $buckets = [];

        foreach ($this->breakdown as $index => $bucket) {
            if (isset($buckets[$bucket->key()])) {
                throw $this->breakdownMismatch(sprintf('breakdown[%d]', $index), sprintf('La case « %s » est en double.', $bucket->key()));
            }

            $buckets[$bucket->key()] = false;
        }

        foreach ($this->lines as $index => $line) {
            $key = TaxBucket::keyOf($line->taxTreatment, $line->taxRate);

            if (! isset($buckets[$key])) {
                throw $this->breakdownMismatch(sprintf('lines[%d].taxTreatment', $index), sprintf('La ventilation n\'a pas de case « %s ».', $key));
            }

            $buckets[$key] = true;
        }

        foreach ($this->breakdown as $index => $bucket) {
            if (! $buckets[$bucket->key()]) {
                throw $this->breakdownMismatch(sprintf('breakdown[%d]', $index), sprintf('La case « %s » ne porte aucune ligne.', $bucket->key()));
            }
        }
    }

    private function requireReconciled(int $net): void
    {
        $expected = $this->basis === PriceBasis::TaxIncluded ? $this->total() : $this->netTotal();

        if ($expected === null || $expected->amount !== $net) {
            throw new ContractViolation(
                ViolationCode::LinesBreakdownUnreconciled,
                'breakdown',
                sprintf('Les lignes (net %d) ne se réconcilient pas avec la ventilation (base « %s »).', $net, $this->basis->value),
            );
        }
    }

    private function requireQualifiedZero(int $net): void
    {
        $qualification = $this->zeroQualification;

        if ($net > 0) {
            if ($qualification !== null) {
                throw new ContractViolation(
                    ViolationCode::ZeroQualificationUnexpected,
                    'zeroQualification',
                    'Seul un total nul se qualifie de gratuité.',
                );
            }

            return;
        }

        $compensated = false;

        foreach ($this->lines as $line) {
            $compensated = $compensated || ($line->kind === LineKind::Charge && ! $line->amount->isZero());
        }

        if ($compensated && $qualification === null) {
            throw new ContractViolation(
                ViolationCode::ZeroTotalWithoutQualification,
                'zeroQualification',
                'Un total nul obtenu par compensation doit être qualifié de gratuité.',
            );
        }

        $price = $qualification?->referencePrice;

        if ($price !== null && ! $price->currency->equals($this->currency())) {
            throw new ContractViolation(
                ViolationCode::ComplimentaryReferencePriceInvalid,
                'zeroQualification.referencePrice',
                'Le prix de référence est dans la devise des lignes.',
            );
        }
    }

    private function mismatch(string $field): ContractViolation
    {
        return new ContractViolation(ViolationCode::CurrencyMismatch, $field, 'Toutes les lignes et la ventilation tiennent une seule devise.');
    }

    private function breakdownMismatch(string $field, string $message): ContractViolation
    {
        return new ContractViolation(ViolationCode::TaxBreakdownMismatch, $field, $message);
    }
}
