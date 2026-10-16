<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts;

use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Facts\Concerns\DescribesMoneyFact;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\Money;
use TontonPro\Contracts\Vocabulary\CancellationReason;

/**
 * `sale.cancelled` — l'annulation d'une vente, sous la même référence source
 * que la vente annulée. Le sort de l'argent (remboursement, somme due au
 * client) est un AUTRE fait.
 */
final readonly class SaleCancelled implements MoneyFact
{
    use DescribesMoneyFact;

    public function __construct(
        public FactHeader $header,
        public string $documentNumber,
        public Money $cancelledTotal,
        public CancellationReason $reason,
        public string $explanation,
    ) {
        self::requireText($documentNumber, ViolationCode::DocumentNumberMissing, 'documentNumber', 'Le numéro de la vente annulée est obligatoire.');
        $this->requirePositive($cancelledTotal, 'cancelledTotal');
        self::requireText($explanation, ViolationCode::ExplanationMissing, 'explanation', 'Une annulation s\'explique.');
    }

    public static function type(): string
    {
        return 'sale.cancelled';
    }

    public function payload(): array
    {
        return [
            'documentNumber' => $this->documentNumber,
            'cancelledTotal' => $this->cancelledTotal->toArray(),
            'reason' => $this->reason->toArray(),
            'explanation' => $this->explanation,
        ];
    }

    public static function fromPayload(FactHeader $header, array $payload): static
    {
        $reader = PayloadReader::of($payload, 'payload')->expect('documentNumber', 'cancelledTotal', 'reason', 'explanation');

        return new static(
            $header,
            $reader->string('documentNumber'),
            Money::fromReader($reader->object('cancelledTotal')),
            CancellationReason::fromReader($reader->object('reason')),
            $reader->string('explanation'),
        );
    }
}
