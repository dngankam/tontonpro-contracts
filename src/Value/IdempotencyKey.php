<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Value;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;

/**
 * La clé unique d'un fait : (système, référence, type, révision). Dérivée,
 * jamais saisie. La révision est celle du FAIT, non celle du schéma : une
 * montée de schéma ne change pas la clé d'un fait déjà reçu.
 *
 * Forme texte : « restopro|sell:48213|sale.completed|1 ».
 */
final readonly class IdempotencyKey
{
    public const string FACT_TYPE_PATTERN = '/^[a-z]+(_[a-z]+)*\.[a-z]+(_[a-z]+)*$/';

    public function __construct(
        public SourceReference $source,
        public string $factType,
        public int $factVersion,
    ) {
        self::requireFactType($factType, 'factType');
        self::requireFactVersion($factVersion, 'factVersion');
    }

    public static function requireFactType(string $factType, string $field): void
    {
        if (preg_match(self::FACT_TYPE_PATTERN, $factType) !== 1) {
            throw new ContractViolation(
                ViolationCode::FactTypeInvalid,
                $field,
                sprintf('Le type de fait « %s » est invalide (forme « objet.verbe »).', $factType),
            );
        }
    }

    public static function requireFactVersion(int $factVersion, string $field): void
    {
        if ($factVersion < 1) {
            throw new ContractViolation(
                ViolationCode::FactVersionInvalid,
                $field,
                sprintf('La révision d\'un fait commence à 1 (%d reçu).', $factVersion),
            );
        }
    }

    public function toString(): string
    {
        return implode('|', [$this->source->system, $this->source->reference, $this->factType, (string) $this->factVersion]);
    }
}
