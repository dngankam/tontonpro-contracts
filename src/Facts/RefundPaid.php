<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts;

use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Facts\Concerns\DescribesMoneyFact;
use TontonPro\Contracts\Facts\Parts\TreasuryOperation;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\Party;
use TontonPro\Contracts\Vocabulary\AllocationNature;
use TontonPro\Contracts\Vocabulary\TreasuryDirection;

/**
 * `refund.paid` — un remboursement : un mouvement de trésorerie séparé,
 * qui rend un acompte ou une somme due au client. Il s'explique.
 */
final readonly class RefundPaid implements MoneyFact
{
    use DescribesMoneyFact;

    public function __construct(
        public FactHeader $header,
        public TreasuryOperation $operation,
        public ?Party $debtor,
        public string $explanation,
        public ?string $cashSession,
    ) {
        $operation->require(TreasuryDirection::Disbursement, [AllocationNature::Advance, AllocationNature::CustomerBalance]);
        $this->requireOperation($operation, 'operation');
        self::requireText($explanation, ViolationCode::ExplanationMissing, 'explanation', 'Un remboursement s\'explique.');
    }

    public static function type(): string
    {
        return 'refund.paid';
    }

    public function payload(): array
    {
        return [
            'operation' => $this->operation->toArray(),
            'debtor' => $this->debtor?->toArray(),
            'explanation' => $this->explanation,
            'cashSession' => $this->cashSession,
        ];
    }

    public static function fromPayload(FactHeader $header, array $payload): static
    {
        $reader = PayloadReader::of($payload, 'payload')->expect('operation', 'debtor', 'explanation', 'cashSession');
        $debtor = $reader->nullableObject('debtor');

        return new static(
            $header,
            TreasuryOperation::fromReader($reader->object('operation')),
            $debtor === null ? null : Party::fromReader($debtor),
            $reader->string('explanation'),
            $reader->nullableString('cashSession'),
        );
    }
}
