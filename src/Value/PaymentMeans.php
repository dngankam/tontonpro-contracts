<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Value;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Vocabulary\PaymentMeansKind;

/**
 * Le moyen de paiement : sa nature, son identifiant dans la source, son
 * libellé figé.
 */
final readonly class PaymentMeans
{
    public function __construct(
        public PaymentMeansKind $kind,
        public string $methodId,
        public string $label,
    ) {
        if (trim($methodId) === '') {
            throw new ContractViolation(ViolationCode::PaymentMeansInvalid, 'methodId', 'L\'identifiant du moyen de paiement est obligatoire.');
        }

        if (trim($label) === '') {
            throw new ContractViolation(ViolationCode::PaymentMeansInvalid, 'label', 'Le libellé du moyen de paiement est obligatoire.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind->toArray(), 'methodId' => $this->methodId, 'label' => $this->label];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('kind', 'methodId', 'label');

        return new self(
            PaymentMeansKind::fromReader($reader->object('kind')),
            $reader->string('methodId'),
            $reader->string('label'),
        );
    }
}
