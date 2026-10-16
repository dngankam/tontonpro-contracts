<?php

declare(strict_types=1);

use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Tests\Support\Values;
use TontonPro\Contracts\Value\Actor;
use TontonPro\Contracts\Value\Establishment;
use TontonPro\Contracts\Value\FactReference;
use TontonPro\Contracts\Value\IdempotencyKey;
use TontonPro\Contracts\Value\Party;
use TontonPro\Contracts\Value\SourceReference;

describe('SourceReference', function () {
    it('accepte un système et une référence', function () {
        expect(new SourceReference('restopro', 'sell:48213'))->toBeInstanceOf(SourceReference::class)
            ->and(new SourceReference('migration', 'legacy/2019-04.A_1'))->toBeInstanceOf(SourceReference::class);
    });

    it('refuse un système invalide', function (string $system) {
        expect(violation(fn () => new SourceReference($system, 'sell:1')))->toBe(ViolationCode::SourceSystemInvalid);
    })->with(['RestoPro', 'r', '1resto', 'resto pro']);

    it('refuse une référence invalide, dont le séparateur de clé', function (string $reference) {
        expect(violation(fn () => new SourceReference('restopro', $reference)))->toBe(ViolationCode::SourceReferenceInvalid);
    })->with(['sell|1', '', ':sell', 'sell 1']);
});

describe('IdempotencyKey', function () {
    it('dérive la clé texte (système, référence, type, révision)', function () {
        expect((new IdempotencyKey(Values::source(), 'sale.completed', 1))->toString())->toBe('restopro|sell:48213|sale.completed|1')
            ->and((new IdempotencyKey(Values::source(), 'sale.cancelled', 1))->toString())->toBe('restopro|sell:48213|sale.cancelled|1');
    });

    it('refuse une révision inférieure à 1', function () {
        expect(violation(fn () => new IdempotencyKey(Values::source(), 'sale.completed', 0)))->toBe(ViolationCode::FactVersionInvalid);
    });

    it('refuse un type de fait mal formé', function (string $type) {
        expect(violation(fn () => new IdempotencyKey(Values::source(), $type, 1)))->toBe(ViolationCode::FactTypeInvalid);
    })->with(['SaleCompleted', 'sale', 'sale.completed.now', 'sale-completed.x']);
});

describe('Establishment', function () {
    it('accepte un établissement figé', function () {
        expect(Values::hotel()->currency->code)->toBe('XAF')
            ->and(new Establishment('hotelpro', 'site:1', str_repeat('é', 191), Values::xaf()))->toBeInstanceOf(Establishment::class);
    });

    it('refuse un identifiant ou un nom invalide', function (string $id, string $name) {
        expect(violation(fn () => new Establishment('hotelpro', $id, $name, Values::xaf())))->toBe(ViolationCode::EstablishmentInvalid);
    })->with([
        'identifiant avec espace' => ['site 1', 'Hôtel'],
        'nom vide' => ['site:1', '   '],
        'nom trop long' => ['site:1', str_repeat('é', 192)],
    ]);

    it('refuse un système invalide', function () {
        expect(violation(fn () => new Establishment('Hotel', 'site:1', 'Hôtel', Values::xaf())))->toBe(ViolationCode::SourceSystemInvalid);
    });
});

describe('Actor et Party', function () {
    it('acceptent un auteur et un débiteur, nature inconnue comprise', function () {
        expect(new Actor('hotelpro', '3'))->toBeInstanceOf(Actor::class)
            ->and((new Party(null, Values::source('customer:4'), 'Client'))->kind)->toBeNull();
    });

    it('refusent un auteur sans identifiant', function () {
        expect(violation(fn () => new Actor('hotelpro', ' ')))->toBe(ViolationCode::ActorInvalid)
            ->and(violation(fn () => new Actor('Hotel', '3')))->toBe(ViolationCode::SourceSystemInvalid);
    });

    it('refusent un débiteur sans nom', function () {
        expect(violation(fn () => new Party(null, Values::source('customer:4'), '')))->toBe(ViolationCode::PartyInvalid);
    });
});

describe('FactReference', function () {
    it('désigne un fait par sa clé', function () {
        $reference = Values::ref('invoice.issued', 'invoice:88');

        expect($reference->idempotencyKey()->toString())->toBe('hotelpro|invoice:88|invoice.issued|1')
            ->and($reference->equals(Values::ref('invoice.issued', 'invoice:88')))->toBeTrue()
            ->and($reference->equals(Values::ref('invoice.issued', 'invoice:89')))->toBeFalse();
    });

    it('refuse un type ou une révision invalides', function () {
        expect(violation(fn () => new FactReference('Invoice', Values::source(), 1)))->toBe(ViolationCode::FactTypeInvalid)
            ->and(violation(fn () => new FactReference('invoice.issued', Values::source(), 0)))->toBe(ViolationCode::FactVersionInvalid);
    });

    it('refuse un fait désigné d\'un autre type que celui attendu', function () {
        expect(violation(fn () => Values::ref('invoice.issued', 'invoice:88')->requireType('sale.completed', 'x')))
            ->toBe(ViolationCode::FactReferenceTypeInvalid)
            ->and(violation(fn () => Values::ref('sale.completed', 'sell:1')->requireType('sale.completed', 'x')))->toBeNull();
    });
});
