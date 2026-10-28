# CLAUDE.md — tontonpro/contracts

Contrats partagés des modules TontonPro : faits d'argent, registre de menus,
tableau de bord, contribution au plan de comptes, question « même société ».
Paquet PHP pur.

## Interdits

- Aucune dépendance de production (seulement `php`). Ni Laravel, ni Carbon.
- Aucun numéro de compte dans le code : ils vivent dans les données des modules.
- Dépôt public : aucun chemin local, aucun nom de serveur, de site, de client
  ou de cabinet, aucun lien vers un dépôt privé.
- Aucun trailer dans les messages de commit.
- Aucun fichier de plus de 300 lignes.

## Conventions

- `declare(strict_types=1)` partout ; toutes les classes `final`, aucune abstraite.
- Objets de valeur et faits : `final readonly class`. Seuls les registres `MenuRegistry` et
  `DashboardBoard` sont mutables.
- Identifiants, propriétés et codes en anglais ; docblocks, README et messages en français.
- Montants : `int` en unité mineure, toujours ≥ 0, devise ISO avec ses décimales ;
  le sens est porté explicitement. Aucun flottant (taux et quantités en chaînes).
- Instants en UTC (refus, jamais conversion) ; journée `Y-m-d` sans fuseau.
- Un invariant rompu lève `ContractViolation` avec un `ViolationCode` stable ;
  une relecture refusée lève `MalformedFact` avec son chemin.

## Règles de travail

- Tout invariant a un témoin qui accepte et un témoin qui refuse, avec le code attendu.
- Toute modification de forme d'un fait incrémente `ContractsVersion::FACT_SCHEMA`
  et met à jour `tests/Fixtures/`.
- `ContractsVersion::VERSION` suit le tag.
- Un nouveau cas d'énumération (régime B) ou une nouvelle valeur connue (régime A)
  entre par version mineure, justifiée dans le commit et le README.

## Commandes

```
composer install
vendor/bin/pest
vendor/bin/phpstan analyse
```
