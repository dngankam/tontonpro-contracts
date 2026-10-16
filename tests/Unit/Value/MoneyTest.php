<?php

declare(strict_types=1);

use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Tests\Support\Values;
use TontonPro\Contracts\Value\Currency;
use TontonPro\Contracts\Value\Money;

describe('Currency', function () {
    it('accepte un code ISO et ses décimales', function () {
        $eur = new Currency('EUR', 2);

        expect($eur->code)->toBe('EUR')
            ->and($eur->decimals)->toBe(2)
            ->and(new Currency('KWD', 3))->toBeInstanceOf(Currency::class)
            ->and(new Currency('XAF', 0))->toBeInstanceOf(Currency::class)
            ->and(new Currency('CLF', 4))->toBeInstanceOf(Currency::class);
    });

    it('refuse un code qui n\'est pas ISO 4217', function (string $code) {
        expect(violation(fn () => new Currency($code, 0)))->toBe(ViolationCode::CurrencyCodeInvalid);
    })->with(['FCFA', 'xaf', 'XA', '']);

    it('refuse des décimales hors de 0 à 4', function (int $decimals) {
        expect(violation(fn () => new Currency('XAF', $decimals)))->toBe(ViolationCode::CurrencyDecimalsInvalid);
    })->with([-1, 5]);

    it('compare le code ET les décimales', function () {
        expect((new Currency('XAF', 0))->equals(new Currency('XAF', 0)))->toBeTrue()
            ->and((new Currency('XAF', 0))->equals(new Currency('XAF', 2)))->toBeFalse();
    });
});

describe('Money', function () {
    it('accepte zéro et un montant positif', function () {
        expect(Values::money(0)->isZero())->toBeTrue()
            ->and(Values::money(65596)->amount)->toBe(65596);
    });

    it('refuse un montant négatif', function () {
        expect(violation(fn () => new Money(-1, Values::xaf())))->toBe(ViolationCode::AmountNegative);
    });

    it('additionne et soustrait dans une même devise', function () {
        expect(Values::money(60000)->plus(Values::money(1000))->amount)->toBe(61000)
            ->and(Values::money(65596)->minus(Values::money(4596))->amount)->toBe(61000)
            ->and(Money::sum([Values::money(1), Values::money(2)], Values::xaf())->amount)->toBe(3)
            ->and(Money::sum([], Values::xaf())->isZero())->toBeTrue();
    });

    it('refuse d\'additionner deux devises', function () {
        expect(violation(fn () => Values::money(1)->plus(Values::money(1, Values::eur()))))->toBe(ViolationCode::CurrencyMismatch);
    });

    it('refuse une soustraction qui deviendrait négative', function () {
        expect(violation(fn () => Values::money(1)->minus(Values::money(2))))->toBe(ViolationCode::AmountNegative);
    });

    it('refuse un dépassement d\'entier au lieu de passer en flottant', function () {
        expect(violation(fn () => Values::money(PHP_INT_MAX)->plus(Values::money(1))))->toBe(ViolationCode::AmountOverflow);
    });

    it('compare montant et devise', function () {
        expect(Values::money(1)->equals(Values::money(1)))->toBeTrue()
            ->and(Values::money(1)->equals(new Money(1, new Currency('XAF', 2))))->toBeFalse();
    });
});
