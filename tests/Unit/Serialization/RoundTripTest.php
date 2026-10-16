<?php

declare(strict_types=1);

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\MalformedFact;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Facts\FactTypes;
use TontonPro\Contracts\Facts\MoneyFact;
use TontonPro\Contracts\Facts\SaleCancelled;
use TontonPro\Contracts\Serialization\FactSerializer;
use TontonPro\Contracts\Tests\Support\Facts;
use TontonPro\Contracts\Tests\Support\Parts;
use TontonPro\Contracts\Tests\Support\Values;
use TontonPro\Contracts\Vocabulary\CancellationReason;

dataset('faits', function () {
    return Facts::all() + [
        'payment.recorded (taux comptable, dérogation)' => Facts::paymentWithAccountingRate(),
        'sale.completed (repas offert)' => Facts::saleCompleted(Parts::freeSet()),
        'sale.completed (remise de 100 % qualifiée)' => Facts::saleCompleted(Parts::compensated(Parts::gift())),
        'sale.completed (TTC, débiteur)' => new TontonPro\Contracts\Facts\SaleCompleted(Values::header(), '2026-000124', Values::party(), Parts::ttc()),
        'sale.cancelled (motif étendu)' => new SaleCancelled(
            Values::header(), '2026-000123', Values::money(14310),
            new CancellationReason('x.restopro.kitchen_closed', 'Cuisine fermée avant le service'), 'Commande non préparée',
        ),
    ];
});

function hasFloat(mixed $value): bool
{
    if (is_float($value)) {
        return true;
    }

    if (is_array($value)) {
        foreach ($value as $item) {
            if (hasFloat($item)) {
                return true;
            }
        }
    }

    return false;
}

describe('aller-retour', function () {
    it('couvre exactement les 13 types de faits', function () {
        $types = array_keys(FactTypes::all());
        sort($types);
        $built = array_keys(Facts::all());
        sort($built);

        expect($types)->toHaveCount(13)->and($built)->toBe($types);
    });

    it('relit toArray → fromArray à l\'identique', function (MoneyFact $fact) {
        $read = FactSerializer::fromArray(FactSerializer::toArray($fact));

        expect($read)->toBeInstanceOf($fact::class)
            ->and(FactSerializer::toJson($read))->toBe(FactSerializer::toJson($fact))
            ->and($read->idempotencyKey()->toString())->toBe($fact->idempotencyKey()->toString());
    })->with('faits');

    it('relit toJson → fromJson à l\'identique, empreinte comprise', function (MoneyFact $fact) {
        $json = FactSerializer::toJson($fact);
        $read = FactSerializer::fromJson($json);

        expect(FactSerializer::toJson($read))->toBe($json)
            ->and(FactSerializer::fingerprint($read))->toBe(FactSerializer::fingerprint($fact))
            ->and(FactSerializer::fingerprint($fact))->toMatch('/^[0-9a-f]{64}$/');
    })->with('faits');

    it('fixe l\'ordre des clés et ne porte aucun flottant', function (MoneyFact $fact) {
        $array = FactSerializer::toArray($fact);

        expect(array_keys($array))->toBe(['schema', 'schemaVersion', 'type', 'idempotencyKey', 'header', 'payload'])
            ->and(hasFloat($array))->toBeFalse();
    })->with('faits');

    it('distingue deux faits différents par l\'empreinte', function () {
        expect(FactSerializer::fingerprint(Facts::paymentRecorded()))
            ->not->toBe(FactSerializer::fingerprint(Facts::paymentRecorded(Parts::expertWithoutTip())));
    });
});

describe('relecture refusée', function () {
    $json = fn (): string => FactSerializer::toJson(Facts::paymentRecorded());

    it('refuse un JSON invalide ou qui n\'est pas un objet', function (string $stored) {
        expect(malformedPath(fn () => FactSerializer::fromJson($stored)))->toBe('');
    })->with(['{"schema":', '[1,2]', '"texte"']);

    it('refuse une forme mal construite, en nommant le chemin', function (string $search, string $replace, string $path) use ($json) {
        $stored = $json();

        expect(str_contains($stored, $search))->toBeTrue()
            ->and(malformedPath(fn () => FactSerializer::fromJson(str_replace($search, $replace, $stored))))->toBe($path);
    })->with([
        'schéma étranger' => ['"tontonpro.money-fact"', '"autre"', 'schema'],
        'version de schéma future' => ['"schemaVersion":1', '"schemaVersion":2', 'schemaVersion'],
        'type inconnu' => ['"type":"payment.recorded"', '"type":"payment.forgotten"', 'type'],
        'clé absente' => [',"cashSession":"cash_session:5"', '', 'payload.cashSession'],
        'clé inattendue' => ['"header":{', '"header":{"extra":true,', 'header.extra'],
        'clé d\'idempotence falsifiée' => ['payment:771|payment.recorded|1', 'payment:772|payment.recorded|1', 'idempotencyKey'],
        'montant en chaîne' => ['"amount":60000', '"amount":"60000"', 'payload.operation.allocations[0].amount.amount'],
        'montant flottant' => ['"amount":60000', '"amount":60000.5', 'payload.operation.allocations[0].amount.amount'],
        'énumération inconnue' => ['"nature":"tip_payable"', '"nature":"gift"', 'payload.operation.allocations[1].nature'],
        'règle d\'arrondi inconnue' => ['"HalfAwayFromZero"', '"Banker"', 'payload.operation.movements[0].rates[0].rounding'],
    ]);

    it('refuse un fait stocké qui rompt un invariant, avec sa cause', function () use ($json) {
        $tampered = str_replace('"amount":60000', '"amount":59000', $json());

        try {
            FactSerializer::fromJson($tampered);
            $cause = null;
        } catch (MalformedFact $malformed) {
            $cause = $malformed->getPrevious();
        }

        expect($cause)->toBeInstanceOf(ContractViolation::class)
            ->and($cause instanceof ContractViolation ? $cause->violation : null)->toBe(ViolationCode::TreasuryUnbalanced);
    });

    it('refuse un instant stocké hors UTC', function () use ($json) {
        expect(malformedPath(fn () => FactSerializer::fromJson(str_replace('"occurredAt":"2026-10-16T09:30:00.000000Z"', '"occurredAt":"2026-10-16T10:30:00+01:00"', $json()))))
            ->toBe('value');
    });
});
