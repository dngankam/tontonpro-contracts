<?php

declare(strict_types=1);

use TontonPro\Contracts\Company\SameCompanyAnswer;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Facts\FactHeader;
use TontonPro\Contracts\Facts\RoomChargeAttached;
use TontonPro\Contracts\Facts\SaleCancelled;
use TontonPro\Contracts\Facts\SaleChargedToRoom;
use TontonPro\Contracts\Facts\SaleCompleted;
use TontonPro\Contracts\Tests\Support\Facts;
use TontonPro\Contracts\Tests\Support\Parts;
use TontonPro\Contracts\Tests\Support\Values;
use TontonPro\Contracts\Vocabulary\CancellationReason;

describe('FactHeader', function () {
    it('refuse une révision inférieure à 1', function () {
        expect(violation(fn () => Values::header(version: 0)))->toBe(ViolationCode::FactVersionInvalid)
            ->and(Values::header(version: 2))->toBeInstanceOf(FactHeader::class);
    });
});

describe('SaleCompleted', function () {
    it('construit une vente et sa clé', function () {
        expect(Facts::saleCompleted()->idempotencyKey()->toString())->toBe('restopro|sell:48213|sale.completed|1');
    });

    it('refuse un numéro de vente vide', function () {
        expect(violation(fn () => new SaleCompleted(Values::header(), ' ', null, Parts::restaurantHt())))->toBe(ViolationCode::DocumentNumberMissing);
    });

    it('refuse des lignes hors de la monnaie de l\'établissement', function () {
        expect(violation(fn () => new SaleCompleted(Values::header(establishment: Values::outlet(Values::eur())), '1', null, Parts::restaurantHt())))
            ->toBe(ViolationCode::CurrencyMismatch);
    });
});

describe('SaleCancelled', function () {
    it('porte une clé distincte de la vente, sans référence inventée', function () {
        expect(Facts::saleCancelled()->idempotencyKey()->toString())->toBe('restopro|sell:48213|sale.cancelled|1');
    });

    it('refuse un total nul, une explication vide, un numéro vide, une autre devise', function (Closure $build, ViolationCode $code) {
        expect(violation($build))->toBe($code);
    })->with([
        'total nul' => [fn () => new SaleCancelled(Values::header(), '1', Values::money(0), CancellationReason::notServed(), 'x'), ViolationCode::AmountNotPositive],
        'explication vide' => [fn () => new SaleCancelled(Values::header(), '1', Values::money(10), CancellationReason::notServed(), ' '), ViolationCode::ExplanationMissing],
        'numéro vide' => [fn () => new SaleCancelled(Values::header(), '', Values::money(10), CancellationReason::notServed(), 'x'), ViolationCode::DocumentNumberMissing],
        'autre devise' => [fn () => new SaleCancelled(Values::header(), '1', Values::money(10, Values::eur()), CancellationReason::notServed(), 'x'), ViolationCode::CurrencyMismatch],
    ]);
});

describe('SaleChargedToRoom', function () {
    it('accepte un report dans la même société, ou sans comptabilité installée', function (SameCompanyAnswer $answer) {
        expect(Facts::saleChargedToRoom($answer))->toBeInstanceOf(SaleChargedToRoom::class);
    })->with([SameCompanyAnswer::Same, SameCompanyAnswer::NoAuthority]);

    it('refuse le report entre deux sociétés', function () {
        expect(violation(fn () => Facts::saleChargedToRoom(SameCompanyAnswer::Different)))->toBe(ViolationCode::RoomChargeAcrossCompanies);
    });

    it('refuse le report quand le rattachement est inconnu', function () {
        expect(violation(fn () => Facts::saleChargedToRoom(SameCompanyAnswer::Unresolved)))->toBe(ViolationCode::RoomChargeCompanyUnresolved);
    });

    it('refuse un montant nul, un numéro vide, une note d\'une autre devise', function (Closure $build, ViolationCode $code) {
        expect(violation($build))->toBe($code);
    })->with([
        'montant nul' => [fn () => new SaleChargedToRoom(Values::header(), '1', Values::money(0), Values::source('folio:3', 'hotelpro'), null, Values::hotel(), SameCompanyAnswer::Same), ViolationCode::AmountNotPositive],
        'numéro vide' => [fn () => new SaleChargedToRoom(Values::header(), ' ', Values::money(10), Values::source('folio:3', 'hotelpro'), null, Values::hotel(), SameCompanyAnswer::Same), ViolationCode::DocumentNumberMissing],
        'autre devise' => [fn () => new SaleChargedToRoom(Values::header(), '1', Values::money(10), Values::source('folio:3', 'hotelpro'), null, Values::hotel(Values::eur()), SameCompanyAnswer::Same), ViolationCode::CurrencyMismatch],
    ]);
});

describe('RoomChargeAttached', function () {
    it('accepte des lignes qui désignent la vente d\'origine', function () {
        $fact = Facts::roomChargeAttached();

        expect($fact)->toBeInstanceOf(RoomChargeAttached::class)
            ->and($fact->idempotencyKey()->toString())->toBe('hotelpro|room_charge:5|room_charge.attached|1');
    });

    it('refuse une ligne qui ne désigne pas la vente d\'origine', function () {
        expect(violation(fn () => Facts::roomChargeAttached(charges: Parts::ttc())))->toBe(ViolationCode::RoomChargeLineNotLinked)
            ->and(violation(fn () => Facts::roomChargeAttached(charges: Parts::ttc(recognizedBy: Values::ref('sale.completed', 'sell:1', 'restopro')))))
            ->toBe(ViolationCode::RoomChargeLineNotLinked);
    });

    it('refuse le report entre sociétés et sans rattachement', function () {
        expect(violation(fn () => Facts::roomChargeAttached(SameCompanyAnswer::Different)))->toBe(ViolationCode::RoomChargeAcrossCompanies)
            ->and(violation(fn () => Facts::roomChargeAttached(SameCompanyAnswer::Unresolved)))->toBe(ViolationCode::RoomChargeCompanyUnresolved);
    });

    it('refuse une origine qui n\'est pas une vente réalisée', function () {
        expect(violation(fn () => new RoomChargeAttached(
            Values::hotelHeader('room_charge:5'), Values::source('folio:3', 'hotelpro'), Values::party(),
            Values::ref('invoice.issued', 'invoice:1'), Values::outlet(), Parts::ttc(), SameCompanyAnswer::Same,
        )))->toBe(ViolationCode::FactReferenceTypeInvalid);
    });

    it('refuse un point de vente d\'une autre devise', function () {
        expect(violation(fn () => new RoomChargeAttached(
            Values::hotelHeader('room_charge:5'), Values::source('folio:3', 'hotelpro'), Values::party(),
            Facts::originSale(), Values::outlet(Values::eur()), Parts::ttc(recognizedBy: Facts::originSale()), SameCompanyAnswer::Same,
        )))->toBe(ViolationCode::CurrencyMismatch);
    });
});
