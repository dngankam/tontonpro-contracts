<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Accounts;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\SourceReference;

/**
 * Les comptes qu'un module demande d'ajouter à un référentiel (« syscohada »,
 * « pcg »…), éventuellement pour un profil national. Lue depuis un fichier de
 * données du module par `fromArray()`.
 *
 * « Ajouter sans rien écraser » est la règle de la comptabilité : le contrat
 * n'offre pas de fusion. Un parent non déclaré ici relève du plan de base.
 */
final readonly class AccountContribution
{
    /**
     * @param list<AccountDeclaration> $accounts
     */
    public function __construct(
        public string $module,
        public string $framework,
        public ?string $profile,
        public array $accounts,
    ) {
        foreach (['module' => $module, 'framework' => $framework] as $field => $value) {
            if (preg_match(SourceReference::SYSTEM_PATTERN, $value) !== 1) {
                throw new ContractViolation(ViolationCode::AccountContributionInvalid, $field, sprintf('« %s » est invalide pour « %s ».', $value, $field));
            }
        }

        if ($accounts === []) {
            throw new ContractViolation(ViolationCode::AccountsEmpty, 'accounts', 'Une contribution déclare au moins un compte.');
        }

        $numbers = [];

        foreach ($accounts as $index => $account) {
            if (isset($numbers[$account->number])) {
                throw new ContractViolation(
                    ViolationCode::AccountNumberDuplicate,
                    sprintf('accounts[%d].number', $index),
                    sprintf('Le compte %s est déclaré deux fois.', $account->number),
                );
            }

            $numbers[$account->number] = true;
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $reader = PayloadReader::of($data, '')->expect('module', 'framework', 'profile', 'accounts');

        return new self(
            $reader->string('module'),
            $reader->string('framework'),
            $reader->nullableString('profile'),
            array_map(AccountDeclaration::fromReader(...), $reader->objects('accounts')),
        );
    }

    /**
     * @return array{module: string, framework: string, profile: ?string, accounts: list<array{number: string, label: string, parent: ?string, role: ?string}>}
     */
    public function toArray(): array
    {
        return [
            'module' => $this->module,
            'framework' => $this->framework,
            'profile' => $this->profile,
            'accounts' => array_map(static fn (AccountDeclaration $account): array => $account->toArray(), $this->accounts),
        ];
    }
}
