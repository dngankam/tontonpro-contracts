<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Exceptions;

/**
 * Codes stables des invariants. Un code ne change jamais de sens ; un nouveau
 * code s'ajoute par version mineure.
 */
enum ViolationCode: string
{
    // Montants et devises
    case AmountNegative = 'AMOUNT_NEGATIVE';
    case AmountOverflow = 'AMOUNT_OVERFLOW';
    case AmountNotPositive = 'AMOUNT_NOT_POSITIVE';
    case CurrencyMismatch = 'CURRENCY_MISMATCH';
    case CurrencyCodeInvalid = 'CURRENCY_CODE_INVALID';
    case CurrencyDecimalsInvalid = 'CURRENCY_DECIMALS_INVALID';

    // Dates
    case InstantNotUtc = 'INSTANT_NOT_UTC';
    case InstantInvalid = 'INSTANT_INVALID';
    case BusinessDateInvalid = 'BUSINESS_DATE_INVALID';

    // Références et identités
    case SourceSystemInvalid = 'SOURCE_SYSTEM_INVALID';
    case SourceReferenceInvalid = 'SOURCE_REFERENCE_INVALID';
    case FactVersionInvalid = 'FACT_VERSION_INVALID';
    case FactTypeInvalid = 'FACT_TYPE_INVALID';
    case FactReferenceTypeInvalid = 'FACT_REFERENCE_TYPE_INVALID';
    case EstablishmentInvalid = 'ESTABLISHMENT_INVALID';
    case ActorInvalid = 'ACTOR_INVALID';
    case PartyInvalid = 'PARTY_INVALID';
    case FundInvalid = 'FUND_INVALID';
    case PaymentMeansInvalid = 'PAYMENT_MEANS_INVALID';

    // Taux et quantités
    case TaxRateInvalid = 'TAX_RATE_INVALID';
    case QuantityInvalid = 'QUANTITY_INVALID';
    case RateSameCurrency = 'RATE_SAME_CURRENCY';
    case RateInvalid = 'RATE_INVALID';
    case RateSourceMissing = 'RATE_SOURCE_MISSING';
    case RateOverrideReasonMissing = 'RATE_OVERRIDE_REASON_MISSING';

    // Vocabulaires
    case VocabularyCodeUnknown = 'VOCABULARY_CODE_UNKNOWN';
    case VocabularyJustificationMissing = 'VOCABULARY_JUSTIFICATION_MISSING';

    // Lignes et ventilation
    case LineInvalid = 'LINE_INVALID';
    case LineTaxIncoherent = 'LINE_TAX_INCOHERENT';
    case TaxBucketIncoherent = 'TAX_BUCKET_INCOHERENT';
    case LinesEmpty = 'LINES_EMPTY';
    case LineRefDuplicate = 'LINE_REF_DUPLICATE';
    case TaxBreakdownMismatch = 'TAX_BREAKDOWN_MISMATCH';
    case LinesTotalNegative = 'LINES_TOTAL_NEGATIVE';
    case LinesTotalNotPositive = 'LINES_TOTAL_NOT_POSITIVE';
    case LinesBreakdownUnreconciled = 'LINES_BREAKDOWN_UNRECONCILED';

    // Gratuités
    case ZeroChargeWithoutQualification = 'ZERO_CHARGE_WITHOUT_QUALIFICATION';
    case ComplimentaryTaxNotConfigured = 'COMPLIMENTARY_TAX_NOT_CONFIGURED';
    case ComplimentaryAmountNotZero = 'COMPLIMENTARY_AMOUNT_NOT_ZERO';
    case ComplimentaryNotOnCharge = 'COMPLIMENTARY_NOT_ON_CHARGE';
    case ComplimentaryReferencePriceInvalid = 'COMPLIMENTARY_REFERENCE_PRICE_INVALID';
    case ZeroTotalWithoutQualification = 'ZERO_TOTAL_WITHOUT_QUALIFICATION';
    case ZeroQualificationUnexpected = 'ZERO_QUALIFICATION_UNEXPECTED';

    // Trésorerie
    case FundCurrencyMismatch = 'FUND_CURRENCY_MISMATCH';
    case MovementAmountZero = 'MOVEMENT_AMOUNT_ZERO';
    case MovementRefInvalid = 'MOVEMENT_REF_INVALID';
    case MovementRateUnexpected = 'MOVEMENT_RATE_UNEXPECTED';
    case MovementRateMissing = 'MOVEMENT_RATE_MISSING';
    case RateDirectionInvalid = 'RATE_DIRECTION_INVALID';
    case MovementAccountingRateIncoherent = 'MOVEMENT_ACCOUNTING_RATE_INCOHERENT';
    case RateRoleNotAllowed = 'RATE_ROLE_NOT_ALLOWED';
    case RateRoleDuplicate = 'RATE_ROLE_DUPLICATE';
    case AllocationIncoherent = 'ALLOCATION_INCOHERENT';
    case MovementRefDuplicate = 'MOVEMENT_REF_DUPLICATE';
    case AllocationRefDuplicate = 'ALLOCATION_REF_DUPLICATE';
    case TreasuryNatureIncoherent = 'TREASURY_NATURE_INCOHERENT';
    case TreasuryUnbalanced = 'TREASURY_UNBALANCED';
    case TreasuryDirectionInvalid = 'TREASURY_DIRECTION_INVALID';
    case AllocationNatureNotAllowed = 'ALLOCATION_NATURE_NOT_ALLOWED';

    // Faits
    case DocumentNumberMissing = 'DOCUMENT_NUMBER_MISSING';
    case ExplanationMissing = 'EXPLANATION_MISSING';
    case RoomChargeAcrossCompanies = 'ROOM_CHARGE_ACROSS_COMPANIES';
    case RoomChargeCompanyUnresolved = 'ROOM_CHARGE_COMPANY_UNRESOLVED';
    case RoomChargeLineNotLinked = 'ROOM_CHARGE_LINE_NOT_LINKED';
    case ServiceDateMismatch = 'SERVICE_DATE_MISMATCH';
    case BillingStatusIncoherent = 'BILLING_STATUS_INCOHERENT';
    case CreditNoteSeriesNotDistinct = 'CREDIT_NOTE_SERIES_NOT_DISTINCT';
    case CreditNoteExceedsInvoice = 'CREDIT_NOTE_EXCEEDS_INVOICE';
    case CreditNoteScopeIncoherent = 'CREDIT_NOTE_SCOPE_INCOHERENT';
    case AdjustmentBeforeService = 'ADJUSTMENT_BEFORE_SERVICE';

    // Registre de menus
    case MenuKeyNotOwned = 'MENU_KEY_NOT_OWNED';
    case MenuTitleMissing = 'MENU_TITLE_MISSING';
    case MenuEntryWithoutAbility = 'MENU_ENTRY_WITHOUT_ABILITY';
    case MenuGroupTaken = 'MENU_GROUP_TAKEN';
    case MenuEntryTaken = 'MENU_ENTRY_TAKEN';
    case MenuGroupUnknown = 'MENU_GROUP_UNKNOWN';

    // Plan de comptes
    case AccountNumberInvalid = 'ACCOUNT_NUMBER_INVALID';
    case AccountNumberDuplicate = 'ACCOUNT_NUMBER_DUPLICATE';
    case AccountLabelMissing = 'ACCOUNT_LABEL_MISSING';
    case AccountParentInvalid = 'ACCOUNT_PARENT_INVALID';
    case AccountsEmpty = 'ACCOUNTS_EMPTY';
    case AccountContributionInvalid = 'ACCOUNT_CONTRIBUTION_INVALID';

    // Tableau de bord (0.1.1)
    case DashboardKeyNotOwned = 'DASHBOARD_KEY_NOT_OWNED';
    case DashboardPanelMalformed = 'DASHBOARD_PANEL_MALFORMED';
    case DashboardPanelWithoutAbility = 'DASHBOARD_PANEL_WITHOUT_ABILITY';
    case DashboardPanelTaken = 'DASHBOARD_PANEL_TAKEN';
    case DashboardFigureMalformed = 'DASHBOARD_FIGURE_MALFORMED';
}
