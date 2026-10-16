<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts\Parts;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\FactReference;
use TontonPro\Contracts\Value\Money;
use TontonPro\Contracts\Value\SourceReference;
use TontonPro\Contracts\Vocabulary\AllocationNature;

/**
 * Ce que l'argent règle, dans la monnaie de l'établissement. Ce n'est pas une
 * sortie de caisse : un pourboire à reverser est une dette, pas un rendu.
 */
final readonly class Allocation
{
    public function __construct(
        public string $allocationRef,
        public AllocationNature $nature,
        public Money $amount,
        public ?FactReference $target = null,
        public ?string $beneficiary = null,
    ) {
        if (preg_match(SourceReference::LOCAL_ID_PATTERN, $allocationRef) !== 1) {
            throw $this->incoherent('allocationRef', sprintf('La référence d\'affectation « %s » est invalide.', $allocationRef));
        }

        if ($amount->isZero()) {
            throw $this->incoherent('amount', 'Une affectation est strictement positive.');
        }

        if ($nature === AllocationNature::SettleReceivable && $target === null) {
            throw $this->incoherent('target', 'Le règlement d\'une créance désigne la créance réglée.');
        }

        if ($nature === AllocationNature::TipPayable && ($beneficiary === null || trim($beneficiary) === '')) {
            throw $this->incoherent('beneficiary', 'Un pourboire à reverser nomme son bénéficiaire.');
        }

        if ($nature !== AllocationNature::TipPayable && $beneficiary !== null) {
            throw $this->incoherent('beneficiary', 'Seul un pourboire nomme un bénéficiaire.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allocationRef' => $this->allocationRef,
            'nature' => $this->nature->value,
            'amount' => $this->amount->toArray(),
            'target' => $this->target?->toArray(),
            'beneficiary' => $this->beneficiary,
        ];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('allocationRef', 'nature', 'amount', 'target', 'beneficiary');
        $target = $reader->nullableObject('target');

        return new self(
            $reader->string('allocationRef'),
            $reader->enum('nature', AllocationNature::class),
            Money::fromReader($reader->object('amount')),
            $target === null ? null : FactReference::fromReader($target),
            $reader->nullableString('beneficiary'),
        );
    }

    private function incoherent(string $field, string $message): ContractViolation
    {
        return new ContractViolation(ViolationCode::AllocationIncoherent, $field, $message);
    }
}
