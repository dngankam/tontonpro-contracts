<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Exceptions;

/**
 * Une forme sérialisée ne se relit pas : clé absente ou inattendue, type
 * inattendu, type de fait inconnu, version de schéma non prise en charge,
 * JSON invalide, ou invariant rompu dans une donnée stockée.
 *
 * C'est un défaut de DONNÉES STOCKÉES, distinct d'une ContractViolation levée
 * chez l'émetteur. Quand un invariant est en cause, la ContractViolation est
 * la cause (`getPrevious()`).
 */
final class MalformedFact extends \UnexpectedValueException
{
    public function __construct(
        public readonly string $path,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
