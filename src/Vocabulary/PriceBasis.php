<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Vocabulary;

/**
 * Base des montants de ligne — régime B.
 */
enum PriceBasis: string
{
    /** Toutes taxes comprises. */
    case TaxIncluded = 'tax_included';

    /** Hors taxe. */
    case TaxExcluded = 'tax_excluded';
}
