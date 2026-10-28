<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Dashboard;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;

/**
 * Le tableau de bord d'UN utilisateur à UN instant : chaque module y pose ses
 * panneaux, sans jamais écraser ceux d'un autre. L'hôte en crée un par
 * affichage et le passe à chaque contributeur.
 *
 * Un panneau en double est refusé. Un refus n'efface rien : les panneaux déjà
 * posés, par ce module ou un autre, restent. Un tableau vide n'est pas une
 * erreur : l'hôte montre alors son propre message.
 */
final class DashboardBoard
{
    /** @var array<string, DashboardPanel> */
    private array $panels = [];

    public function addPanel(DashboardPanel $panel): void
    {
        $existing = $this->panels[$panel->key] ?? null;

        if ($existing !== null) {
            throw new ContractViolation(
                ViolationCode::DashboardPanelTaken,
                'key',
                sprintf('Le panneau « %s » est déjà posé par « %s ».', $panel->key, $existing->owner),
            );
        }

        $this->panels[$panel->key] = $panel;
    }

    /**
     * Les panneaux, par rang puis par ordre d'ajout.
     *
     * @return list<DashboardPanel>
     */
    public function panels(): array
    {
        $panels = array_values($this->panels);
        usort($panels, static fn (DashboardPanel $a, DashboardPanel $b): int => $a->order <=> $b->order);

        return $panels;
    }

    /**
     * Les panneaux visibles, filtrés par la fonction de droits de l'hôte, par
     * rang puis par ordre d'ajout. L'hôte résout les routes et traduit les clés.
     *
     * @param callable(string): bool $can
     *
     * @return list<array{key: string, titleKey: string, subtitle: ?string, businessDate: ?string, order: int, routeName: ?string, linkTitleKey: ?string, figures: list<array{labelKey: string, kind: string, value: ?int, currency: ?array{code: string, decimals: int}, date: ?string, hintKey: ?string, routeName: ?string}>}>
     */
    public function visibleFor(callable $can): array
    {
        $visible = [];

        foreach ($this->panels() as $panel) {
            if ($can($panel->ability)) {
                $visible[] = $panel->toArray();
            }
        }

        return $visible;
    }
}
