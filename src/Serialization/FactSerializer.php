<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Serialization;

use TontonPro\Contracts\ContractsVersion;
use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\MalformedFact;
use TontonPro\Contracts\Facts\FactHeader;
use TontonPro\Contracts\Facts\FactTypes;
use TontonPro\Contracts\Facts\MoneyFact;

/**
 * La forme sérialisée d'un fait, stable : ordre des clés fixé par le code,
 * toutes les clés toujours présentes, aucun flottant, chaque montant avec sa
 * devise et ses décimales.
 *
 * La relecture reconstruit par les constructeurs : tous les invariants sont
 * revérifiés. L'empreinte (SHA-256 du JSON) distingue un rejeu (même clé,
 * même empreinte) d'un conflit (même clé, empreinte différente).
 */
final class FactSerializer
{
    public const string SCHEMA = 'tontonpro.money-fact';

    private const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * @return array<string, mixed>
     */
    public static function toArray(MoneyFact $fact): array
    {
        return [
            'schema' => self::SCHEMA,
            'schemaVersion' => ContractsVersion::FACT_SCHEMA,
            'type' => $fact::type(),
            'idempotencyKey' => $fact->idempotencyKey()->toString(),
            'header' => $fact->header()->toArray(),
            'payload' => $fact->payload(),
        ];
    }

    public static function toJson(MoneyFact $fact): string
    {
        return json_encode(self::toArray($fact), self::JSON_FLAGS);
    }

    public static function fingerprint(MoneyFact $fact): string
    {
        return hash('sha256', self::toJson($fact));
    }

    public static function fromJson(string $json): MoneyFact
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new MalformedFact('', 'Le JSON du fait est invalide : '.$exception->getMessage(), $exception);
        }

        return self::fromArray($data);
    }

    public static function fromArray(mixed $data): MoneyFact
    {
        $root = PayloadReader::of($data, '')->expect('schema', 'schemaVersion', 'type', 'idempotencyKey', 'header', 'payload');

        if ($root->string('schema') !== self::SCHEMA) {
            throw new MalformedFact('schema', sprintf('« schema » : « %s » attendu.', self::SCHEMA));
        }

        $version = $root->int('schemaVersion');

        if ($version < 1 || $version > ContractsVersion::FACT_SCHEMA) {
            throw new MalformedFact('schemaVersion', sprintf('« schemaVersion » : la version %d n\'est pas prise en charge.', $version));
        }

        $type = $root->string('type');
        $class = FactTypes::classFor($type);

        if ($class === null) {
            throw new MalformedFact('type', sprintf('« type » : le type de fait « %s » est inconnu.', $type));
        }

        try {
            $fact = $class::fromPayload(FactHeader::fromReader($root->object('header')), $root->rawObject('payload'));
        } catch (ContractViolation $violation) {
            throw new MalformedFact(
                $violation->field,
                sprintf('Le fait stocké rompt un invariant (%s, « %s ») : %s', $violation->violation->value, $violation->field, $violation->getMessage()),
                $violation,
            );
        }

        if ($fact->idempotencyKey()->toString() !== $root->string('idempotencyKey')) {
            throw new MalformedFact('idempotencyKey', '« idempotencyKey » : la clé stockée ne correspond pas au fait.');
        }

        return $fact;
    }
}
