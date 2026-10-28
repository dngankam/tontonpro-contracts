<?php

declare(strict_types=1);

use TontonPro\Contracts\Facts\FactTypes;

arch('tous les fichiers déclarent strict_types', function () {
    expect(['TontonPro\Contracts', 'TontonPro\Contracts\Tests'])->toUseStrictTypes();
});

arch('toutes les classes sont finales', function () {
    expect('TontonPro\Contracts')->classes()->toBeFinal();
});

arch('aucune classe abstraite', function () {
    expect('TontonPro\Contracts')->classes()->not->toBeAbstract();
});

arch('objets de valeur, faits et composants sont immuables', function () {
    expect([
        'TontonPro\Contracts\Value',
        'TontonPro\Contracts\Facts',
        'TontonPro\Contracts\Accounts',
        'TontonPro\Contracts\Company',
        'TontonPro\Contracts\Serialization\PayloadReader',
        'TontonPro\Contracts\Menu\MenuGroup',
        'TontonPro\Contracts\Menu\MenuEntry',
        'TontonPro\Contracts\Dashboard\DashboardPanel',
        'TontonPro\Contracts\Dashboard\DashboardFigure',
        'TontonPro\Contracts\Vocabulary\CreditNoteReason',
        'TontonPro\Contracts\Vocabulary\CancellationReason',
        'TontonPro\Contracts\Vocabulary\AdjustmentReason',
        'TontonPro\Contracts\Vocabulary\ComplimentaryReason',
        'TontonPro\Contracts\Vocabulary\PaymentMeansKind',
    ])->classes()->toBeReadonly()->ignoring([FactTypes::class]);
});

arch('aucune dépendance hors du paquet et de PHP', function () {
    expect('TontonPro\Contracts')->toOnlyUse(['TontonPro\Contracts'])->ignoring('TontonPro\Contracts\Tests');
});
