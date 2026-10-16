<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Vocabulary;

use TontonPro\Contracts\Vocabulary\Concerns\ExtensibleVocabulary;

/**
 * Motif d'une gratuité — régime A (ouvert, justifié).
 *
 * LISTE PROVISOIRE, à relire par l'expert-comptable. Le régime A permet de la
 * compléter sans nouvelle version (« x.<système>.<code> » justifié).
 */
final readonly class ComplimentaryReason
{
    use ExtensibleVocabulary;

    /** Geste commercial. */
    public const string COMMERCIAL_GESTURE = 'commercial_gesture';

    /** Dédommagement d'un incident. */
    public const string SERVICE_RECOVERY = 'service_recovery';

    /** Invitation promotionnelle. */
    public const string PROMOTIONAL_INVITATION = 'promotional_invitation';

    /** Avantage au personnel. */
    public const string STAFF_BENEFIT = 'staff_benefit';

    public static function knownCodes(): array
    {
        return [self::COMMERCIAL_GESTURE, self::SERVICE_RECOVERY, self::PROMOTIONAL_INVITATION, self::STAFF_BENEFIT];
    }

    public static function commercialGesture(): self
    {
        return new self(self::COMMERCIAL_GESTURE);
    }

    public static function serviceRecovery(): self
    {
        return new self(self::SERVICE_RECOVERY);
    }

    public static function promotionalInvitation(): self
    {
        return new self(self::PROMOTIONAL_INVITATION);
    }

    public static function staffBenefit(): self
    {
        return new self(self::STAFF_BENEFIT);
    }
}
