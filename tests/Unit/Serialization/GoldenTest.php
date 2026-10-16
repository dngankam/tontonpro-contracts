<?php

declare(strict_types=1);

use TontonPro\Contracts\Facts\PaymentRecorded;
use TontonPro\Contracts\Serialization\FactSerializer;
use TontonPro\Contracts\Tests\Support\Facts;

/**
 * Le fichier de référence : l'encaissement de l'expert (100 EUR à la parité
 * officielle 655,957 = 65 596 XAF ; 4 596 rendus ; 60 000 réglés ; 1 000 de
 * pourboire). Une réorganisation silencieuse de la forme fait rougir ce témoin.
 * Toute modification voulue de la forme incrémente FACT_SCHEMA.
 */
function golden(): string
{
    return rtrim((string) file_get_contents(__DIR__.'/../../Fixtures/payment-recorded.v1.json'), "\r\n");
}

it('produit exactement le JSON de référence', function () {
    expect(FactSerializer::toJson(Facts::paymentRecorded()))->toBe(golden());
});

it('relit le JSON de référence et le réécrit à l\'identique', function () {
    $fact = FactSerializer::fromJson(golden());

    expect($fact)->toBeInstanceOf(PaymentRecorded::class)
        ->and(FactSerializer::toJson($fact))->toBe(golden());
});

it('garde l\'empreinte de référence', function () {
    expect(FactSerializer::fingerprint(Facts::paymentRecorded()))
        ->toBe('21761572f0d076d73ae3c687686a1d2cc5af0a6dbe2d5c1d1ca6151b0655c268')
        ->and(hash('sha256', golden()))->toBe('21761572f0d076d73ae3c687686a1d2cc5af0a6dbe2d5c1d1ca6151b0655c268');
});
