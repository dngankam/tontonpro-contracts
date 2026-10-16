<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Accounts;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;

/**
 * Un compte qu'un module demande d'ajouter au plan. Le numéro vient d'un
 * fichier de données du module, jamais du code.
 */
final readonly class AccountDeclaration
{
    public function __construct(
        public string $number,
        public string $label,
        public ?string $parent = null,
        public ?string $role = null,
    ) {
        if (preg_match('/^[0-9A-Z]{1,20}$/', $number) !== 1) {
            throw new ContractViolation(ViolationCode::AccountNumberInvalid, 'number', sprintf('Le numéro de compte « %s » est invalide.', $number));
        }

        if (trim($label) === '') {
            throw new ContractViolation(ViolationCode::AccountLabelMissing, 'label', sprintf('Le compte %s a un libellé.', $number));
        }

        if ($parent === $number) {
            throw new ContractViolation(ViolationCode::AccountParentInvalid, 'parent', sprintf('Le compte %s ne peut pas être son propre parent.', $number));
        }
    }

    /**
     * @return array{number: string, label: string, parent: ?string, role: ?string}
     */
    public function toArray(): array
    {
        return ['number' => $this->number, 'label' => $this->label, 'parent' => $this->parent, 'role' => $this->role];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('number', 'label', 'parent', 'role');

        return new self(
            $reader->string('number'),
            $reader->string('label'),
            $reader->nullableString('parent'),
            $reader->nullableString('role'),
        );
    }
}
