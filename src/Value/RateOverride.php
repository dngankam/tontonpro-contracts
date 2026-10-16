<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Value;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;

/**
 * Une dérogation au taux proposé : son auteur et son motif.
 */
final readonly class RateOverride
{
    public function __construct(
        public Actor $actor,
        public string $reason,
    ) {
        if (trim($reason) === '') {
            throw new ContractViolation(
                ViolationCode::RateOverrideReasonMissing,
                'reason',
                'Une dérogation au taux proposé exige un motif.',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['actor' => $this->actor->toArray(), 'reason' => $this->reason];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('actor', 'reason');

        return new self(Actor::fromReader($reader->object('actor')), $reader->string('reason'));
    }
}
