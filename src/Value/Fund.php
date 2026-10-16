<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Value;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;

/**
 * La caisse ou le compte qui a réellement reçu ou rendu les fonds, et la
 * devise qu'il détient : une caisse EUR ne reçoit pas de XAF.
 */
final readonly class Fund
{
    public function __construct(
        public string $system,
        public string $id,
        public string $label,
        public Currency $currency,
    ) {
        SourceReference::requireSystem($system, 'system');

        if (preg_match(SourceReference::LOCAL_ID_PATTERN, $id) !== 1) {
            throw new ContractViolation(ViolationCode::FundInvalid, 'id', sprintf('L\'identifiant de fonds « %s » est invalide.', $id));
        }

        if (trim($label) === '') {
            throw new ContractViolation(ViolationCode::FundInvalid, 'label', 'Le libellé du fonds est obligatoire.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'system' => $this->system,
            'id' => $this->id,
            'label' => $this->label,
            'currency' => $this->currency->toArray(),
        ];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('system', 'id', 'label', 'currency');

        return new self(
            $reader->string('system'),
            $reader->string('id'),
            $reader->string('label'),
            Currency::fromReader($reader->object('currency')),
        );
    }
}
