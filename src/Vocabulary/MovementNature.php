<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Vocabulary;

/**
 * Nature d'un mouvement physique de fonds — régime B.
 */
enum MovementNature: string
{
    /** Entrée de fonds. */
    case Received = 'received';

    /** Monnaie rendue (sortie). */
    case ChangeReturned = 'change_returned';

    /** Sortie : remboursement, restitution. */
    case PaidOut = 'paid_out';
}
