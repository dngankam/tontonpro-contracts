<?php

declare(strict_types=1);

use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Menu\MenuContributor;
use TontonPro\Contracts\Menu\MenuEntry;
use TontonPro\Contracts\Menu\MenuGroup;
use TontonPro\Contracts\Menu\MenuRegistry;

function hotelContributor(): MenuContributor
{
    return new class implements MenuContributor {
        public function contributeTo(MenuRegistry $menu): void
        {
            $menu->addGroup(new MenuGroup('hotelpro.desk', 'hotelpro', 'hotelpro::nav.desk', 10, true, true));
            $menu->addEntry(new MenuEntry('hotelpro.ledger', 'hotelpro', 'hotelpro.desk', 'hotelpro::nav.ledger', 'book', 'hotelpro.ledger', 'hotelpro.ledger.view', 1));
        }
    };
}

function restaurantContributor(): MenuContributor
{
    return new class implements MenuContributor {
        public function contributeTo(MenuRegistry $menu): void
        {
            $menu->addGroup(new MenuGroup('restopro.pos', 'restopro', 'restopro::nav.pos', 5));
            $menu->addEntry(new MenuEntry('restopro.tables', 'restopro', 'restopro.pos', 'restopro::nav.tables', 'table', 'restopro.tables', 'restopro.tables.view'));
            // Une entrée posée dans le groupe d'un AUTRE paquet : elle garde son propriétaire et son droit.
            $menu->addEntry(new MenuEntry('restopro.room_charges', 'restopro', 'hotelpro.desk', 'restopro::nav.room_charges', 'bed', null, 'restopro.room_charges.view', 2, true));
        }
    };
}

function sharedMenu(): MenuRegistry
{
    $menu = new MenuRegistry();
    hotelContributor()->contributeTo($menu);
    restaurantContributor()->contributeTo($menu);

    return $menu;
}

describe('deux paquets, un menu', function () {
    it('ajoutent leurs groupes sans s\'écraser', function () {
        $menu = sharedMenu();

        expect(array_map(fn (MenuGroup $group) => $group->key, $menu->groups()))->toBe(['restopro.pos', 'hotelpro.desk'])
            ->and(violation(fn () => $menu->addGroup(new MenuGroup('hotelpro.desk', 'hotelpro', 'autre.titre', 10))))->toBe(ViolationCode::MenuGroupTaken)
            ->and($menu->groups()[1]->titleKey)->toBe('hotelpro::nav.desk');
    });

    it('gardent chacun le droit de leurs entrées', function () {
        $entries = sharedMenu()->entriesOf('hotelpro.desk');

        expect(array_map(fn (MenuEntry $entry) => [$entry->owner, $entry->ability], $entries))->toBe([
            ['hotelpro', 'hotelpro.ledger.view'],
            ['restopro', 'restopro.room_charges.view'],
        ]);
    });

    it('ne montrent que ce que le droit permet, et omettent les groupes vides', function () {
        $visible = sharedMenu()->visibleFor(fn (string $ability) => $ability === 'restopro.room_charges.view');

        expect($visible)->toBe([[
            'key' => 'hotelpro.desk',
            'titleKey' => 'hotelpro::nav.desk',
            'order' => 10,
            'collapsible' => true,
            'defaultOpen' => true,
            'items' => [[
                'key' => 'restopro.room_charges',
                'titleKey' => 'restopro::nav.room_charges',
                'icon' => 'bed',
                'routeName' => null,
                'disabled' => true,
            ]],
        ]])->and(sharedMenu()->visibleFor(fn (string $ability) => false))->toBe([]);
    });

    it('trient par rang puis par ordre d\'ajout', function () {
        $menu = new MenuRegistry();
        $menu->addGroup(new MenuGroup('hotelpro.b', 'hotelpro', 't', 1));
        $menu->addGroup(new MenuGroup('hotelpro.a', 'hotelpro', 't', 1));
        $menu->addGroup(new MenuGroup('hotelpro.c', 'hotelpro', 't', 0));

        expect(array_map(fn (MenuGroup $group) => $group->key, $menu->groups()))->toBe(['hotelpro.c', 'hotelpro.b', 'hotelpro.a']);
    });

    it('acceptent qu\'un groupe soit redéclaré à l\'identique', function () {
        $menu = sharedMenu();
        $menu->addGroup(new MenuGroup('hotelpro.desk', 'hotelpro', 'hotelpro::nav.desk', 10, true, true));

        expect($menu->groups())->toHaveCount(2);
    });
});

describe('refus du registre', function () {
    it('refuse une entrée en double et un groupe inconnu', function () {
        $menu = sharedMenu();

        expect(violation(fn () => $menu->addEntry(new MenuEntry('hotelpro.ledger', 'hotelpro', 'hotelpro.desk', 't', 'i', null, 'a'))))->toBe(ViolationCode::MenuEntryTaken)
            ->and(violation(fn () => $menu->addEntry(new MenuEntry('hotelpro.other', 'hotelpro', 'hotelpro.unknown', 't', 'i', null, 'a'))))->toBe(ViolationCode::MenuGroupUnknown);
    });

    it('refuse une clé qui n\'appartient pas à son propriétaire', function (Closure $build) {
        expect(violation($build))->toBe(ViolationCode::MenuKeyNotOwned);
    })->with([
        'groupe' => fn () => new MenuGroup('restopro.desk', 'hotelpro', 't', 1),
        'entrée' => fn () => new MenuEntry('restopro.ledger', 'hotelpro', 'hotelpro.desk', 't', 'i', null, 'a'),
        'propriétaire invalide' => fn () => new MenuGroup('Hotel.desk', 'Hotel', 't', 1),
        'clé sans nom' => fn () => new MenuGroup('hotelpro.', 'hotelpro', 't', 1),
    ]);

    it('refuse un titre absent', function () {
        expect(violation(fn () => new MenuGroup('hotelpro.desk', 'hotelpro', ' ', 1)))->toBe(ViolationCode::MenuTitleMissing)
            ->and(violation(fn () => new MenuEntry('hotelpro.ledger', 'hotelpro', 'hotelpro.desk', '', 'i', null, 'a')))->toBe(ViolationCode::MenuTitleMissing);
    });

    it('refuse une entrée sans droit', function () {
        expect(violation(fn () => new MenuEntry('hotelpro.ledger', 'hotelpro', 'hotelpro.desk', 't', 'i', null, ' ')))->toBe(ViolationCode::MenuEntryWithoutAbility);
    });
});
