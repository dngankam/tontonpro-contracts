<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts;

use TontonPro\Contracts\Facts\Concerns\DescribesMoneyFact;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\FactReference;
use TontonPro\Contracts\Value\Money;

/**
 * `security_deposit.applied` — l'affectation explicite d'une caution au
 * règlement d'une facture. AUCUN mouvement de fonds.
 */
final readonly class SecurityDepositApplied implements MoneyFact
{
    use DescribesMoneyFact;

    public function __construct(
        public FactHeader $header,
        public FactReference $deposit,
        public FactReference $invoice,
        public Money $amount,
    ) {
        $deposit->requireType(SecurityDepositReceived::type(), 'deposit');
        $invoice->requireType(InvoiceIssued::type(), 'invoice');
        $this->requirePositive($amount, 'amount');
    }

    public static function type(): string
    {
        return 'security_deposit.applied';
    }

    public function payload(): array
    {
        return [
            'deposit' => $this->deposit->toArray(),
            'invoice' => $this->invoice->toArray(),
            'amount' => $this->amount->toArray(),
        ];
    }

    public static function fromPayload(FactHeader $header, array $payload): static
    {
        $reader = PayloadReader::of($payload, 'payload')->expect('deposit', 'invoice', 'amount');

        return new static(
            $header,
            FactReference::fromReader($reader->object('deposit')),
            FactReference::fromReader($reader->object('invoice')),
            Money::fromReader($reader->object('amount')),
        );
    }
}
