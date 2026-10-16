<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts;

use TontonPro\Contracts\Facts\Concerns\DescribesMoneyFact;
use TontonPro\Contracts\Facts\Parts\TreasuryOperation;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\FactReference;
use TontonPro\Contracts\Vocabulary\AllocationNature;
use TontonPro\Contracts\Vocabulary\TreasuryDirection;

/**
 * `security_deposit.returned` — la restitution d'une caution : le mouvement
 * inverse. Qu'elle ne dépasse pas la caution reçue se vérifie dans le livre.
 */
final readonly class SecurityDepositReturned implements MoneyFact
{
    use DescribesMoneyFact;

    public function __construct(
        public FactHeader $header,
        public FactReference $deposit,
        public TreasuryOperation $operation,
    ) {
        $deposit->requireType(SecurityDepositReceived::type(), 'deposit');
        $operation->require(TreasuryDirection::Disbursement, [AllocationNature::SecurityDeposit]);
        $this->requireOperation($operation, 'operation');
    }

    public static function type(): string
    {
        return 'security_deposit.returned';
    }

    public function payload(): array
    {
        return [
            'deposit' => $this->deposit->toArray(),
            'operation' => $this->operation->toArray(),
        ];
    }

    public static function fromPayload(FactHeader $header, array $payload): static
    {
        $reader = PayloadReader::of($payload, 'payload')->expect('deposit', 'operation');

        return new static(
            $header,
            FactReference::fromReader($reader->object('deposit')),
            TreasuryOperation::fromReader($reader->object('operation')),
        );
    }
}
