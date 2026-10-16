<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Value;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;

/**
 * Un taux de taxe en points de base : 1925 = 19,25 %.
 */
final readonly class TaxRate
{
    public function __construct(
        public int $basisPoints,
    ) {
        if ($basisPoints < 0 || $basisPoints > 10000) {
            throw new ContractViolation(
                ViolationCode::TaxRateInvalid,
                'basisPoints',
                sprintf('Un taux va de 0 à 10000 points de base (%d reçu).', $basisPoints),
            );
        }
    }

    public function isZero(): bool
    {
        return $this->basisPoints === 0;
    }
}
