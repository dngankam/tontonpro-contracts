<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Vocabulary;

/**
 * Ce que l'argent règle — régime B. Une affectation n'est pas une sortie de caisse.
 */
enum AllocationNature: string
{
    /** Règlement d'une créance. */
    case SettleReceivable = 'settle_receivable';

    /** Acompte. */
    case Advance = 'advance';

    /** Pourboire à reverser. */
    case TipPayable = 'tip_payable';

    /** Somme restant due au client. */
    case CustomerBalance = 'customer_balance';

    /** Caution. */
    case SecurityDeposit = 'security_deposit';
}
