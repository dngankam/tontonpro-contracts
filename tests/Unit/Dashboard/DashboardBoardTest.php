<?php

declare(strict_types=1);

use TontonPro\Contracts\Dashboard\DashboardBoard;
use TontonPro\Contracts\Dashboard\DashboardContributor;
use TontonPro\Contracts\Dashboard\DashboardFigure;
use TontonPro\Contracts\Dashboard\DashboardPanel;
use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Value\Currency;
use TontonPro\Contracts\Value\Money;
use TontonPro\Contracts\Value\UtcInstant;

function boardPanel(string $key, int $order = 0, string $ability = 'a'): DashboardPanel
{
    $owner = explode('.', $key)[0];

    return new DashboardPanel($key, $owner, $owner.'::dashboard.title', $ability, [DashboardFigure::count($owner.'::dashboard.count', 1)], $order);
}

function boardNow(): UtcInstant
{
    return new UtcInstant(new DateTimeImmutable('now', new DateTimeZone('UTC')));
}

/**
 * Un module qui ne connaît que l'utilisateur `$known` : les autres n'ont rien.
 *
 * @param (Closure(int, UtcInstant): void)|null $onCall
 */
function boardHotelContributor(int $known, ?Closure $onCall = null): DashboardContributor
{
    return new class ($known, $onCall) implements DashboardContributor {
        /**
         * @param (Closure(int, UtcInstant): void)|null $onCall
         */
        public function __construct(private readonly int $known, private readonly ?Closure $onCall)
        {
        }

        public function contributeTo(DashboardBoard $board, int $userId, UtcInstant $now): void
        {
            if ($this->onCall !== null) {
                ($this->onCall)($userId, $now);
            }

            if ($userId !== $this->known) {
                return;
            }

            $board->addPanel(new DashboardPanel('hotelpro.desk', 'hotelpro', 'hotelpro::dashboard.desk', 'hotelpro.ledger.view', [
                DashboardFigure::percentBasisPoints('hotelpro::dashboard.occupancy', 7500),
                DashboardFigure::money('hotelpro::dashboard.collected_xaf', new Money(152500, new Currency('XAF', 0))),
                DashboardFigure::money('hotelpro::dashboard.collected_eur', new Money(4000, new Currency('EUR', 2))),
            ], 10, 'hotelpro.ledger', 'hotelpro::nav.ledger'));
            $board->addPanel(boardPanel('hotelpro.housekeeping', 20, 'hotelpro.rooms.view'));
        }
    };
}

describe('deux modules, un tableau de bord', function () {
    it('passent au contributeur l\'utilisateur et l\'instant UTC de l\'hôte', function () {
        $user = random_int(1, PHP_INT_MAX);
        $now = boardNow();
        $calls = [];
        boardHotelContributor($user, function (int $userId, UtcInstant $at) use (&$calls) {
            $calls[] = [$userId, $at->toString()];
        })->contributeTo(new DashboardBoard(), $user, $now);

        expect($calls)->toBe([[$user, $now->toString()]]);
    });

    it('ne donnent rien à un utilisateur inconnu du module : tableau vide, sans erreur', function () {
        $user = random_int(1, PHP_INT_MAX - 1);
        $board = new DashboardBoard();
        boardHotelContributor($user)->contributeTo($board, $user + 1, boardNow());

        expect($board->panels())->toBe([])
            ->and($board->visibleFor(fn (string $ability) => true))->toBe([]);
    });

    it('trient par rang puis par ordre d\'ajout, tous modules confondus', function () {
        $board = new DashboardBoard();
        $board->addPanel(boardPanel('hotelpro.b', 1));
        $board->addPanel(boardPanel('comptapro.a', 1));
        $board->addPanel(boardPanel('restopro.c', 0));
        $board->addPanel(boardPanel('hotelpro.d', -1));

        expect(array_map(fn (DashboardPanel $panel) => $panel->key, $board->panels()))->toBe(['hotelpro.d', 'restopro.c', 'hotelpro.b', 'comptapro.a']);
    });

    it('ne montrent que ce que le droit permet, dans l\'ordre', function () {
        $user = random_int(1, PHP_INT_MAX);
        $board = new DashboardBoard();
        boardHotelContributor($user)->contributeTo($board, $user, boardNow());

        $visible = $board->visibleFor(fn (string $ability) => $ability === 'hotelpro.ledger.view');

        expect(array_column($visible, 'key'))->toBe(['hotelpro.desk'])
            ->and(array_column($visible[0]['figures'], 'currency'))->toBe([null, ['code' => 'XAF', 'decimals' => 0], ['code' => 'EUR', 'decimals' => 2]])
            ->and(array_column($board->visibleFor(fn (string $ability) => true), 'key'))->toBe(['hotelpro.desk', 'hotelpro.housekeeping'])
            ->and($board->visibleFor(fn (string $ability) => false))->toBe([]);
    });
});

describe('refus du tableau de bord', function () {
    it('refuse un panneau en double', function () {
        $board = new DashboardBoard();
        $board->addPanel(boardPanel('hotelpro.desk', 1));

        expect(violation(fn () => $board->addPanel(boardPanel('hotelpro.desk', 2))))->toBe(ViolationCode::DashboardPanelTaken)
            ->and($board->panels()[0]->order)->toBe(1);
    });

    it('garde les panneaux d\'un module quand un autre lève', function () {
        $user = random_int(1, PHP_INT_MAX);
        $board = new DashboardBoard();
        boardHotelContributor($user)->contributeTo($board, $user, boardNow());

        $faulty = new class implements DashboardContributor {
            public function contributeTo(DashboardBoard $board, int $userId, UtcInstant $now): void
            {
                $board->addPanel(boardPanel('restopro.sales'));
                $board->addPanel(boardPanel('restopro.sales'));
            }
        };

        expect(fn () => $faulty->contributeTo($board, $user, boardNow()))->toThrow(ContractViolation::class)
            ->and(array_map(fn (DashboardPanel $panel) => $panel->key, $board->panels()))->toBe(['restopro.sales', 'hotelpro.desk', 'hotelpro.housekeeping']);
    });
});
