<?php

declare(strict_types=1);

use TontonPro\Contracts\ContractsVersion;
use TontonPro\Contracts\Serialization\FactSerializer;
use TontonPro\Contracts\Tests\Support\Facts;

it('suit le versionnage sémantique', function () {
    expect(ContractsVersion::VERSION)->toBe('0.1.1')
        ->and(ContractsVersion::VERSION)->toMatch('/^\d+\.\d+\.\d+$/');
});

it('écrit la version de schéma qu\'il annonce', function () {
    expect(FactSerializer::toArray(Facts::saleCompleted())['schemaVersion'])->toBe(ContractsVersion::FACT_SCHEMA)
        ->and(ContractsVersion::FACT_SCHEMA)->toBe(1);
});
