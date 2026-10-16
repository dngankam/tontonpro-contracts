<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Facts\Concerns\DescribesMoneyFact;
use TontonPro\Contracts\Facts\Parts\LineSet;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\FactReference;
use TontonPro\Contracts\Value\Money;
use TontonPro\Contracts\Value\Party;
use TontonPro\Contracts\Vocabulary\CreditNoteReason;
use TontonPro\Contracts\Vocabulary\CreditScope;

/**
 * `credit_note.issued` — un avoir, dans une série distincte de celle des
 * factures, à sa date réelle d'émission (l'en-tête), montants positifs.
 * Son total est strictement positif et ne dépasse pas la facture d'origine.
 */
final readonly class CreditNoteIssued implements MoneyFact
{
    use DescribesMoneyFact;

    public function __construct(
        public FactHeader $header,
        public string $series,
        public string $number,
        public FactReference $originalInvoice,
        public string $originalSeries,
        public string $originalNumber,
        public Money $originalTotal,
        public CreditScope $scope,
        public CreditNoteReason $reason,
        public string $explanation,
        public Party $debtor,
        public ?FactReference $replacementInvoice,
        public LineSet $charges,
    ) {
        foreach (['series' => $series, 'number' => $number, 'originalSeries' => $originalSeries, 'originalNumber' => $originalNumber] as $field => $value) {
            self::requireText($value, ViolationCode::DocumentNumberMissing, $field, 'Séries et numéros de l\'avoir et de la facture d\'origine sont obligatoires.');
        }

        if ($series === $originalSeries) {
            throw new ContractViolation(ViolationCode::CreditNoteSeriesNotDistinct, 'series', 'Un avoir est numéroté dans une série distincte de celle des factures.');
        }

        $originalInvoice->requireType(InvoiceIssued::type(), 'originalInvoice');
        $replacementInvoice?->requireType(InvoiceIssued::type(), 'replacementInvoice');
        self::requireText($explanation, ViolationCode::ExplanationMissing, 'explanation', 'Un avoir s\'explique.');
        $this->requirePositive($originalTotal, 'originalTotal');
        $this->requireLines($charges, 'charges');
        $charges->requirePositiveTotal('charges');

        $total = $charges->total();

        if ($total->amount > $originalTotal->amount) {
            throw new ContractViolation(
                ViolationCode::CreditNoteExceedsInvoice,
                'charges',
                sprintf('L\'avoir (%d) dépasse la facture d\'origine (%d).', $total->amount, $originalTotal->amount),
            );
        }

        if (($total->amount === $originalTotal->amount) !== ($scope === CreditScope::Total)) {
            throw new ContractViolation(
                ViolationCode::CreditNoteScopeIncoherent,
                'scope',
                'Un avoir est total si, et seulement si, il couvre toute la facture.',
            );
        }
    }

    public static function type(): string
    {
        return 'credit_note.issued';
    }

    public function payload(): array
    {
        return [
            'series' => $this->series,
            'number' => $this->number,
            'originalInvoice' => $this->originalInvoice->toArray(),
            'originalSeries' => $this->originalSeries,
            'originalNumber' => $this->originalNumber,
            'originalTotal' => $this->originalTotal->toArray(),
            'scope' => $this->scope->value,
            'reason' => $this->reason->toArray(),
            'explanation' => $this->explanation,
            'debtor' => $this->debtor->toArray(),
            'replacementInvoice' => $this->replacementInvoice?->toArray(),
            'charges' => $this->charges->toArray(),
        ];
    }

    public static function fromPayload(FactHeader $header, array $payload): static
    {
        $reader = PayloadReader::of($payload, 'payload')->expect(
            'series', 'number', 'originalInvoice', 'originalSeries', 'originalNumber', 'originalTotal',
            'scope', 'reason', 'explanation', 'debtor', 'replacementInvoice', 'charges',
        );
        $replacement = $reader->nullableObject('replacementInvoice');

        return new static(
            $header,
            $reader->string('series'),
            $reader->string('number'),
            FactReference::fromReader($reader->object('originalInvoice')),
            $reader->string('originalSeries'),
            $reader->string('originalNumber'),
            Money::fromReader($reader->object('originalTotal')),
            $reader->enum('scope', CreditScope::class),
            CreditNoteReason::fromReader($reader->object('reason')),
            $reader->string('explanation'),
            Party::fromReader($reader->object('debtor')),
            $replacement === null ? null : FactReference::fromReader($replacement),
            LineSet::fromReader($reader->object('charges')),
        );
    }
}
