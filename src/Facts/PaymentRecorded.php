<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts;

use TontonPro\Contracts\Facts\Concerns\DescribesMoneyFact;
use TontonPro\Contracts\Facts\Parts\TreasuryOperation;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\Party;
use TontonPro\Contracts\Vocabulary\AllocationNature;
use TontonPro\Contracts\Vocabulary\TreasuryDirection;

/**
 * `payment.recorded` — un encaissement : les fonds réellement reçus et rendus,
 * et ce qu'ils règlent. La caution a ses propres faits.
 */
final readonly class PaymentRecorded implements MoneyFact
{
    use DescribesMoneyFact;

    public function __construct(
        public FactHeader $header,
        public TreasuryOperation $operation,
        public ?Party $debtor,
        public ?string $cashSession,
    ) {
        $operation->require(TreasuryDirection::Collection, [
            AllocationNature::SettleReceivable,
            AllocationNature::Advance,
            AllocationNature::TipPayable,
            AllocationNature::CustomerBalance,
        ]);
        $this->requireOperation($operation, 'operation');
    }

    public static function type(): string
    {
        return 'payment.recorded';
    }

    public function payload(): array
    {
        return [
            'operation' => $this->operation->toArray(),
            'debtor' => $this->debtor?->toArray(),
            'cashSession' => $this->cashSession,
        ];
    }

    public static function fromPayload(FactHeader $header, array $payload): static
    {
        $reader = PayloadReader::of($payload, 'payload')->expect('operation', 'debtor', 'cashSession');
        $debtor = $reader->nullableObject('debtor');

        return new static(
            $header,
            TreasuryOperation::fromReader($reader->object('operation')),
            $debtor === null ? null : Party::fromReader($debtor),
            $reader->nullableString('cashSession'),
        );
    }
}
