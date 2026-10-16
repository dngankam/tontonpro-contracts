<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Vocabulary;

/**
 * Sens d'une opération de trésorerie — régime B.
 */
enum TreasuryDirection: string
{
    /** Encaissement. */
    case Collection = 'collection';

    /** Décaissement. */
    case Disbursement = 'disbursement';
}
