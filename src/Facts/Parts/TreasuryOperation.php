<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts\Parts;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\Currency;
use TontonPro\Contracts\Value\Money;
use TontonPro\Contracts\Vocabulary\AllocationNature;
use TontonPro\Contracts\Vocabulary\MovementNature;
use TontonPro\Contracts\Vocabulary\TreasuryDirection;

/**
 * Une opération de trésorerie : les mouvements physiques ET ce qu'ils règlent.
 *
 * T1 références uniques ; T2 un encaissement a au moins une entrée et aucune
 * sortie, un décaissement au moins une sortie et ni entrée ni rendu ;
 * T3 équilibre sur les contre-valeurs commerciales : entrées − rendus (ou
 * sorties) = Σ affectations.
 *
 * Exemple : reçu 100 EUR (65 596 XAF) − rendu 4 596 XAF = 60 000 réglés + 1 000
 * de pourboire. Les 65 596 XAF ne sont pas affectés en entier à la facture.
 */
final readonly class TreasuryOperation
{
    /**
     * @param list<TreasuryMovement> $movements
     * @param list<Allocation>       $allocations
     */
    public function __construct(
        public TreasuryDirection $direction,
        public array $movements,
        public array $allocations,
    ) {
        $this->requireUniqueRefs();
        $this->requireNatures();
        $this->requireBalance();
    }

    /**
     * @param list<TreasuryMovement> $movements
     * @param list<Allocation>       $allocations
     */
    public static function collection(array $movements, array $allocations): self
    {
        return new self(TreasuryDirection::Collection, $movements, $allocations);
    }

    /**
     * @param list<TreasuryMovement> $movements
     * @param list<Allocation>       $allocations
     */
    public static function disbursement(array $movements, array $allocations): self
    {
        return new self(TreasuryDirection::Disbursement, $movements, $allocations);
    }

    /**
     * La monnaie de tenue : celle des contre-valeurs et des affectations.
     */
    public function currency(): Currency
    {
        return $this->movements[0]->counterValue->currency;
    }

    /**
     * Le sens et les natures que le fait permet.
     *
     * @param list<AllocationNature> $natures
     */
    public function require(TreasuryDirection $direction, array $natures): void
    {
        if ($this->direction !== $direction) {
            throw new ContractViolation(
                ViolationCode::TreasuryDirectionInvalid,
                'direction',
                sprintf('Ce fait attend une opération « %s », pas « %s ».', $direction->value, $this->direction->value),
            );
        }

        foreach ($this->allocations as $index => $allocation) {
            if (! in_array($allocation->nature, $natures, true)) {
                throw new ContractViolation(
                    ViolationCode::AllocationNatureNotAllowed,
                    sprintf('allocations[%d].nature', $index),
                    sprintf('L\'affectation « %s » n\'a pas sa place dans ce fait.', $allocation->nature->value),
                );
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'direction' => $this->direction->value,
            'movements' => array_map(static fn (TreasuryMovement $movement): array => $movement->toArray(), $this->movements),
            'allocations' => array_map(static fn (Allocation $allocation): array => $allocation->toArray(), $this->allocations),
        ];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('direction', 'movements', 'allocations');

        return new self(
            $reader->enum('direction', TreasuryDirection::class),
            array_map(TreasuryMovement::fromReader(...), $reader->objects('movements')),
            array_map(Allocation::fromReader(...), $reader->objects('allocations')),
        );
    }

    private function requireUniqueRefs(): void
    {
        $seen = [];

        foreach ($this->movements as $index => $movement) {
            if (isset($seen[$movement->movementRef])) {
                throw new ContractViolation(ViolationCode::MovementRefDuplicate, sprintf('movements[%d].movementRef', $index), 'Référence de mouvement en double.');
            }

            $seen[$movement->movementRef] = true;
        }

        $seen = [];

        foreach ($this->allocations as $index => $allocation) {
            if (isset($seen[$allocation->allocationRef])) {
                throw new ContractViolation(ViolationCode::AllocationRefDuplicate, sprintf('allocations[%d].allocationRef', $index), 'Référence d\'affectation en double.');
            }

            $seen[$allocation->allocationRef] = true;
        }
    }

    private function requireNatures(): void
    {
        $count = ['received' => 0, 'change_returned' => 0, 'paid_out' => 0];

        foreach ($this->movements as $movement) {
            $count[$movement->nature->value]++;
        }

        $coherent = $this->direction === TreasuryDirection::Collection
            ? $count['received'] > 0 && $count['paid_out'] === 0
            : $count['paid_out'] > 0 && $count['received'] === 0 && $count['change_returned'] === 0;

        if (! $coherent) {
            throw new ContractViolation(
                ViolationCode::TreasuryNatureIncoherent,
                'movements',
                sprintf('Les mouvements ne s\'accordent pas avec un « %s ».', $this->direction->value),
            );
        }
    }

    private function requireBalance(): void
    {
        $currency = $this->currency();
        $in = [];
        $out = [];

        foreach ($this->movements as $index => $movement) {
            if (! $movement->counterValue->currency->equals($currency)) {
                throw new ContractViolation(ViolationCode::CurrencyMismatch, sprintf('movements[%d].counterValue', $index), 'Les contre-valeurs sont dans une seule monnaie.');
            }

            if ($movement->nature === MovementNature::ChangeReturned) {
                $out[] = $movement->counterValue;
            } else {
                $in[] = $movement->counterValue;
            }
        }

        foreach ($this->allocations as $index => $allocation) {
            if (! $allocation->amount->currency->equals($currency)) {
                throw new ContractViolation(ViolationCode::CurrencyMismatch, sprintf('allocations[%d].amount', $index), 'Les affectations sont dans la monnaie de l\'établissement.');
            }
        }

        $moved = Money::sum($in, $currency)->amount - Money::sum($out, $currency)->amount;
        $allocated = Money::sum(array_map(static fn (Allocation $a): Money => $a->amount, $this->allocations), $currency)->amount;

        if ($moved !== $allocated) {
            throw new ContractViolation(
                ViolationCode::TreasuryUnbalanced,
                'allocations',
                sprintf('Les fonds (%d) ne s\'équilibrent pas avec les affectations (%d).', $moved, $allocated),
            );
        }
    }
}
