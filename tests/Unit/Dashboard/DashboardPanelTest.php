<?php

declare(strict_types=1);

use TontonPro\Contracts\Dashboard\DashboardFigure;
use TontonPro\Contracts\Dashboard\DashboardPanel;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Value\BusinessDate;

/**
 * @return list<DashboardFigure>
 */
function panelFigures(): array
{
    return [DashboardFigure::count('hotelpro::dashboard.arrivals', 3), DashboardFigure::count('hotelpro::dashboard.departures', 2)];
}

describe('panneaux acceptés', function () {
    it('donnent à l\'hôte une forme complète', function () {
        $panel = new DashboardPanel(
            key: 'hotelpro.desk',
            owner: 'hotelpro',
            titleKey: 'hotelpro::dashboard.desk',
            ability: 'hotelpro.ledger.view',
            figures: panelFigures(),
            order: 10,
            routeName: 'hotelpro.ledger',
            linkTitleKey: 'hotelpro::nav.ledger',
            subtitle: 'Hôtel de démonstration',
            businessDate: new BusinessDate('2026-10-28'),
        );

        expect($panel->toArray())->toBe([
            'key' => 'hotelpro.desk',
            'titleKey' => 'hotelpro::dashboard.desk',
            'subtitle' => 'Hôtel de démonstration',
            'businessDate' => '2026-10-28',
            'order' => 10,
            'routeName' => 'hotelpro.ledger',
            'linkTitleKey' => 'hotelpro::nav.ledger',
            'figures' => array_map(fn (DashboardFigure $figure) => $figure->toArray(), panelFigures()),
        ]);
    });

    it('se passent de lien, de sous-titre et de journée', function () {
        $panel = new DashboardPanel('hotelpro.rooms', 'hotelpro', 't', 'a', panelFigures());

        expect([$panel->order, $panel->routeName, $panel->linkTitleKey, $panel->subtitle, $panel->businessDate])->toBe([0, null, null, null, null]);
    });
});

describe('panneaux refusés', function () {
    it('refuse une clé qui n\'appartient pas à son propriétaire', function (Closure $build) {
        expect(violation($build))->toBe(ViolationCode::DashboardKeyNotOwned);
    })->with([
        'autre propriétaire' => fn () => new DashboardPanel('restopro.desk', 'hotelpro', 't', 'a', panelFigures()),
        'propriétaire invalide' => fn () => new DashboardPanel('Hotel.desk', 'Hotel', 't', 'a', panelFigures()),
        'clé sans nom' => fn () => new DashboardPanel('hotelpro.', 'hotelpro', 't', 'a', panelFigures()),
    ]);

    it('refuse un panneau sans droit', function () {
        expect(violation(fn () => new DashboardPanel('hotelpro.desk', 'hotelpro', 't', ' ', panelFigures())))->toBe(ViolationCode::DashboardPanelWithoutAbility);
    });

    it('refuse un panneau mal formé', function (Closure $build, string $field) {
        expect(violationAt($build))->toBe(ViolationCode::DashboardPanelMalformed->value.'@'.$field);
    })->with([
        'titre absent' => [fn () => new DashboardPanel('hotelpro.desk', 'hotelpro', '', 'a', panelFigures()), 'titleKey'],
        'aucun indicateur' => [fn () => new DashboardPanel('hotelpro.desk', 'hotelpro', 't', 'a', []), 'figures'],
        'libellé en double' => [fn () => new DashboardPanel('hotelpro.desk', 'hotelpro', 't', 'a', [DashboardFigure::count('l', 1), DashboardFigure::count('l', 2)]), 'figures[1].labelKey'],
        'route sans libellé' => [fn () => new DashboardPanel('hotelpro.desk', 'hotelpro', 't', 'a', panelFigures(), 0, 'hotelpro.ledger'), 'linkTitleKey'],
        'libellé sans route' => [fn () => new DashboardPanel('hotelpro.desk', 'hotelpro', 't', 'a', panelFigures(), 0, null, 'hotelpro::nav.ledger'), 'linkTitleKey'],
        'route vide' => [fn () => new DashboardPanel('hotelpro.desk', 'hotelpro', 't', 'a', panelFigures(), 0, ' ', 'l'), 'routeName'],
        'sous-titre vide' => [fn () => new DashboardPanel('hotelpro.desk', 'hotelpro', 't', 'a', panelFigures(), 0, null, null, ''), 'subtitle'],
    ]);
});
