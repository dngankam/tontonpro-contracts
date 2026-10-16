<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Vocabulary;

/**
 * Rôle d'un taux de change — régime B.
 */
enum RateRole: string
{
    /** Proposé au client. */
    case Proposed = 'proposed';

    /** Retenu par la comptabilité. */
    case Accounting = 'accounting';

    /** Exécuté par la banque, lors d'un change ultérieur. */
    case BankExecuted = 'bank_executed';
}
