<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Vocabulary;

/**
 * Sens d'un ajustement lié — régime B.
 */
enum AdjustmentDirection: string
{
    case Decrease = 'decrease';
    case Increase = 'increase';
}
