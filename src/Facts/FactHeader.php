<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts;

use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\Actor;
use TontonPro\Contracts\Value\BusinessDate;
use TontonPro\Contracts\Value\Currency;
use TontonPro\Contracts\Value\Establishment;
use TontonPro\Contracts\Value\IdempotencyKey;
use TontonPro\Contracts\Value\SourceReference;
use TontonPro\Contracts\Value\UtcInstant;

/**
 * L'en-tête commun à tous les faits : révision, source, établissement figé,
 * instant UTC, journée d'exploitation de la source, auteur.
 */
final readonly class FactHeader
{
    public function __construct(
        public int $factVersion,
        public SourceReference $source,
        public Establishment $establishment,
        public UtcInstant $occurredAt,
        public BusinessDate $businessDate,
        public Actor $actor,
    ) {
        IdempotencyKey::requireFactVersion($factVersion, 'factVersion');
    }

    /**
     * La monnaie de l'établissement : celle de tous les montants du fait.
     */
    public function currency(): Currency
    {
        return $this->establishment->currency;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'factVersion' => $this->factVersion,
            'source' => $this->source->toArray(),
            'establishment' => $this->establishment->toArray(),
            'occurredAt' => $this->occurredAt->toString(),
            'businessDate' => $this->businessDate->value,
            'actor' => $this->actor->toArray(),
        ];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('factVersion', 'source', 'establishment', 'occurredAt', 'businessDate', 'actor');

        return new self(
            $reader->int('factVersion'),
            SourceReference::fromReader($reader->object('source')),
            Establishment::fromReader($reader->object('establishment')),
            UtcInstant::fromString($reader->string('occurredAt')),
            new BusinessDate($reader->string('businessDate')),
            Actor::fromReader($reader->object('actor')),
        );
    }
}
