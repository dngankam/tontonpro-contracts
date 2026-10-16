<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Menu;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;

/**
 * Le registre où chaque paquet ajoute ses groupes et ses entrées, sans
 * jamais écraser ceux d'un autre.
 *
 * Un groupe redéclaré à l'identique est sans effet ; redéclaré autrement, il
 * est refusé. Une entrée en double est refusée. Un paquet peut poser une
 * entrée dans le groupe d'un autre : elle garde son propriétaire et son droit.
 */
final class MenuRegistry
{
    /** @var array<string, MenuGroup> */
    private array $groups = [];

    /** @var array<string, MenuEntry> */
    private array $entries = [];

    public function addGroup(MenuGroup $group): void
    {
        $existing = $this->groups[$group->key] ?? null;

        if ($existing !== null) {
            if ($existing->equals($group)) {
                return;
            }

            throw new ContractViolation(
                ViolationCode::MenuGroupTaken,
                'key',
                sprintf('Le groupe « %s » est déjà déclaré par « %s ».', $group->key, $existing->owner),
            );
        }

        $this->groups[$group->key] = $group;
    }

    public function addEntry(MenuEntry $entry): void
    {
        if (isset($this->entries[$entry->key])) {
            throw new ContractViolation(
                ViolationCode::MenuEntryTaken,
                'key',
                sprintf('L\'entrée « %s » est déjà déclarée.', $entry->key),
            );
        }

        if (! isset($this->groups[$entry->group])) {
            throw new ContractViolation(
                ViolationCode::MenuGroupUnknown,
                'group',
                sprintf('Le groupe « %s » n\'est pas déclaré.', $entry->group),
            );
        }

        $this->entries[$entry->key] = $entry;
    }

    /**
     * Les groupes, par rang puis par ordre d'ajout.
     *
     * @return list<MenuGroup>
     */
    public function groups(): array
    {
        $groups = array_values($this->groups);
        usort($groups, static fn (MenuGroup $a, MenuGroup $b): int => $a->order <=> $b->order);

        return $groups;
    }

    /**
     * Les entrées d'un groupe, par rang puis par ordre d'ajout.
     *
     * @return list<MenuEntry>
     */
    public function entriesOf(string $group): array
    {
        $entries = array_values(array_filter($this->entries, static fn (MenuEntry $entry): bool => $entry->group === $group));
        usort($entries, static fn (MenuEntry $a, MenuEntry $b): int => $a->order <=> $b->order);

        return $entries;
    }

    /**
     * Le menu visible, filtré par la fonction de droits de l'hôte. Les groupes
     * vides sont omis. L'hôte résout `routeName` et traduit `titleKey`.
     *
     * @param callable(string): bool $can
     *
     * @return list<array{key: string, titleKey: string, order: int, collapsible: bool, defaultOpen: bool, items: list<array{key: string, titleKey: string, icon: string, routeName: ?string, disabled: bool}>}>
     */
    public function visibleFor(callable $can): array
    {
        $menu = [];

        foreach ($this->groups() as $group) {
            $items = [];

            foreach ($this->entriesOf($group->key) as $entry) {
                if ($can($entry->ability)) {
                    $items[] = [
                        'key' => $entry->key,
                        'titleKey' => $entry->titleKey,
                        'icon' => $entry->icon,
                        'routeName' => $entry->routeName,
                        'disabled' => $entry->disabled,
                    ];
                }
            }

            if ($items !== []) {
                $menu[] = [
                    'key' => $group->key,
                    'titleKey' => $group->titleKey,
                    'order' => $group->order,
                    'collapsible' => $group->collapsible,
                    'defaultOpen' => $group->defaultOpen,
                    'items' => $items,
                ];
            }
        }

        return $menu;
    }
}
