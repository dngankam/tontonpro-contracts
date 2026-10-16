<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Value;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;

/**
 * Un objet métier désigné dans son système source : (« restopro », « sell:48213 »).
 * La référence est unique dans ce système. Le « | » est interdit : il sépare
 * les parties de la clé d'idempotence.
 */
final readonly class SourceReference
{
    /** Système source : minuscules, chiffres, « _ » et « - ». */
    public const string SYSTEM_PATTERN = '/^[a-z][a-z0-9_-]{1,31}$/';

    /** Référence dans le système source. */
    public const string REFERENCE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9:._\/-]{0,119}$/';

    /** Identifiant local : établissement, ligne, mouvement, affectation. */
    public const string LOCAL_ID_PATTERN = '/^[A-Za-z0-9:._-]{1,64}$/';

    public function __construct(
        public string $system,
        public string $reference,
    ) {
        self::requireSystem($system, 'system');

        if (preg_match(self::REFERENCE_PATTERN, $reference) !== 1) {
            throw new ContractViolation(
                ViolationCode::SourceReferenceInvalid,
                'reference',
                sprintf('La référence source « %s » est invalide (1 à 120 caractères, sans « | »).', $reference),
            );
        }
    }

    /**
     * Contrôle partagé par tout ce qui nomme un système (établissement, auteur, fonds).
     */
    public static function requireSystem(string $system, string $field): void
    {
        if (preg_match(self::SYSTEM_PATTERN, $system) !== 1) {
            throw new ContractViolation(
                ViolationCode::SourceSystemInvalid,
                $field,
                sprintf('Le système « %s » est invalide (minuscules, 2 à 32 caractères).', $system),
            );
        }
    }

    public function equals(self $other): bool
    {
        return $this->system === $other->system && $this->reference === $other->reference;
    }

    /**
     * @return array{system: string, reference: string}
     */
    public function toArray(): array
    {
        return ['system' => $this->system, 'reference' => $this->reference];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('system', 'reference');

        return new self($reader->string('system'), $reader->string('reference'));
    }
}
