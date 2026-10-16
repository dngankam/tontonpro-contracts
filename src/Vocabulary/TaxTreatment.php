<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Vocabulary;

use TontonPro\Contracts\Value\TaxRate;

/**
 * Traitement fiscal d'une ligne — régime B (fermé, étendu par version mineure).
 *
 * `taxable` porte un taux > 0 ; `zero_rated` un taux nul ; les autres aucun
 * taux. `not_configured` est le « je ne sais pas » : ni base ni taxe connues.
 */
enum TaxTreatment: string
{
    case Taxable = 'taxable';
    case ZeroRated = 'zero_rated';
    case Exempt = 'exempt';
    case OutOfScope = 'out_of_scope';
    case Franchise = 'franchise';
    case NotConfigured = 'not_configured';

    /**
     * La règle taux / traitement, commune aux lignes et à la ventilation.
     */
    public function acceptsRate(?TaxRate $rate): bool
    {
        return match ($this) {
            self::Taxable => $rate !== null && ! $rate->isZero(),
            self::ZeroRated => $rate !== null && $rate->isZero(),
            default => $rate === null,
        };
    }
}
