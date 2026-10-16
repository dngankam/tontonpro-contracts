<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Facts\Concerns\DescribesMoneyFact;
use TontonPro\Contracts\Facts\Parts\LineSet;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\BusinessDate;
use TontonPro\Contracts\Value\FactReference;
use TontonPro\Contracts\Vocabulary\AdjustmentDirection;
use TontonPro\Contracts\Vocabulary\AdjustmentReason;

/**
 * `adjustment.linked` — la correction d'un fait passé, qui n'est jamais
 * réécrit. Date d'enregistrement et auteur : l'en-tête. Le total est
 * strictement positif ; le sens est porté par `direction`.
 */
final readonly class LinkedAdjustment implements MoneyFact
{
    use DescribesMoneyFact;

    public function __construct(
        public FactHeader $header,
        public FactReference $adjusted,
        public AdjustmentReason $reason,
        public string $explanation,
        public BusinessDate $originalServiceDate,
        public AdjustmentDirection $direction,
        public LineSet $charges,
    ) {
        self::requireText($explanation, ViolationCode::ExplanationMissing, 'explanation', 'Un ajustement s\'explique.');

        if ($originalServiceDate->isAfter($header->businessDate)) {
            throw new ContractViolation(
                ViolationCode::AdjustmentBeforeService,
                'originalServiceDate',
                'On ne corrige pas une prestation à venir.',
            );
        }

        $this->requireLines($charges, 'charges');
        $charges->requirePositiveTotal('charges');
    }

    public static function type(): string
    {
        return 'adjustment.linked';
    }

    public function payload(): array
    {
        return [
            'adjusted' => $this->adjusted->toArray(),
            'reason' => $this->reason->toArray(),
            'explanation' => $this->explanation,
            'originalServiceDate' => $this->originalServiceDate->value,
            'direction' => $this->direction->value,
            'charges' => $this->charges->toArray(),
        ];
    }

    public static function fromPayload(FactHeader $header, array $payload): static
    {
        $reader = PayloadReader::of($payload, 'payload')
            ->expect('adjusted', 'reason', 'explanation', 'originalServiceDate', 'direction', 'charges');

        return new static(
            $header,
            FactReference::fromReader($reader->object('adjusted')),
            AdjustmentReason::fromReader($reader->object('reason')),
            $reader->string('explanation'),
            new BusinessDate($reader->string('originalServiceDate')),
            $reader->enum('direction', AdjustmentDirection::class),
            LineSet::fromReader($reader->object('charges')),
        );
    }
}
