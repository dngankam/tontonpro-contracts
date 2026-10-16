<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts\Parts;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\BusinessDate;
use TontonPro\Contracts\Value\Complimentary;
use TontonPro\Contracts\Value\FactReference;
use TontonPro\Contracts\Value\Money;
use TontonPro\Contracts\Value\Quantity;
use TontonPro\Contracts\Value\SourceReference;
use TontonPro\Contracts\Value\TaxRate;
use TontonPro\Contracts\Vocabulary\LineKind;
use TontonPro\Contracts\Vocabulary\TaxTreatment;

/**
 * Une ligne de prestation ou de remise. Le montant est positif ou nul, sur la
 * base de prix de l'ensemble (TTC ou HT) ; c'est `kind` qui dit son sens.
 *
 * Gratuité : une ligne `charge` à 0 exige sa qualification (`complimentary`).
 * Une ligne qualifiée est une `charge` à 0 exactement — jamais une remise qui
 * l'annulerait — et son traitement fiscal est connu (pas `not_configured`).
 */
final readonly class Line
{
    public function __construct(
        public string $lineRef,
        public LineKind $kind,
        public string $label,
        public Quantity $quantity,
        public string $revenueCategory,
        public Money $amount,
        public TaxTreatment $taxTreatment,
        public ?TaxRate $taxRate = null,
        public ?string $legalMention = null,
        public ?BusinessDate $serviceDate = null,
        public ?FactReference $recognizedBy = null,
        public ?Complimentary $complimentary = null,
    ) {
        if (preg_match(SourceReference::LOCAL_ID_PATTERN, $lineRef) !== 1) {
            throw new ContractViolation(ViolationCode::LineInvalid, 'lineRef', sprintf('La référence de ligne « %s » est invalide.', $lineRef));
        }

        if (trim($label) === '') {
            throw new ContractViolation(ViolationCode::LineInvalid, 'label', 'Le libellé de la ligne est obligatoire.');
        }

        if (preg_match('/^[A-Za-z0-9_.-]{1,32}$/', $revenueCategory) !== 1) {
            throw new ContractViolation(ViolationCode::LineInvalid, 'revenueCategory', sprintf('La catégorie « %s » est invalide.', $revenueCategory));
        }

        if (! $taxTreatment->acceptsRate($taxRate)) {
            throw new ContractViolation(
                ViolationCode::LineTaxIncoherent,
                'taxRate',
                sprintf('Le traitement « %s » ne s\'accorde pas avec ce taux.', $taxTreatment->value),
            );
        }

        $this->requireQualifiedGratuity();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lineRef' => $this->lineRef,
            'kind' => $this->kind->value,
            'label' => $this->label,
            'quantity' => $this->quantity->value,
            'revenueCategory' => $this->revenueCategory,
            'amount' => $this->amount->toArray(),
            'taxTreatment' => $this->taxTreatment->value,
            'taxRate' => $this->taxRate?->basisPoints,
            'legalMention' => $this->legalMention,
            'serviceDate' => $this->serviceDate?->value,
            'recognizedBy' => $this->recognizedBy?->toArray(),
            'complimentary' => $this->complimentary?->toArray(),
        ];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect(
            'lineRef', 'kind', 'label', 'quantity', 'revenueCategory', 'amount', 'taxTreatment',
            'taxRate', 'legalMention', 'serviceDate', 'recognizedBy', 'complimentary',
        );
        $recognizedBy = $reader->nullableObject('recognizedBy');
        $complimentary = $reader->nullableObject('complimentary');
        $serviceDate = $reader->nullableString('serviceDate');

        return new self(
            lineRef: $reader->string('lineRef'),
            kind: $reader->enum('kind', LineKind::class),
            label: $reader->string('label'),
            quantity: new Quantity($reader->string('quantity')),
            revenueCategory: $reader->string('revenueCategory'),
            amount: Money::fromReader($reader->object('amount')),
            taxTreatment: $reader->enum('taxTreatment', TaxTreatment::class),
            taxRate: self::readRate($reader, 'taxRate'),
            legalMention: $reader->nullableString('legalMention'),
            serviceDate: $serviceDate === null ? null : new BusinessDate($serviceDate),
            recognizedBy: $recognizedBy === null ? null : FactReference::fromReader($recognizedBy),
            complimentary: $complimentary === null ? null : Complimentary::fromReader($complimentary),
        );
    }

    /**
     * Un taux sérialisé : ses points de base, ou `null`.
     */
    public static function readRate(PayloadReader $reader, string $key): ?TaxRate
    {
        return $reader->nullableInt($key) === null ? null : new TaxRate($reader->int($key));
    }

    private function requireQualifiedGratuity(): void
    {
        $complimentary = $this->complimentary;

        if ($complimentary === null) {
            if ($this->kind === LineKind::Charge && $this->amount->isZero()) {
                throw new ContractViolation(
                    ViolationCode::ZeroChargeWithoutQualification,
                    'amount',
                    'Une ligne à 0 doit être qualifiée de gratuité : un prix absent n\'est pas une gratuité.',
                );
            }

            return;
        }

        if ($this->kind !== LineKind::Charge) {
            throw new ContractViolation(ViolationCode::ComplimentaryNotOnCharge, 'complimentary', 'Seule une ligne de prestation peut être offerte.');
        }

        if (! $this->amount->isZero()) {
            throw new ContractViolation(ViolationCode::ComplimentaryAmountNotZero, 'amount', 'Une ligne offerte vaut 0.');
        }

        if ($this->taxTreatment === TaxTreatment::NotConfigured) {
            throw new ContractViolation(
                ViolationCode::ComplimentaryTaxNotConfigured,
                'taxTreatment',
                'Une gratuité porte un traitement fiscal connu, pas « not_configured ».',
            );
        }

        $price = $complimentary->referencePrice;

        if ($price !== null && ! $price->currency->equals($this->amount->currency)) {
            throw new ContractViolation(
                ViolationCode::ComplimentaryReferencePriceInvalid,
                'complimentary.referencePrice',
                'Le prix de référence est dans la devise de la ligne.',
            );
        }
    }
}
