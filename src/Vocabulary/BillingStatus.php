<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Vocabulary;

/**
 * État de facturation d'une prestation consommée — régime B.
 */
enum BillingStatus: string
{
    /** Consommée, pas encore facturée. */
    case NotInvoiced = 'not_invoiced';

    /** Déjà facturée d'avance. */
    case InvoicedInAdvance = 'invoiced_in_advance';
}
