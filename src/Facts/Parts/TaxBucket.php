<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts\Parts;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\Currency;
use TontonPro\Contracts\Value\Money;
use TontonPro\Contracts\Value\TaxRate;
use TontonPro\Contracts\Vocabulary\TaxTreatment;

/**
 * Une case de la ventilation fiscale, par (traitement, taux).
 *
 * `not_configured` : base et taxe inconnues (`null`), seul le TTC est connu.
 * Sinon base + taxe = TTC ; la taxe est nulle hors `taxable`.
 */
final readonly class TaxBucket
{
    public function __construct(
        public TaxTreatment $treatment,
        public ?TaxRate $rate,
        public ?Money $net,
        public ?Money $tax,
        public Money $gross,
    ) {
        if (! $treatment->acceptsRate($rate)) {
            throw $this->incoherent('rate', sprintf('Le traitement « %s » ne s\'accorde pas avec ce taux.', $treatment->value));
        }

        foreach (['net' => $net, 'tax' => $tax] as $field => $money) {
            if ($money !== null && ! $money->currency->equals($gross->currency)) {
                throw new ContractViolation(ViolationCode::CurrencyMismatch, $field, 'Une case de ventilation tient une seule devise.');
            }
        }

        if ($treatment === TaxTreatment::NotConfigured) {
            if ($net !== null || $tax !== null) {
                throw $this->incoherent('net', 'Un traitement non configuré ne connaît ni base ni taxe.');
            }

            return;
        }

        if ($net === null || $tax === null) {
            throw $this->incoherent('net', 'La base et la taxe sont obligatoires quand le traitement est connu.');
        }

        if (! $net->plus($tax)->equals($gross)) {
            throw $this->incoherent('gross', sprintf('Base %d + taxe %d ≠ TTC %d.', $net->amount, $tax->amount, $gross->amount));
        }

        if ($treatment !== TaxTreatment::Taxable && ! $tax->isZero()) {
            throw $this->incoherent('tax', sprintf('Le traitement « %s » ne porte aucune taxe.', $treatment->value));
        }
    }

    /**
     * La clé de la case : (traitement, taux).
     */
    public function key(): string
    {
        return self::keyOf($this->treatment, $this->rate);
    }

    public static function keyOf(TaxTreatment $treatment, ?TaxRate $rate): string
    {
        return $treatment->value.'|'.($rate === null ? '-' : (string) $rate->basisPoints);
    }

    public function currency(): Currency
    {
        return $this->gross->currency;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'treatment' => $this->treatment->value,
            'rate' => $this->rate?->basisPoints,
            'net' => $this->net?->toArray(),
            'tax' => $this->tax?->toArray(),
            'gross' => $this->gross->toArray(),
        ];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('treatment', 'rate', 'net', 'tax', 'gross');
        $net = $reader->nullableObject('net');
        $tax = $reader->nullableObject('tax');

        return new self(
            $reader->enum('treatment', TaxTreatment::class),
            Line::readRate($reader, 'rate'),
            $net === null ? null : Money::fromReader($net),
            $tax === null ? null : Money::fromReader($tax),
            Money::fromReader($reader->object('gross')),
        );
    }

    private function incoherent(string $field, string $message): ContractViolation
    {
        return new ContractViolation(ViolationCode::TaxBucketIncoherent, $field, $message);
    }
}
