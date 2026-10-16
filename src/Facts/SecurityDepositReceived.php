<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts;

use TontonPro\Contracts\Facts\Concerns\DescribesMoneyFact;
use TontonPro\Contracts\Facts\Parts\TreasuryOperation;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\Party;
use TontonPro\Contracts\Value\SourceReference;
use TontonPro\Contracts\Vocabulary\AllocationNature;
use TontonPro\Contracts\Vocabulary\TreasuryDirection;

/**
 * `security_deposit.received` — une caution reçue, suivie par client ou par
 * séjour. Ce n'est pas un produit : toutes les affectations sont `security_deposit`.
 */
final readonly class SecurityDepositReceived implements MoneyFact
{
    use DescribesMoneyFact;

    public function __construct(
        public FactHeader $header,
        public SourceReference $holder,
        public Party $depositor,
        public TreasuryOperation $operation,
    ) {
        $operation->require(TreasuryDirection::Collection, [AllocationNature::SecurityDeposit]);
        $this->requireOperation($operation, 'operation');
    }

    public static function type(): string
    {
        return 'security_deposit.received';
    }

    public function payload(): array
    {
        return [
            'holder' => $this->holder->toArray(),
            'depositor' => $this->depositor->toArray(),
            'operation' => $this->operation->toArray(),
        ];
    }

    public static function fromPayload(FactHeader $header, array $payload): static
    {
        $reader = PayloadReader::of($payload, 'payload')->expect('holder', 'depositor', 'operation');

        return new static(
            $header,
            SourceReference::fromReader($reader->object('holder')),
            Party::fromReader($reader->object('depositor')),
            TreasuryOperation::fromReader($reader->object('operation')),
        );
    }
}
