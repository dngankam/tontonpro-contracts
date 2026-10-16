<?php

declare(strict_types=1);

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\MalformedFact;
use TontonPro\Contracts\Exceptions\ViolationCode;

/**
 * Le code de l'invariant rompu par `$build`, ou `null` s'il n'en rompt aucun.
 * Un témoin vérifie le CODE attendu, pas seulement la classe d'exception.
 */
function violation(callable $build): ?ViolationCode
{
    try {
        $build();
    } catch (ContractViolation $violation) {
        return $violation->violation;
    }

    return null;
}

/**
 * Le chemin fautif d'une relecture refusée, ou `null` si elle passe.
 */
function malformedPath(callable $read): ?string
{
    try {
        $read();
    } catch (MalformedFact $malformed) {
        return $malformed->path;
    }

    return null;
}
