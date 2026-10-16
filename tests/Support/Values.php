<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Tests\Support;

use TontonPro\Contracts\Facts\FactHeader;
use TontonPro\Contracts\Value\Actor;
use TontonPro\Contracts\Value\BusinessDate;
use TontonPro\Contracts\Value\Currency;
use TontonPro\Contracts\Value\Establishment;
use TontonPro\Contracts\Value\ExchangeRate;
use TontonPro\Contracts\Value\FactReference;
use TontonPro\Contracts\Value\Fund;
use TontonPro\Contracts\Value\Money;
use TontonPro\Contracts\Value\Party;
use TontonPro\Contracts\Value\PaymentMeans;
use TontonPro\Contracts\Value\SourceReference;
use TontonPro\Contracts\Value\UtcInstant;
use TontonPro\Contracts\Vocabulary\PartyKind;
use TontonPro\Contracts\Vocabulary\PaymentMeansKind;
use TontonPro\Contracts\Vocabulary\RateRole;

/**
 * Fabriques d'objets de valeur valides, réutilisées par tous les témoins.
 */
final class Values
{
    public static function xaf(): Currency
    {
        return new Currency('XAF', 0);
    }

    public static function eur(): Currency
    {
        return new Currency('EUR', 2);
    }

    public static function money(int $amount, ?Currency $currency = null): Money
    {
        return new Money($amount, $currency ?? self::xaf());
    }

    public static function source(string $reference = 'sell:48213', string $system = 'restopro'): SourceReference
    {
        return new SourceReference($system, $reference);
    }

    public static function outlet(?Currency $currency = null): Establishment
    {
        return new Establishment('restopro', 'outlet:1', 'Restaurant', $currency ?? self::xaf());
    }

    public static function hotel(?Currency $currency = null): Establishment
    {
        return new Establishment('hotelpro', 'site:1', 'Hôtel', $currency ?? self::xaf());
    }

    public static function actor(): Actor
    {
        return new Actor('restopro', '12', 'Caissier');
    }

    public static function manager(): Actor
    {
        return new Actor('hotelpro', '3', 'Direction');
    }

    public static function party(): Party
    {
        return new Party(PartyKind::Person, new SourceReference('hotelpro', 'guest:7'), 'Client');
    }

    public static function instant(): UtcInstant
    {
        return UtcInstant::fromString('2026-10-16T09:30:00.000000Z');
    }

    public static function date(string $value = '2026-10-16'): BusinessDate
    {
        return new BusinessDate($value);
    }

    public static function receptionist(): Actor
    {
        return new Actor('hotelpro', '8', 'Réception');
    }

    public static function header(
        ?SourceReference $source = null,
        ?Establishment $establishment = null,
        string $date = '2026-10-16',
        int $version = 1,
        ?Actor $actor = null,
    ): FactHeader {
        return new FactHeader(
            $version,
            $source ?? self::source(),
            $establishment ?? self::outlet(),
            self::instant(),
            self::date($date),
            $actor ?? self::actor(),
        );
    }

    public static function hotelHeader(string $reference, string $date = '2026-10-16'): FactHeader
    {
        return self::header(self::source($reference, 'hotelpro'), self::hotel(), $date, 1, self::receptionist());
    }

    public static function ref(string $factType, string $reference, string $system = 'hotelpro'): FactReference
    {
        return new FactReference($factType, new SourceReference($system, $reference), 1);
    }

    public static function rate(RateRole $role = RateRole::Proposed, ?Currency $base = null, ?Currency $quote = null): ExchangeRate
    {
        return new ExchangeRate(
            $base ?? self::eur(),
            $quote ?? self::xaf(),
            '655.957',
            $role,
            'parité officielle',
            UtcInstant::fromString('2026-10-16T00:00:00Z'),
            \RoundingMode::HalfAwayFromZero,
        );
    }

    public static function xafFund(): Fund
    {
        return new Fund('hotelpro', 'cash_point:1', 'Caisse XAF', self::xaf());
    }

    public static function eurFund(): Fund
    {
        return new Fund('hotelpro', 'cash_point:2', 'Caisse EUR', self::eur());
    }

    public static function cash(): PaymentMeans
    {
        return new PaymentMeans(PaymentMeansKind::cash(), 'payment_method:1', 'Espèces');
    }
}
