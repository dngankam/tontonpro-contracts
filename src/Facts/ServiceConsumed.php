<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Facts\Concerns\DescribesMoneyFact;
use TontonPro\Contracts\Facts\Parts\LineSet;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\Party;
use TontonPro\Contracts\Value\SourceReference;
use TontonPro\Contracts\Vocabulary\BillingStatus;

/**
 * `service.consumed` — une prestation réellement consommée, reconnue à la
 * journée d'exploitation. Chaque ligne est datée de cette journée. Un total
 * nul est admis : une nuitée offerte, ligne qualifiée à l'appui.
 */
final readonly class ServiceConsumed implements MoneyFact
{
    use DescribesMoneyFact;

    public function __construct(
        public FactHeader $header,
        public SourceReference $stay,
        public SourceReference $folio,
        public Party $debtor,
        public LineSet $charges,
        public BillingStatus $billingStatus,
        public ?SourceReference $advanceInvoice = null,
    ) {
        $this->requireLines($charges, 'charges');

        foreach ($charges->lines as $index => $line) {
            if ($line->serviceDate === null || ! $line->serviceDate->equals($header->businessDate)) {
                throw new ContractViolation(
                    ViolationCode::ServiceDateMismatch,
                    sprintf('charges.lines[%d].serviceDate', $index),
                    sprintf('Une prestation consommée est datée de la journée %s.', $header->businessDate->value),
                );
            }
        }

        if (($billingStatus === BillingStatus::InvoicedInAdvance) !== ($advanceInvoice !== null)) {
            throw new ContractViolation(
                ViolationCode::BillingStatusIncoherent,
                'advanceInvoice',
                'Une prestation facturée d\'avance désigne sa facture, et seulement elle.',
            );
        }
    }

    public static function type(): string
    {
        return 'service.consumed';
    }

    public function payload(): array
    {
        return [
            'stay' => $this->stay->toArray(),
            'folio' => $this->folio->toArray(),
            'debtor' => $this->debtor->toArray(),
            'charges' => $this->charges->toArray(),
            'billingStatus' => $this->billingStatus->value,
            'advanceInvoice' => $this->advanceInvoice?->toArray(),
        ];
    }

    public static function fromPayload(FactHeader $header, array $payload): static
    {
        $reader = PayloadReader::of($payload, 'payload')
            ->expect('stay', 'folio', 'debtor', 'charges', 'billingStatus', 'advanceInvoice');
        $advance = $reader->nullableObject('advanceInvoice');

        return new static(
            $header,
            SourceReference::fromReader($reader->object('stay')),
            SourceReference::fromReader($reader->object('folio')),
            Party::fromReader($reader->object('debtor')),
            LineSet::fromReader($reader->object('charges')),
            $reader->enum('billingStatus', BillingStatus::class),
            $advance === null ? null : SourceReference::fromReader($advance),
        );
    }
}
