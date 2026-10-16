<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Vocabulary;

use TontonPro\Contracts\Vocabulary\Concerns\ExtensibleVocabulary;

/**
 * Motif d'annulation d'une vente — régime A (ouvert, justifié).
 *
 * LISTE PROVISOIRE, à relire par l'expert-comptable. Le régime A permet de la
 * compléter sans nouvelle version (« x.<système>.<code> » justifié).
 */
final readonly class CancellationReason
{
    use ExtensibleVocabulary;

    /** Erreur de saisie. */
    public const string ENTRY_ERROR = 'entry_error';

    /** Vente enregistrée deux fois. */
    public const string DUPLICATE_SALE = 'duplicate_sale';

    /** Commande non servie. */
    public const string NOT_SERVED = 'not_served';

    /** Contestation du client. */
    public const string CUSTOMER_DISPUTE = 'customer_dispute';

    public static function knownCodes(): array
    {
        return [self::ENTRY_ERROR, self::DUPLICATE_SALE, self::NOT_SERVED, self::CUSTOMER_DISPUTE];
    }

    public static function entryError(): self
    {
        return new self(self::ENTRY_ERROR);
    }

    public static function duplicateSale(): self
    {
        return new self(self::DUPLICATE_SALE);
    }

    public static function notServed(): self
    {
        return new self(self::NOT_SERVED);
    }

    public static function customerDispute(): self
    {
        return new self(self::CUSTOMER_DISPUTE);
    }
}
