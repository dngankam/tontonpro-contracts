<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Dashboard;

use TontonPro\Contracts\Value\UtcInstant;

/**
 * Un module qui pose ses panneaux d'indicateurs sur le tableau de bord de
 * l'hôte, pour un utilisateur et à un instant donnés.
 *
 * L'hôte ne connaît pas le module : il appelle chaque contributeur inscrit,
 * puis filtre les panneaux par leur droit. Le module lit ses propres données
 * (sites, rôles, journée d'exploitation, fuseau de l'établissement) à partir
 * de l'identifiant de l'utilisateur de l'hôte ; un utilisateur qui n'a rien
 * à voir chez lui ne reçoit aucun panneau, et ce n'est pas une erreur.
 */
interface DashboardContributor
{
    public function contributeTo(DashboardBoard $board, int $userId, UtcInstant $now): void;
}
