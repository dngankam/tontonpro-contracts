<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Menu;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Value\SourceReference;

/**
 * Un groupe de la barre de navigation, déclaré par son propriétaire.
 * Sa clé est préfixée par le propriétaire (« hotelpro.desk »). Le titre est
 * une clé de traduction : l'hôte traduit, le contrat n'a pas de traducteur.
 * Rang 0 : celui des groupes de l'hôte.
 */
final readonly class MenuGroup
{
    public function __construct(
        public string $key,
        public string $owner,
        public string $titleKey,
        public int $order,
        public bool $collapsible = false,
        public bool $defaultOpen = true,
    ) {
        self::requireOwnedKey($key, $owner, 'key');

        if (trim($titleKey) === '') {
            throw new ContractViolation(ViolationCode::MenuTitleMissing, 'titleKey', 'Un groupe de menu a une clé de titre.');
        }
    }

    /**
     * Une clé de menu appartient à son propriétaire : « <owner>.<nom> ».
     */
    public static function requireOwnedKey(string $key, string $owner, string $field): void
    {
        if (preg_match(SourceReference::SYSTEM_PATTERN, $owner) !== 1
            || preg_match('/^'.preg_quote($owner, '/').'\.[a-z0-9][a-z0-9_.-]{0,63}$/', $key) !== 1) {
            throw new ContractViolation(
                ViolationCode::MenuKeyNotOwned,
                $field,
                sprintf('La clé « %s » doit commencer par « %s. ».', $key, $owner),
            );
        }
    }

    public function equals(self $other): bool
    {
        return $this->key === $other->key
            && $this->owner === $other->owner
            && $this->titleKey === $other->titleKey
            && $this->order === $other->order
            && $this->collapsible === $other->collapsible
            && $this->defaultOpen === $other->defaultOpen;
    }
}
