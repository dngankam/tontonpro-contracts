<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Vocabulary;

use TontonPro\Contracts\Vocabulary\Concerns\ExtensibleVocabulary;

/**
 * Motif d'un avoir — régime A (ouvert, justifié). Les sept motifs de la
 * décision d'architecture sur les faits d'argent.
 */
final readonly class CreditNoteReason
{
    use ExtensibleVocabulary;

    /** Réduction commerciale. */
    public const string COMMERCIAL_DISCOUNT = 'commercial_discount';

    /** Erreur de prix, de quantité ou de calcul. */
    public const string PRICING_ERROR = 'pricing_error';

    /** Erreur de traitement fiscal. */
    public const string TAX_TREATMENT_ERROR = 'tax_treatment_error';

    /** Prestation annulée, non réalisée ou partiellement réalisée. */
    public const string SERVICE_CANCELLED = 'service_cancelled';

    /** Retour de produit. */
    public const string GOODS_RETURNED = 'goods_returned';

    /** Erreur de débiteur, avec facture de remplacement. */
    public const string WRONG_DEBTOR_REPLACED = 'wrong_debtor_replaced';

    /** Double facturation. */
    public const string DUPLICATE_INVOICE = 'duplicate_invoice';

    public static function knownCodes(): array
    {
        return [
            self::COMMERCIAL_DISCOUNT,
            self::PRICING_ERROR,
            self::TAX_TREATMENT_ERROR,
            self::SERVICE_CANCELLED,
            self::GOODS_RETURNED,
            self::WRONG_DEBTOR_REPLACED,
            self::DUPLICATE_INVOICE,
        ];
    }

    public static function commercialDiscount(): self
    {
        return new self(self::COMMERCIAL_DISCOUNT);
    }

    public static function pricingError(): self
    {
        return new self(self::PRICING_ERROR);
    }

    public static function taxTreatmentError(): self
    {
        return new self(self::TAX_TREATMENT_ERROR);
    }

    public static function serviceCancelled(): self
    {
        return new self(self::SERVICE_CANCELLED);
    }

    public static function goodsReturned(): self
    {
        return new self(self::GOODS_RETURNED);
    }

    public static function wrongDebtorReplaced(): self
    {
        return new self(self::WRONG_DEBTOR_REPLACED);
    }

    public static function duplicateInvoice(): self
    {
        return new self(self::DUPLICATE_INVOICE);
    }
}
