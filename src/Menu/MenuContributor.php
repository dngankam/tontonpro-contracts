<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Menu;

/**
 * Un paquet qui ajoute ses groupes et ses entrées au menu de l'hôte.
 */
interface MenuContributor
{
    public function contributeTo(MenuRegistry $menu): void;
}
