<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Dashboard;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Value\BusinessDate;
use TontonPro\Contracts\Value\SourceReference;

/**
 * Un panneau du tableau de bord, posé par son propriétaire : un titre (clé
 * de traduction), le droit qui le montre (obligatoire), ses indicateurs (au
 * moins un, libellés distincts) et, s'il y a lieu, un lien « Ouvrir ».
 *
 * Sa clé est préfixée par le propriétaire (« hotelpro.desk »), comme une clé
 * de menu. Le sous-titre est un texte déjà résolu par le module (le nom du
 * site) ; la journée est celle à laquelle se rapportent les chiffres « du
 * jour », nommée explicitement, jamais déduite d'un fuseau par l'hôte.
 * Rang 0 : celui des panneaux de l'hôte.
 */
final readonly class DashboardPanel
{
    /**
     * @param list<DashboardFigure> $figures
     */
    public function __construct(
        public string $key,
        public string $owner,
        public string $titleKey,
        public string $ability,
        public array $figures,
        public int $order = 0,
        public ?string $routeName = null,
        public ?string $linkTitleKey = null,
        public ?string $subtitle = null,
        public ?BusinessDate $businessDate = null,
    ) {
        if (preg_match(SourceReference::SYSTEM_PATTERN, $owner) !== 1
            || preg_match('/^'.preg_quote($owner, '/').'\.[a-z0-9][a-z0-9_.-]{0,63}$/', $key) !== 1) {
            throw new ContractViolation(
                ViolationCode::DashboardKeyNotOwned,
                'key',
                sprintf('La clé « %s » doit commencer par « %s. ».', $key, $owner),
            );
        }

        if (trim($titleKey) === '') {
            self::malformed('titleKey', sprintf('Le panneau « %s » a une clé de titre.', $key));
        }

        if (trim($ability) === '') {
            throw new ContractViolation(
                ViolationCode::DashboardPanelWithoutAbility,
                'ability',
                sprintf('Le panneau « %s » doit nommer le droit qui le montre.', $key),
            );
        }

        self::requireFigures($key, $figures);

        foreach (['routeName' => $routeName, 'linkTitleKey' => $linkTitleKey, 'subtitle' => $subtitle] as $field => $value) {
            if ($value !== null && trim($value) === '') {
                self::malformed($field, sprintf('« %s » est absent (null) ou renseigné, jamais vide.', $field));
            }
        }

        if (($routeName === null) !== ($linkTitleKey === null)) {
            self::malformed('linkTitleKey', sprintf('Le lien du panneau « %s » a une route ET un libellé, ou aucun des deux.', $key));
        }
    }

    /**
     * La forme lue par l'hôte : toutes les clés toujours présentes.
     *
     * @return array{key: string, titleKey: string, subtitle: ?string, businessDate: ?string, order: int, routeName: ?string, linkTitleKey: ?string, figures: list<array{labelKey: string, kind: string, value: ?int, currency: ?array{code: string, decimals: int}, date: ?string, hintKey: ?string, routeName: ?string}>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'titleKey' => $this->titleKey,
            'subtitle' => $this->subtitle,
            'businessDate' => $this->businessDate?->value,
            'order' => $this->order,
            'routeName' => $this->routeName,
            'linkTitleKey' => $this->linkTitleKey,
            'figures' => array_map(static fn (DashboardFigure $figure): array => $figure->toArray(), $this->figures),
        ];
    }

    /**
     * @param list<DashboardFigure> $figures
     */
    private static function requireFigures(string $key, array $figures): void
    {
        if ($figures === []) {
            self::malformed('figures', sprintf('Le panneau « %s » montre au moins un indicateur.', $key));
        }

        $labels = [];

        foreach ($figures as $index => $figure) {
            if (isset($labels[$figure->labelKey])) {
                self::malformed(
                    sprintf('figures[%d].labelKey', $index),
                    sprintf('Le libellé « %s » figure deux fois dans le panneau « %s ».', $figure->labelKey, $key),
                );
            }

            $labels[$figure->labelKey] = true;
        }
    }

    private static function malformed(string $field, string $message): never
    {
        throw new ContractViolation(ViolationCode::DashboardPanelMalformed, $field, $message);
    }
}
