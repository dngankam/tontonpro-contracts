<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Exceptions;

/**
 * Un invariant du contrat est rompu au moment de la construction.
 *
 * C'est un défaut de l'ÉMETTEUR : le fait (ou l'objet) n'aurait jamais dû être
 * construit. Le code est stable et destiné aux machines ; la phrase est en
 * français et destinée aux personnes ; le champ désigne la valeur fautive
 * (chemin relatif à l'objet : « amount », « lines[2].lineRef »).
 */
final class ContractViolation extends \InvalidArgumentException
{
    public function __construct(
        public readonly ViolationCode $violation,
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
