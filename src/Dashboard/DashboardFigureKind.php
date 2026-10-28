<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Dashboard;

/**
 * Nature d'un indicateur du tableau de bord — régime B. Elle dit à l'hôte
 * comment lire la valeur ; elle ne se déduit jamais du libellé.
 */
enum DashboardFigureKind: string
{
    /** Un nombre entier ≥ 0 (arrivées, chambres à nettoyer…). */
    case Count = 'count';

    /** Un taux en points de base, ≥ 0 (8 250 = 82,50 %) ; il peut dépasser 10 000. */
    case PercentBasisPoints = 'percent_bp';

    /** Un montant entier en unité mineure, ≥ 0, avec sa devise et ses décimales. */
    case Money = 'money';

    /** Une journée d'exploitation « AAAA-MM-JJ », sans fuseau (la journée à clôturer). */
    case BusinessDate = 'business_date';
}
