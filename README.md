# tontonpro/contracts

Les contrats partagés des modules TontonPro : un paquet PHP pur, **sans aucune
dépendance de production**, que chaque module requiert et qui ne requiert aucun
module.

Il définit :

- les **faits d'argent** qu'un module annonce (vente réalisée, encaissement, avoir…) ;
- le **registre de menus** où chaque module ajoute ses entrées ;
- le **tableau de bord** où chaque module pose ses panneaux d'indicateurs ;
- la **contribution au plan de comptes** qu'un module déclare ;
- la question **« ces deux établissements appartiennent-ils à la même société ? »**.

Il ne fait **pas** d'écriture comptable, ne contient **aucun** numéro de compte,
aucune table, aucune dépendance à un framework. La comptabilité, quand elle est
installée, écoute les faits et en tire ses écritures par ses propres données.

## Installation

```
composer require tontonpro/contracts
```

PHP 8.4 ou plus récent.

## Annoncer un fait depuis un module

Le module construit le fait **dans la transaction de sa source** et l'annonce.
Un constructeur refuse tout fait incohérent (`ContractViolation`, avec un code
stable) : un fait construit est un fait valide.

```php
use TontonPro\Contracts\Facts\SaleCompleted;

$fait = new SaleCompleted($entete, '2026-000123', null, $lignes);

event($fait); // dans la transaction ; sans comptabilité installée, rien ne change
```

## Écouter tous les faits

Tous les faits implémentent `TontonPro\Contracts\Facts\MoneyFact`. Un seul
écouteur inscrit sur cette interface reçoit tous les faits, présents et futurs.

## Les 13 faits

| Type | Classe | Sens |
|---|---|---|
| `sale.completed` | `SaleCompleted` | une vente devenue définitive (payée ou portée en chambre) |
| `sale.cancelled` | `SaleCancelled` | l'annulation d'une vente, motivée ; le sort de l'argent est un autre fait |
| `sale.charged_to_room` | `SaleChargedToRoom` | côté point de vente : la vente est réglée par report sur une note |
| `room_charge.attached` | `RoomChargeAttached` | côté hôtel : les lignes reportées rejoignent la note, sans produit ni taxe nouveaux |
| `service.consumed` | `ServiceConsumed` | une prestation réellement consommée, reconnue à la journée |
| `invoice.issued` | `InvoiceIssued` | une facture émise |
| `credit_note.issued` | `CreditNoteIssued` | un avoir, en série distincte, motivé |
| `payment.recorded` | `PaymentRecorded` | un encaissement : fonds reçus et rendus, et ce qu'ils règlent |
| `refund.paid` | `RefundPaid` | un remboursement |
| `security_deposit.received` | `SecurityDepositReceived` | une caution reçue |
| `security_deposit.returned` | `SecurityDepositReturned` | une caution restituée |
| `security_deposit.applied` | `SecurityDepositApplied` | une caution affectée à une facture, sans mouvement de fonds |
| `adjustment.linked` | `LinkedAdjustment` | la correction d'un fait passé, qui n'est jamais réécrit |

Chaque fait porte un en-tête : révision, référence source, établissement figé
(système, identifiant, nom, devise), instant UTC, journée d'exploitation, auteur.

## Montants, devises, taux

- Un montant est un **entier en unité mineure**, toujours **≥ 0**, avec sa devise
  ISO 4217 **et ses décimales** (de 0 à 4). Le sens est porté explicitement :
  nature du mouvement, sens de l'opération, type de ligne, portée de l'avoir.
- **Aucun flottant** : taux de change et quantités sont des chaînes décimales
  exactes ; les taux de taxe sont en points de base (1925 = 19,25 %).
- Tous les montants d'un fait sont dans la **monnaie de l'établissement**, sauf
  le montant réellement reçu ou rendu d'un mouvement de trésorerie.
- Un taux se lit « 1 `base` = `rate` `quote` ». La contre-valeur est conservée
  telle qu'elle a été calculée, sans recalcul.

Exemple : un client règle une facture de 60 000 XAF avec un billet de 100 EUR,
à la parité officielle 655,957. 100 EUR valent 65 595,7, arrondis à **65 596 XAF**.
On lui rend 4 596 XAF et il laisse 1 000 XAF de pourboire :

| | XAF |
|---|---|
| reçu : 100 EUR (contre-valeur) | 65 596 |
| rendu | − 4 596 |
| **affecté** : règlement de la facture | 60 000 |
| **affecté** : pourboire à reverser | 1 000 |

Les 65 596 XAF ne sont **pas** affectés en entier à la facture : une
`TreasuryOperation` refuse tout déséquilibre entre fonds et affectations.

## Gratuités

Une ligne à 0 doit être **qualifiée** (`Complimentary`) : motif, auteur de la
gratuité, et prix de référence quand il existe — on n'invente jamais de prix.
Un prix absent ou un paramétrage incomplet ne devient jamais une gratuité.
Une ligne qualifiée vaut 0 exactement et porte un traitement fiscal connu.

Un total nul obtenu par **compensation** (une remise de 100 %, des remises qui
annulent des prestations positives) n'est accepté que **qualifié** de la même
façon : `LineSet::$zeroQualification`, une `Complimentary`, prix de référence
facultatif. Cette qualification est interdite quand le total est positif.

Une vente, une prestation consommée et une facture peuvent avoir un total nul
(un repas ou une nuitée offerts). Un avoir, un report en chambre et un
ajustement ont toujours un total strictement positif, même qualifié. Les lignes
restent détaillées, même quand elles se compensent.

## Sérialisation et clé d'idempotence

`FactSerializer` donne une forme stable : ordre des clés fixé, toutes les clés
toujours présentes, aucun flottant. La relecture reconstruit le fait par ses
constructeurs (tous les invariants sont revérifiés) et refuse toute clé
absente ou inattendue (`MalformedFact`, avec le chemin fautif).

La clé d'idempotence est dérivée : `système|référence|type|révision`, par
exemple `restopro|sell:48213|sale.completed|1`. La révision est celle du fait,
pas celle du schéma. L'empreinte (`fingerprint()`, SHA-256 du JSON) distingue
un rejeu (même clé, même empreinte) d'un conflit (même clé, empreinte différente).

## Vocabulaires

Deux régimes :

- **régime A — ouvert, justifié** (motifs, moyens de paiement) : une valeur
  inconnue est acceptée si elle est espacée par module (`x.<système>.<code>`)
  **et** justifiée. Une valeur éprouvée entre ensuite dans la liste par version
  mineure ;
- **régime B — fermé** (natures dont dépendent les invariants : traitement
  fiscal, nature des mouvements et des affectations…) : énumérations étendues
  seulement par version mineure.

**Les listes de motifs d'annulation, d'ajustement et de gratuité sont
provisoires**, à relire par l'expert-comptable. Le régime A permet de les
compléter sans nouvelle version. Les sept motifs d'avoir sont arrêtés.

## Menu, plan de comptes, « même société », version

Le tableau de bord a sa propre section, plus bas.

- `MenuRegistry` : chaque module ajoute ses groupes et ses entrées ; un groupe
  déjà déclaré n'est jamais écrasé ; une entrée garde son propriétaire et son
  droit, même dans le groupe d'un autre module. L'hôte filtre par ses droits,
  résout les routes et traduit les titres.
- `AccountContribution` : les comptes qu'un module demande d'ajouter, lus depuis
  un fichier de données du module. Ajouter sans rien écraser est la règle de la
  comptabilité.
- `SameCompanyResolver` : liée par défaut à `NoAccountingResolver` (« personne ne
  sait », aucun refus) ; la comptabilité remplace la liaison. Un report en
  chambre entre deux sociétés, ou sans rattachement connu, est refusé.
- `ContractsVersion::VERSION` et `ContractsVersion::FACT_SCHEMA`.

Les modules cités en exemple (RestoPro, HotelPro) ne sont que des exemples.

## Contribuer au tableau de bord

Même forme que le menu : l'hôte crée un `DashboardBoard` par affichage, appelle
chaque `DashboardContributor` inscrit, puis filtre les panneaux par leur droit.
L'hôte n'appelle jamais un module directement et ne calcule aucun chiffre.

- `DashboardPanel` : clé préfixée par le propriétaire (`hotelpro.desk`), titre
  (clé de traduction), **droit obligatoire**, au moins un indicateur (libellés
  distincts), rang, lien facultatif (route **et** libellé, ou aucun), sous-titre
  déjà résolu (le nom du site) et journée à laquelle se rapportent les chiffres.
- `DashboardFigure` : un libellé et une nature (`DashboardFigureKind`, régime B) :
  `count` et `percent_bp` (entier ≥ 0, points de base : 8 250 = 82,50 %),
  `money` (entier ≥ 0 en unité mineure **avec sa devise et ses décimales**, comme
  un fait ; deux devises ne se somment jamais : deux indicateurs), `business_date`
  (une journée `AAAA-MM-JJ`, sans fuseau). Indication et route facultatives.
- **Aucune heure murale** : le contributeur reçoit un instant UTC (`UtcInstant`)
  et l'identifiant de l'utilisateur de l'hôte ; il lit lui-même le fuseau et la
  journée de son établissement.
- Un panneau en double est refusé (`DASHBOARD_PANEL_TAKEN`) ; un refus n'efface
  aucun panneau déjà posé. Un tableau vide n'est pas une erreur : l'hôte montre
  son propre message, ou envoie l'utilisateur ailleurs (un vendeur sur sa caisse).

Un module :

```php
use TontonPro\Contracts\Dashboard\{DashboardBoard, DashboardContributor, DashboardFigure, DashboardPanel};
use TontonPro\Contracts\Value\{BusinessDate, Money, UtcInstant};

final class HotelDashboard implements DashboardContributor
{
    public function contributeTo(DashboardBoard $board, int $userId, UtcInstant $now): void
    {
        foreach ($this->sitesOf($userId) as $site) { // ses propres données ; aucun site : aucun panneau
            $board->addPanel(new DashboardPanel(
                key: 'hotelpro.desk.'.$site->id,
                owner: 'hotelpro',
                titleKey: 'hotelpro::dashboard.desk',
                ability: 'hotelpro.ledger.view',
                figures: [
                    DashboardFigure::percentBasisPoints('hotelpro::dashboard.occupancy', $site->occupancyBp($now)),
                    DashboardFigure::count('hotelpro::dashboard.arrivals_expected', $site->arrivalsExpected($now)),
                    DashboardFigure::money('hotelpro::dashboard.collected', new Money($site->collected($now), $site->currency())),
                    DashboardFigure::businessDate('hotelpro::dashboard.day_to_close', new BusinessDate($site->openDay())),
                ],
                order: 10,
                routeName: 'hotelpro.ledger',
                linkTitleKey: 'hotelpro::nav.ledger',
                subtitle: $site->name,
                businessDate: new BusinessDate($site->businessDay($now)),
            ));
        }
    }
}
```

L'hôte :

```php
$board = new DashboardBoard();
$now = new UtcInstant(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

foreach ($contributors as $contributor) { // les contributeurs inscrits ; aucun : tableau vide
    try {
        $contributor->contributeTo($board, $user->id, $now);
    } catch (ContractViolation $violation) {
        report($violation); // un module fautif ne retire pas les panneaux des autres
    }
}

$panels = $board->visibleFor(fn (string $ability): bool => $user->can($ability));
// [] : l'hôte montre son message utile. Sinon : traduire les clés, résoudre les routes, formater selon `kind`.
```

## Versions

Versionnage sémantique. Le paquet passe en 1.0 dès qu'un contrat sert deux
modules. Tout changement de forme d'un fait sérialisé incrémente `FACT_SCHEMA`.

- **0.1.1** — ajout pur : contrat du tableau de bord (`TontonPro\Contracts\Dashboard`),
  l'énumération fermée `DashboardFigureKind` (une nature décide de la lecture de
  la valeur : elle ne peut pas être ouverte) et cinq codes `DASHBOARD_*`. Aucune
  signature existante ne change ; `FACT_SCHEMA` reste 1. Une contrainte `^0.1`
  la prend.
- **0.1.0** — faits d'argent, registre de menus, plan de comptes, « même société ».

## Développement

```
composer install
vendor/bin/pest
vendor/bin/phpstan analyse
```

## Licence

MIT — voir [LICENSE](LICENSE).
