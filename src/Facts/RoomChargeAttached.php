<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts;

use TontonPro\Contracts\Company\SameCompanyAnswer;
use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Facts\Concerns\DescribesMoneyFact;
use TontonPro\Contracts\Facts\Parts\LineSet;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\Establishment;
use TontonPro\Contracts\Value\FactReference;
use TontonPro\Contracts\Value\Party;
use TontonPro\Contracts\Value\SourceReference;

/**
 * `room_charge.attached` — côté hôtel : les lignes reportées rejoignent la note
 * SANS produit ni taxe nouveaux. Chaque ligne désigne la vente d'origine qui
 * a déjà reconnu le produit.
 */
final readonly class RoomChargeAttached implements MoneyFact
{
    use DescribesMoneyFact;

    public function __construct(
        public FactHeader $header,
        public SourceReference $folio,
        public Party $debtor,
        public FactReference $originSale,
        public Establishment $originEstablishment,
        public LineSet $charges,
        public SameCompanyAnswer $companyCheck,
    ) {
        $originSale->requireType(SaleCompleted::type(), 'originSale');
        $this->requireCurrency($originEstablishment->currency, 'originEstablishment.currency');
        $this->requireLines($charges, 'charges');
        $charges->requirePositiveTotal('charges');

        foreach ($charges->lines as $index => $line) {
            if ($line->recognizedBy === null || ! $line->recognizedBy->equals($originSale)) {
                throw new ContractViolation(
                    ViolationCode::RoomChargeLineNotLinked,
                    sprintf('charges.lines[%d].recognizedBy', $index),
                    'Une ligne reportée désigne la vente d\'origine : elle ne recrée ni produit ni taxe.',
                );
            }
        }

        self::requireRoomChargePermitted($companyCheck, 'companyCheck');
    }

    public static function type(): string
    {
        return 'room_charge.attached';
    }

    public function payload(): array
    {
        return [
            'folio' => $this->folio->toArray(),
            'debtor' => $this->debtor->toArray(),
            'originSale' => $this->originSale->toArray(),
            'originEstablishment' => $this->originEstablishment->toArray(),
            'charges' => $this->charges->toArray(),
            'companyCheck' => $this->companyCheck->value,
        ];
    }

    public static function fromPayload(FactHeader $header, array $payload): static
    {
        $reader = PayloadReader::of($payload, 'payload')
            ->expect('folio', 'debtor', 'originSale', 'originEstablishment', 'charges', 'companyCheck');

        return new static(
            $header,
            SourceReference::fromReader($reader->object('folio')),
            Party::fromReader($reader->object('debtor')),
            FactReference::fromReader($reader->object('originSale')),
            Establishment::fromReader($reader->object('originEstablishment')),
            LineSet::fromReader($reader->object('charges')),
            $reader->enum('companyCheck', SameCompanyAnswer::class),
        );
    }
}
