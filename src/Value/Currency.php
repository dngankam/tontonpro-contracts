<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Value;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;

/**
 * Une devise ISO 4217 et ses décimales. Les décimales sont portées, jamais
 * supposées : un montant relu seul, des années plus tard, se suffit.
 */
final readonly class Currency
{
    public function __construct(
        public string $code,
        public int $decimals,
    ) {
        if (preg_match('/^[A-Z]{3}$/', $code) !== 1) {
            throw new ContractViolation(
                ViolationCode::CurrencyCodeInvalid,
                'code',
                sprintf('Le code de devise « %s » n\'est pas un code ISO 4217 de trois lettres majuscules.', $code),
            );
        }

        if ($decimals < 0 || $decimals > 4) {
            throw new ContractViolation(
                ViolationCode::CurrencyDecimalsInvalid,
                'decimals',
                sprintf('Une devise porte de 0 à 4 décimales, pas %d.', $decimals),
            );
        }
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code && $this->decimals === $other->decimals;
    }

    /**
     * @return array{code: string, decimals: int}
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'decimals' => $this->decimals];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('code', 'decimals');

        return new self($reader->string('code'), $reader->int('decimals'));
    }
}
