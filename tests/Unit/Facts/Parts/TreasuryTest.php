<?php

declare(strict_types=1);

use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Facts\Parts\Allocation;
use TontonPro\Contracts\Facts\Parts\TreasuryMovement;
use TontonPro\Contracts\Facts\Parts\TreasuryOperation;
use TontonPro\Contracts\Tests\Support\Parts;
use TontonPro\Contracts\Tests\Support\Values;
use TontonPro\Contracts\Value\RateOverride;
use TontonPro\Contracts\Vocabulary\AllocationNature;
use TontonPro\Contracts\Vocabulary\MovementNature;
use TontonPro\Contracts\Vocabulary\RateRole;
use TontonPro\Contracts\Vocabulary\TreasuryDirection;

/**
 * Un mouvement EUR → XAF, ses champs facultatifs au choix.
 *
 * @param list<TontonPro\Contracts\Value\ExchangeRate> $rates
 */
function eurMovement(array $rates, ?int $accounting = null, ?RateOverride $override = null): TreasuryMovement
{
    return new TreasuryMovement(
        'm1', MovementNature::Received, Values::eurFund(), Values::cash(),
        Values::money(10000, Values::eur()), Values::money(65596),
        $accounting === null ? null : Values::money($accounting), $rates, $override,
    );
}

describe('TreasuryMovement', function () {
    it('accepte un mouvement en monnaie locale et un mouvement en devise', function () {
        expect(Parts::movement(60000)->counterValue->amount)->toBe(60000)
            ->and(Parts::eurReceived()->rate(RateRole::Proposed)?->rate)->toBe('655.957')
            ->and(eurMovement([Values::rate(), Values::rate(RateRole::Accounting)], 65600)->accountingCounterValue?->amount)->toBe(65600)
            ->and(eurMovement([Values::rate()], null, new RateOverride(Values::manager(), 'Arrondi accordé'))->rateOverride)->not->toBeNull();
    });

    it('refuse une référence de mouvement invalide', function () {
        expect(violation(fn () => Parts::movement(100, 'm 1')))->toBe(ViolationCode::MovementRefInvalid);
    });

    it('M1 : refuse un montant dans une autre devise que le fonds', function () {
        expect(violation(fn () => new TreasuryMovement('m1', MovementNature::Received, Values::eurFund(), Values::cash(), Values::money(100), Values::money(100))))
            ->toBe(ViolationCode::FundCurrencyMismatch);
    });

    it('M2 : refuse un mouvement nul', function () {
        expect(violation(fn () => Parts::movement(0)))->toBe(ViolationCode::MovementAmountZero);
    });

    it('M3 : refuse taux, contre-valeur distincte ou dérogation en monnaie locale', function (Closure $build) {
        expect(violation($build))->toBe(ViolationCode::MovementRateUnexpected);
    })->with([
        'contre-valeur distincte' => fn () => new TreasuryMovement('m1', MovementNature::Received, Values::xafFund(), Values::cash(), Values::money(100), Values::money(99)),
        'taux' => fn () => new TreasuryMovement('m1', MovementNature::Received, Values::xafFund(), Values::cash(), Values::money(100), Values::money(100), null, [Values::rate()]),
        'contre-valeur comptable' => fn () => new TreasuryMovement('m1', MovementNature::Received, Values::xafFund(), Values::cash(), Values::money(100), Values::money(100), Values::money(100)),
        'dérogation' => fn () => new TreasuryMovement('m1', MovementNature::Received, Values::xafFund(), Values::cash(), Values::money(100), Values::money(100), null, [], new RateOverride(Values::manager(), 'x')),
    ]);

    it('M4 : refuse une devise sans taux proposé', function () {
        expect(violation(fn () => eurMovement([])))->toBe(ViolationCode::MovementRateMissing)
            ->and(violation(fn () => eurMovement([Values::rate(RateRole::Accounting)], 65596)))->toBe(ViolationCode::MovementRateMissing);
    });

    it('M4 : refuse un taux dans le mauvais sens', function () {
        expect(violation(fn () => eurMovement([Values::rate(RateRole::Proposed, Values::xaf(), Values::eur())])))->toBe(ViolationCode::RateDirectionInvalid);
    });

    it('M5 : la contre-valeur comptable va avec le taux comptable', function () {
        expect(violation(fn () => eurMovement([Values::rate()], 65600)))->toBe(ViolationCode::MovementAccountingRateIncoherent)
            ->and(violation(fn () => eurMovement([Values::rate(), Values::rate(RateRole::Accounting)])))->toBe(ViolationCode::MovementAccountingRateIncoherent);
    });

    it('M6 : refuse le taux bancaire et deux taux de même rôle', function () {
        expect(violation(fn () => eurMovement([Values::rate(), Values::rate(RateRole::BankExecuted)])))->toBe(ViolationCode::RateRoleNotAllowed)
            ->and(violation(fn () => eurMovement([Values::rate(), Values::rate()])))->toBe(ViolationCode::RateRoleDuplicate);
    });

    it('refuse une contre-valeur comptable dans une autre monnaie', function () {
        expect(violation(fn () => new TreasuryMovement(
            'm1', MovementNature::Received, Values::eurFund(), Values::cash(), Values::money(10000, Values::eur()),
            Values::money(65596), Values::money(100, Values::eur()), [Values::rate(), Values::rate(RateRole::Accounting)],
        )))->toBe(ViolationCode::CurrencyMismatch);
    });
});

describe('Allocation', function () {
    it('accepte chaque nature bien formée', function (AllocationNature $nature) {
        expect(Parts::allocation($nature, 100)->nature)->toBe($nature);
    })->with(AllocationNature::cases());

    it('refuse une affectation incohérente', function (Closure $build) {
        expect(violation($build))->toBe(ViolationCode::AllocationIncoherent);
    })->with([
        'référence' => fn () => new Allocation('a 1', AllocationNature::Advance, Values::money(100)),
        'montant nul' => fn () => new Allocation('a1', AllocationNature::Advance, Values::money(0)),
        'règlement sans créance' => fn () => new Allocation('a1', AllocationNature::SettleReceivable, Values::money(100)),
        'pourboire sans bénéficiaire' => fn () => new Allocation('a1', AllocationNature::TipPayable, Values::money(100), null, ' '),
        'bénéficiaire hors pourboire' => fn () => new Allocation('a1', AllocationNature::Advance, Values::money(100), null, 'Marie'),
    ]);
});

describe('TreasuryOperation', function () {
    it('accepte l\'exemple de l\'expert : 100 EUR − 4 596 = 60 000 + 1 000 de pourboire', function () {
        $operation = Parts::expert();

        expect($operation->direction)->toBe(TreasuryDirection::Collection)
            ->and($operation->allocations[0]->amount->amount)->toBe(60000)
            ->and($operation->allocations[1]->amount->amount)->toBe(1000);
    });

    it('accepte sa variante sans pourboire : 65 596 − 5 596 = 60 000', function () {
        expect(Parts::expertWithoutTip()->allocations)->toHaveCount(1);
    });

    it('accepte un décaissement', function () {
        expect(Parts::payout(AllocationNature::Advance)->direction)->toBe(TreasuryDirection::Disbursement);
    });

    it('T3 : refuse d\'affecter les 65 596 entiers à la facture', function () {
        expect(violation(fn () => TreasuryOperation::collection(
            [Parts::eurReceived(), Parts::movement(4596, 'm2', MovementNature::ChangeReturned)],
            [Parts::allocation(AllocationNature::SettleReceivable, 65596)],
        )))->toBe(ViolationCode::TreasuryUnbalanced);
    });

    it('T3 : refuse un déséquilibre', function (Closure $build) {
        expect(violation($build))->toBe(ViolationCode::TreasuryUnbalanced);
    })->with([
        'rendu oublié' => fn () => TreasuryOperation::collection([Parts::eurReceived()], [Parts::allocation(AllocationNature::SettleReceivable, 60000)]),
        'sans affectation' => fn () => TreasuryOperation::collection([Parts::movement(100)], []),
        'décaissement' => fn () => TreasuryOperation::disbursement([Parts::movement(100, 'm1', MovementNature::PaidOut)], [Parts::allocation(AllocationNature::Advance, 90)]),
    ]);

    it('T1 : refuse des références en double', function () {
        expect(violation(fn () => TreasuryOperation::collection([Parts::movement(50), Parts::movement(50)], [Parts::allocation(AllocationNature::Advance, 100)])))
            ->toBe(ViolationCode::MovementRefDuplicate)
            ->and(violation(fn () => TreasuryOperation::collection([Parts::movement(100)], [Parts::allocation(AllocationNature::Advance, 50), Parts::allocation(AllocationNature::Advance, 50)])))
            ->toBe(ViolationCode::AllocationRefDuplicate);
    });

    it('T2 : refuse des natures de mouvement incohérentes avec le sens', function (Closure $build) {
        expect(violation($build))->toBe(ViolationCode::TreasuryNatureIncoherent);
    })->with([
        'encaissement sans entrée' => fn () => TreasuryOperation::collection([], [Parts::allocation(AllocationNature::Advance, 100)]),
        'encaissement avec sortie' => fn () => TreasuryOperation::collection(
            [Parts::movement(200), Parts::movement(100, 'm2', MovementNature::PaidOut)],
            [Parts::allocation(AllocationNature::Advance, 300)],
        ),
        'décaissement avec entrée' => fn () => TreasuryOperation::disbursement(
            [Parts::movement(100, 'm1', MovementNature::PaidOut), Parts::movement(100, 'm2')],
            [Parts::allocation(AllocationNature::Advance, 200)],
        ),
        'décaissement avec rendu' => fn () => TreasuryOperation::disbursement(
            [Parts::movement(100, 'm1', MovementNature::PaidOut), Parts::movement(10, 'm2', MovementNature::ChangeReturned)],
            [Parts::allocation(AllocationNature::Advance, 110)],
        ),
    ]);

    it('refuse une affectation hors de la monnaie des contre-valeurs', function () {
        expect(violation(fn () => TreasuryOperation::collection([Parts::movement(100)], [new Allocation('a1', AllocationNature::Advance, Values::money(100, Values::eur()))])))
            ->toBe(ViolationCode::CurrencyMismatch);
    });

    it('impose au fait son sens et ses natures permises', function () {
        expect(violation(fn () => Parts::expert()->require(TreasuryDirection::Disbursement, [AllocationNature::SettleReceivable])))
            ->toBe(ViolationCode::TreasuryDirectionInvalid)
            ->and(violation(fn () => Parts::expert()->require(TreasuryDirection::Collection, [AllocationNature::SettleReceivable])))
            ->toBe(ViolationCode::AllocationNatureNotAllowed)
            ->and(violation(fn () => Parts::expert()->require(TreasuryDirection::Collection, [AllocationNature::SettleReceivable, AllocationNature::TipPayable])))
            ->toBeNull();
    });
});
