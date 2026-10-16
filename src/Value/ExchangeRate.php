<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Value;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Vocabulary\RateRole;

/**
 * Un taux de change et son sens : « 1 `base` = `rate` `quote` »
 * (1 EUR = 655.957 XAF). Le taux est un décimal exact, porté en chaîne.
 */
final readonly class ExchangeRate
{
    public function __construct(
        public Currency $base,
        public Currency $quote,
        public string $rate,
        public RateRole $role,
        public string $source,
        public UtcInstant $effectiveAt,
        public \RoundingMode $rounding,
    ) {
        if ($base->code === $quote->code) {
            throw new ContractViolation(
                ViolationCode::RateSameCurrency,
                'quote',
                sprintf('Un taux relie deux devises différentes, pas %s à elle-même.', $base->code),
            );
        }

        if (preg_match('/^[0-9]{1,12}(\.[0-9]{1,10})?$/', $rate) !== 1 || preg_match('/[1-9]/', $rate) !== 1) {
            throw new ContractViolation(
                ViolationCode::RateInvalid,
                'rate',
                sprintf('« %s » n\'est pas un taux décimal strictement positif.', $rate),
            );
        }

        if (trim($source) === '') {
            throw new ContractViolation(ViolationCode::RateSourceMissing, 'source', 'La source du taux est obligatoire.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'base' => $this->base->toArray(),
            'quote' => $this->quote->toArray(),
            'rate' => $this->rate,
            'role' => $this->role->value,
            'source' => $this->source,
            'effectiveAt' => $this->effectiveAt->toString(),
            'rounding' => $this->rounding->name,
        ];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('base', 'quote', 'rate', 'role', 'source', 'effectiveAt', 'rounding');

        return new self(
            Currency::fromReader($reader->object('base')),
            Currency::fromReader($reader->object('quote')),
            $reader->string('rate'),
            $reader->enum('role', RateRole::class),
            $reader->string('source'),
            UtcInstant::fromString($reader->string('effectiveAt')),
            $reader->roundingMode('rounding'),
        );
    }
}
