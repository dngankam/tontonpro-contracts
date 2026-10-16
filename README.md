# tontonpro/contracts

Les contrats partagés des modules TontonPro : un paquet PHP pur, **sans aucune
dépendance de production**, que chaque module requiert et qui ne requiert aucun
module.

Il définit :

- les **faits d'argent** qu'un module annonce (vente réalisée, encaissement, avoir…) ;
- le **registre de menus** où chaque module ajoute ses entrées ;
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

## Versions

Versionnage sémantique. Le paquet passe en 1.0 dès qu'un contrat sert deux
modules. Tout changement de forme d'un fait sérialisé incrémente `FACT_SCHEMA`.

## Développement

```
composer install
vendor/bin/pest
vendor/bin/phpstan analyse
```

## Licence

MIT — voir [LICENSE](LICENSE).
