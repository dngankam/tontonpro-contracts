<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts;

use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Facts\Concerns\DescribesMoneyFact;
use TontonPro\Contracts\Facts\Parts\LineSet;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\Party;

/**
 * `sale.completed` — une vente devenue définitive (payée ou portée en chambre).
 * Un total nul est admis : un repas offert, ligne qualifiée à l'appui.
 */
final readonly class SaleCompleted implements MoneyFact
{
    use DescribesMoneyFact;

    public function __construct(
        public FactHeader $header,
        public string $documentNumber,
        public ?Party $debtor,
        public LineSet $charges,
    ) {
        self::requireText($documentNumber, ViolationCode::DocumentNumberMissing, 'documentNumber', 'Le numéro de vente est obligatoire.');
        $this->requireLines($charges, 'charges');
    }

    public static function type(): string
    {
        return 'sale.completed';
    }

    public function payload(): array
    {
        return [
            'documentNumber' => $this->documentNumber,
            'debtor' => $this->debtor?->toArray(),
            'charges' => $this->charges->toArray(),
        ];
    }

    public static function fromPayload(FactHeader $header, array $payload): static
    {
        $reader = PayloadReader::of($payload, 'payload')->expect('documentNumber', 'debtor', 'charges');
        $debtor = $reader->nullableObject('debtor');

        return new static(
            $header,
            $reader->string('documentNumber'),
            $debtor === null ? null : Party::fromReader($debtor),
            LineSet::fromReader($reader->object('charges')),
        );
    }
}
