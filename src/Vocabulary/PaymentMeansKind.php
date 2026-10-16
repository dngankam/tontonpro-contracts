<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Vocabulary;

use TontonPro\Contracts\Vocabulary\Concerns\ExtensibleVocabulary;

/**
 * Nature d'un moyen de paiement — régime A (ouvert, justifié).
 *
 * Exclus à dessein : le report en chambre (ce n'est pas un encaissement), la
 * prise en charge et le paiement différé (aucun fonds ne bouge).
 */
final readonly class PaymentMeansKind
{
    use ExtensibleVocabulary;

    /** Espèces. */
    public const string CASH = 'cash';

    /** Carte bancaire. */
    public const string CARD = 'card';

    /** Chèque. */
    public const string CHEQUE = 'cheque';

    /** Monnaie électronique (paiement mobile). */
    public const string ELECTRONIC_MONEY = 'electronic_money';

    public static function knownCodes(): array
    {
        return [self::CASH, self::CARD, self::CHEQUE, self::ELECTRONIC_MONEY];
    }

    public static function cash(): self
    {
        return new self(self::CASH);
    }

    public static function card(): self
    {
        return new self(self::CARD);
    }

    public static function cheque(): self
    {
        return new self(self::CHEQUE);
    }

    public static function electronicMoney(): self
    {
        return new self(self::ELECTRONIC_MONEY);
    }
}
