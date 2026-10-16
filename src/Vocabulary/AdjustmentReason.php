<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Vocabulary;

use TontonPro\Contracts\Vocabulary\Concerns\ExtensibleVocabulary;

/**
 * Motif d'un ajustement lié — régime A (ouvert, justifié).
 *
 * LISTE PROVISOIRE, à relire par l'expert-comptable. Le régime A permet de la
 * compléter sans nouvelle version (« x.<système>.<code> » justifié).
 */
final readonly class AdjustmentReason
{
    use ExtensibleVocabulary;

    /** Erreur de saisie. */
    public const string ENTRY_ERROR = 'entry_error';

    /** Départ anticipé. */
    public const string EARLY_DEPARTURE = 'early_departure';

    /** Prestation non rendue. */
    public const string SERVICE_NOT_RENDERED = 'service_not_rendered';

    /** Extourne d'une charge. */
    public const string CHARGE_REVERSED = 'charge_reversed';

    public static function knownCodes(): array
    {
        return [self::ENTRY_ERROR, self::EARLY_DEPARTURE, self::SERVICE_NOT_RENDERED, self::CHARGE_REVERSED];
    }

    public static function entryError(): self
    {
        return new self(self::ENTRY_ERROR);
    }

    public static function earlyDeparture(): self
    {
        return new self(self::EARLY_DEPARTURE);
    }

    public static function serviceNotRendered(): self
    {
        return new self(self::SERVICE_NOT_RENDERED);
    }

    public static function chargeReversed(): self
    {
        return new self(self::CHARGE_REVERSED);
    }
}
