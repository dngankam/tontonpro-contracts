<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Value;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;

/**
 * L'établissement, figé au moment du fait : système, identifiant, nom et
 * monnaie. Sa monnaie est celle de tous les montants du fait (hors montant
 * réellement reçu d'un mouvement de trésorerie).
 */
final readonly class Establishment
{
    public function __construct(
        public string $system,
        public string $id,
        public string $name,
        public Currency $currency,
    ) {
        SourceReference::requireSystem($system, 'system');

        if (preg_match(SourceReference::LOCAL_ID_PATTERN, $id) !== 1) {
            throw new ContractViolation(
                ViolationCode::EstablishmentInvalid,
                'id',
                sprintf('L\'identifiant d\'établissement « %s » est invalide.', $id),
            );
        }

        if (trim($name) === '' || preg_match('/^.{1,191}$/su', $name) !== 1) {
            throw new ContractViolation(
                ViolationCode::EstablishmentInvalid,
                'name',
                'Le nom de l\'établissement est obligatoire (191 caractères au plus).',
            );
        }
    }

    /**
     * @return array{system: string, id: string, name: string, currency: array{code: string, decimals: int}}
     */
    public function toArray(): array
    {
        return [
            'system' => $this->system,
            'id' => $this->id,
            'name' => $this->name,
            'currency' => $this->currency->toArray(),
        ];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('system', 'id', 'name', 'currency');

        return new self(
            $reader->string('system'),
            $reader->string('id'),
            $reader->string('name'),
            Currency::fromReader($reader->object('currency')),
        );
    }
}
