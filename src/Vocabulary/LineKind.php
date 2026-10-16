<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Vocabulary;

/**
 * Sens d'une ligne — régime B. Le montant reste positif ; c'est le type qui
 * dit s'il s'ajoute ou se retranche.
 */
enum LineKind: string
{
    /** Prestation ou produit. */
    case Charge = 'charge';

    /** Remise sur ligne. */
    case Deduction = 'deduction';
}
