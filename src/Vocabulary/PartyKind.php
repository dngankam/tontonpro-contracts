<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Vocabulary;

/**
 * Nature d'un débiteur — régime B.
 */
enum PartyKind: string
{
    case Person = 'person';
    case Organization = 'organization';
}
