<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Value;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;

/**
 * Une quantité décimale exacte, strictement positive (« 1 », « 0.5 »).
 * Portée en chaîne : jamais de flottant.
 */
final readonly class Quantity
{
    public function __construct(
        public string $value,
    ) {
        if (preg_match('/^(0|[1-9][0-9]{0,8})(\.[0-9]{1,6})?$/', $value) !== 1 || preg_match('/[1-9]/', $value) !== 1) {
            throw new ContractViolation(
                ViolationCode::QuantityInvalid,
                'value',
                sprintf('« %s » n\'est pas une quantité décimale strictement positive.', $value),
            );
        }
    }
}
