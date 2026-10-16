<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Menu;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;

/**
 * Une entrée de menu. Elle garde SON propriétaire et SON droit, même posée
 * dans le groupe d'un autre paquet. Le droit est obligatoire ; la route est
 * un nom, résolu par l'hôte (sans route, l'entrée n'est pas navigable).
 */
final readonly class MenuEntry
{
    public function __construct(
        public string $key,
        public string $owner,
        public string $group,
        public string $titleKey,
        public string $icon,
        public ?string $routeName,
        public string $ability,
        public int $order = 0,
        public bool $disabled = false,
    ) {
        MenuGroup::requireOwnedKey($key, $owner, 'key');

        if (trim($titleKey) === '') {
            throw new ContractViolation(ViolationCode::MenuTitleMissing, 'titleKey', 'Une entrée de menu a une clé de titre.');
        }

        if (trim($ability) === '') {
            throw new ContractViolation(
                ViolationCode::MenuEntryWithoutAbility,
                'ability',
                sprintf('L\'entrée « %s » doit nommer le droit qui la montre.', $key),
            );
        }
    }
}
