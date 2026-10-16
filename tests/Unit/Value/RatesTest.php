<?php

declare(strict_types=1);

use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Tests\Support\Values;
use TontonPro\Contracts\Value\ExchangeRate;
use TontonPro\Contracts\Value\Fund;
use TontonPro\Contracts\Value\PaymentMeans;
use TontonPro\Contracts\Value\Quantity;
use TontonPro\Contracts\Value\RateOverride;
use TontonPro\Contracts\Value\TaxRate;
use TontonPro\Contracts\Value\UtcInstant;
use TontonPro\Contracts\Vocabulary\PaymentMeansKind;
use TontonPro\Contracts\Vocabulary\RateRole;

describe('TaxRate', function () {
    it('accepte de 0 à 10000 points de base', function (int $basisPoints) {
        expect((new TaxRate($basisPoints))->basisPoints)->toBe($basisPoints);
    })->with([0, 1925, 10000]);

    it('refuse un taux hors bornes', function (int $basisPoints) {
        expect(violation(fn () => new TaxRate($basisPoints)))->toBe(ViolationCode::TaxRateInvalid);
    })->with([-1, 10001]);
});

describe('Quantity', function () {
    it('accepte une quantité décimale exacte', function (string $value) {
        expect((new Quantity($value))->value)->toBe($value);
    })->with(['1', '0.5', '12.125', '999999999.000001']);

    it('refuse une quantité nulle, négative ou mal écrite', function (string $value) {
        expect(violation(fn () => new Quantity($value)))->toBe(ViolationCode::QuantityInvalid);
    })->with(['0', '0.000', '-1', '1.', '01', '1e3', '1,5', '']);
});

describe('ExchangeRate', function () {
    it('accepte un taux dans son sens, la parité officielle EUR → XAF', function () {
        $rate = Values::rate();

        expect($rate->rate)->toBe('655.957')
            ->and($rate->base->code)->toBe('EUR')
            ->and($rate->quote->code)->toBe('XAF')
            // 655,957 × 100 EUR = 65 595,7 → 65 596 XAF (arrondi au plus proche, calcul entier)
            ->and(intdiv(655957 * 100 + 500, 1000))->toBe(65596);
    });

    it('refuse un taux d\'une devise vers elle-même', function () {
        expect(violation(fn () => Values::rate(RateRole::Proposed, Values::xaf(), Values::xaf())))->toBe(ViolationCode::RateSameCurrency);
    });

    it('refuse un taux nul, négatif ou mal écrit', function (string $value) {
        expect(violation(fn () => new ExchangeRate(Values::eur(), Values::xaf(), $value, RateRole::Proposed, 'BEAC', Values::instant(), RoundingMode::HalfAwayFromZero)))
            ->toBe(ViolationCode::RateInvalid);
    })->with(['0', '0.000', '-1', '655,957', '']);

    it('refuse un taux sans source', function () {
        expect(violation(fn () => new ExchangeRate(Values::eur(), Values::xaf(), '655.957', RateRole::Proposed, ' ', UtcInstant::fromString('2026-10-16T00:00:00Z'), RoundingMode::HalfEven)))
            ->toBe(ViolationCode::RateSourceMissing);
    });
});

describe('RateOverride, Fund, PaymentMeans', function () {
    it('acceptent une dérogation motivée, un fonds et un moyen de paiement', function () {
        expect(new RateOverride(Values::manager(), 'Client fidèle'))->toBeInstanceOf(RateOverride::class)
            ->and(Values::eurFund()->currency->code)->toBe('EUR')
            ->and(Values::cash()->kind->code)->toBe('cash');
    });

    it('refusent une dérogation sans motif', function () {
        expect(violation(fn () => new RateOverride(Values::manager(), '')))->toBe(ViolationCode::RateOverrideReasonMissing);
    });

    it('refusent un fonds mal désigné', function () {
        expect(violation(fn () => new Fund('hotelpro', 'cash point', 'Caisse', Values::xaf())))->toBe(ViolationCode::FundInvalid)
            ->and(violation(fn () => new Fund('hotelpro', 'cash_point:1', ' ', Values::xaf())))->toBe(ViolationCode::FundInvalid)
            ->and(violation(fn () => new Fund('Hotel', 'cash_point:1', 'Caisse', Values::xaf())))->toBe(ViolationCode::SourceSystemInvalid);
    });

    it('refusent un moyen de paiement sans identifiant ou sans libellé', function () {
        expect(violation(fn () => new PaymentMeans(PaymentMeansKind::card(), '', 'Carte')))->toBe(ViolationCode::PaymentMeansInvalid)
            ->and(violation(fn () => new PaymentMeans(PaymentMeansKind::card(), 'payment_method:3', '')))->toBe(ViolationCode::PaymentMeansInvalid);
    });
});
