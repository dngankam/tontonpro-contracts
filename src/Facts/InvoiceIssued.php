<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts;

use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Facts\Concerns\DescribesMoneyFact;
use TontonPro\Contracts\Facts\Parts\LineSet;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\Party;
use TontonPro\Contracts\Value\SourceReference;

/**
 * `invoice.issued` — une facture émise dans une série autorisée. Les lignes
 * déjà reconnues (nuitée consommée) désignent le fait qui les a reconnues.
 * Un total nul est admis, lignes qualifiées à l'appui.
 */
final readonly class InvoiceIssued implements MoneyFact
{
    use DescribesMoneyFact;

    public function __construct(
        public FactHeader $header,
        public string $series,
        public string $number,
        public Party $debtor,
        public ?SourceReference $folio,
        public LineSet $charges,
    ) {
        self::requireText($series, ViolationCode::DocumentNumberMissing, 'series', 'La série de la facture est obligatoire.');
        self::requireText($number, ViolationCode::DocumentNumberMissing, 'number', 'Le numéro de la facture est obligatoire.');
        $this->requireLines($charges, 'charges');
    }

    public static function type(): string
    {
        return 'invoice.issued';
    }

    public function payload(): array
    {
        return [
            'series' => $this->series,
            'number' => $this->number,
            'debtor' => $this->debtor->toArray(),
            'folio' => $this->folio?->toArray(),
            'charges' => $this->charges->toArray(),
        ];
    }

    public static function fromPayload(FactHeader $header, array $payload): static
    {
        $reader = PayloadReader::of($payload, 'payload')->expect('series', 'number', 'debtor', 'folio', 'charges');
        $folio = $reader->nullableObject('folio');

        return new static(
            $header,
            $reader->string('series'),
            $reader->string('number'),
            Party::fromReader($reader->object('debtor')),
            $folio === null ? null : SourceReference::fromReader($folio),
            LineSet::fromReader($reader->object('charges')),
        );
    }
}
