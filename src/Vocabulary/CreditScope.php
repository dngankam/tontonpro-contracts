<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Vocabulary;

/**
 * Portée d'un avoir — régime B.
 */
enum CreditScope: string
{
    case Partial = 'partial';
    case Total = 'total';
}
