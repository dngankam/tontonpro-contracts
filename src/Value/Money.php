<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Value;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;

/**
 * Un montant entier en unité mineure, toujours ≥ 0, et sa devise. Le sens de
 * l'argent est porté ailleurs, explicitement (nature, direction, type de ligne).
 * Aucune arithmétique flottante.
 */
final readonly class Money
{
    public function __construct(
        public int $amount,
        public Currency $currency,
    ) {
        if ($amount < 0) {
            throw new ContractViolation(
                ViolationCode::AmountNegative,
                'amount',
                sprintf('Un montant est toujours positif ou nul (%d reçu) : le sens se dit ailleurs.', $amount),
            );
        }
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    /**
     * @param list<self> $amounts
     */
    public static function sum(array $amounts, Currency $currency): self
    {
        $total = self::zero($currency);

        foreach ($amounts as $amount) {
            $total = $total->plus($amount);
        }

        return $total;
    }

    public function plus(self $other): self
    {
        $this->requireSameCurrency($other);

        if ($other->amount > PHP_INT_MAX - $this->amount) {
            throw new ContractViolation(
                ViolationCode::AmountOverflow,
                'amount',
                'La somme dépasse le plus grand entier représentable.',
            );
        }

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->requireSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency->equals($other->currency);
    }

    /**
     * @return array{amount: int, currency: array{code: string, decimals: int}}
     */
    public function toArray(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency->toArray()];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('amount', 'currency');

        return new self($reader->int('amount'), Currency::fromReader($reader->object('currency')));
    }

    private function requireSameCurrency(self $other): void
    {
        if (! $this->currency->equals($other->currency)) {
            throw new ContractViolation(
                ViolationCode::CurrencyMismatch,
                'currency',
                sprintf('On ne combine pas des %s et des %s.', $this->currency->code, $other->currency->code),
            );
        }
    }
}
