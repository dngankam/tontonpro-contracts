<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Value;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Vocabulary\ComplimentaryReason;

/**
 * La qualification d'une gratuité : pourquoi, qui l'a accordée, et le prix de
 * référence quand il existe. On n'invente jamais de prix : sans prix connu,
 * `referencePrice` est `null`.
 *
 * Seule une ligne qualifiée ainsi peut valoir 0 : un prix absent ou un
 * paramétrage incomplet ne devient jamais une gratuité.
 */
final readonly class Complimentary
{
    public function __construct(
        public ComplimentaryReason $reason,
        public Actor $approver,
        public ?Money $referencePrice = null,
    ) {
        if ($referencePrice !== null && $referencePrice->isZero()) {
            throw new ContractViolation(
                ViolationCode::ComplimentaryReferencePriceInvalid,
                'referencePrice',
                'Le prix de référence d\'une gratuité est strictement positif, ou absent.',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reason' => $this->reason->toArray(),
            'approver' => $this->approver->toArray(),
            'referencePrice' => $this->referencePrice?->toArray(),
        ];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('reason', 'approver', 'referencePrice');
        $price = $reader->nullableObject('referencePrice');

        return new self(
            ComplimentaryReason::fromReader($reader->object('reason')),
            Actor::fromReader($reader->object('approver')),
            $price === null ? null : Money::fromReader($price),
        );
    }
}
