<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts;

use TontonPro\Contracts\Company\SameCompanyAnswer;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Facts\Concerns\DescribesMoneyFact;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\Establishment;
use TontonPro\Contracts\Value\Money;
use TontonPro\Contracts\Value\Party;
use TontonPro\Contracts\Value\SourceReference;

/**
 * `sale.charged_to_room` — côté point de vente : la vente est réglée par
 * report, TTC, sur la note d'un client d'un établissement de la même société.
 */
final readonly class SaleChargedToRoom implements MoneyFact
{
    use DescribesMoneyFact;

    public function __construct(
        public FactHeader $header,
        public string $documentNumber,
        public Money $amount,
        public SourceReference $folio,
        public ?Party $debtor,
        public Establishment $receivingEstablishment,
        public SameCompanyAnswer $companyCheck,
    ) {
        self::requireText($documentNumber, ViolationCode::DocumentNumberMissing, 'documentNumber', 'Le numéro de vente est obligatoire.');
        $this->requirePositive($amount, 'amount');
        $this->requireCurrency($receivingEstablishment->currency, 'receivingEstablishment.currency');
        self::requireRoomChargePermitted($companyCheck, 'companyCheck');
    }

    public static function type(): string
    {
        return 'sale.charged_to_room';
    }

    public function payload(): array
    {
        return [
            'documentNumber' => $this->documentNumber,
            'amount' => $this->amount->toArray(),
            'folio' => $this->folio->toArray(),
            'debtor' => $this->debtor?->toArray(),
            'receivingEstablishment' => $this->receivingEstablishment->toArray(),
            'companyCheck' => $this->companyCheck->value,
        ];
    }

    public static function fromPayload(FactHeader $header, array $payload): static
    {
        $reader = PayloadReader::of($payload, 'payload')
            ->expect('documentNumber', 'amount', 'folio', 'debtor', 'receivingEstablishment', 'companyCheck');
        $debtor = $reader->nullableObject('debtor');

        return new static(
            $header,
            $reader->string('documentNumber'),
            Money::fromReader($reader->object('amount')),
            SourceReference::fromReader($reader->object('folio')),
            $debtor === null ? null : Party::fromReader($debtor),
            Establishment::fromReader($reader->object('receivingEstablishment')),
            $reader->enum('companyCheck', SameCompanyAnswer::class),
        );
    }
}
