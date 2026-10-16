<?php

declare(strict_types=1);

use TontonPro\Contracts\Accounts\AccountContribution;
use TontonPro\Contracts\Accounts\AccountContributor;
use TontonPro\Contracts\Accounts\AccountDeclaration;
use TontonPro\Contracts\Exceptions\ViolationCode;

/**
 * @param list<array<string, mixed>> $accounts
 *
 * @return array<string, mixed>
 */
function contributionData(array $accounts = [], string $module = 'hotelpro'): array
{
    return [
        'module' => $module,
        'framework' => 'syscohada',
        'profile' => 'cm',
        'accounts' => $accounts === [] ? [
            ['number' => '4221', 'label' => 'Pourboires à reverser', 'parent' => '422', 'role' => 'tips.payable'],
            ['number' => '4191', 'label' => 'Cautions reçues', 'parent' => null, 'role' => null],
        ] : $accounts,
    ];
}

it('lit une contribution depuis des données et la réécrit à l\'identique', function () {
    $contribution = AccountContribution::fromArray(contributionData());

    expect($contribution->accounts)->toHaveCount(2)
        ->and($contribution->accounts[0]->role)->toBe('tips.payable')
        ->and($contribution->toArray())->toBe(contributionData());
});

it('refuse une contribution invalide', function (Closure $build, ViolationCode $code) {
    expect(violation($build))->toBe($code);
})->with([
    'numéro invalide' => [fn () => new AccountDeclaration('42-21', 'Pourboires'), ViolationCode::AccountNumberInvalid],
    'libellé vide' => [fn () => new AccountDeclaration('4221', ' '), ViolationCode::AccountLabelMissing],
    'son propre parent' => [fn () => new AccountDeclaration('4221', 'Pourboires', '4221'), ViolationCode::AccountParentInvalid],
    'numéro en double' => [fn () => AccountContribution::fromArray(contributionData([
        ['number' => '4221', 'label' => 'A', 'parent' => null, 'role' => null],
        ['number' => '4221', 'label' => 'B', 'parent' => null, 'role' => null],
    ])), ViolationCode::AccountNumberDuplicate],
    'aucun compte' => [fn () => new AccountContribution('hotelpro', 'syscohada', null, []), ViolationCode::AccountsEmpty],
    'module invalide' => [fn () => AccountContribution::fromArray(contributionData([], 'Hotel')), ViolationCode::AccountContributionInvalid],
]);

it('refuse des données mal formées', function () {
    $data = contributionData();
    unset($data['profile']);

    expect(malformedPath(fn () => AccountContribution::fromArray($data)))->toBe('profile');
});

it('se déclare par un contributeur', function () {
    $contributor = new class implements AccountContributor {
        public function accountContributions(): iterable
        {
            yield AccountContribution::fromArray(contributionData());
        }
    };

    expect(iterator_to_array($contributor->accountContributions()))->toHaveCount(1);
});
