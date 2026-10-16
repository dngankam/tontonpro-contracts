<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Value;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;

/**
 * Une journée d'exploitation « AAAA-MM-JJ », sans fuseau.
 */
final readonly class BusinessDate
{
    public function __construct(
        public string $value,
    ) {
        $parsed = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value)
            : false;

        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw new ContractViolation(
                ViolationCode::BusinessDateInvalid,
                'value',
                sprintf('« %s » n\'est pas une journée valide au format AAAA-MM-JJ.', $value),
            );
        }
    }

    public function isAfter(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
