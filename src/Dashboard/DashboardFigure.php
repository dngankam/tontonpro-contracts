<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Dashboard;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Value\BusinessDate;
use TontonPro\Contracts\Value\Currency;
use TontonPro\Contracts\Value\Money;

/**
 * Un indicateur d'un panneau : un libellé (clé de traduction), une nature et
 * sa valeur, déjà calculée par le module. L'hôte ne calcule rien, n'additionne
 * rien : il traduit, formate selon la nature et affiche.
 *
 * - `count`, `percent_bp` : `value` entier ≥ 0, sans devise ni journée ;
 * - `money` : `value` entier ≥ 0 en unité mineure ET sa devise (règle des
 *   faits) ; deux devises ne se somment jamais, un panneau en montre deux
 *   indicateurs ;
 * - `business_date` : `date` seule, sans `value` ni devise.
 *
 * Aucune heure murale : une heure dépend du fuseau de l'établissement, que
 * l'hôte ne connaît pas. Le module choisit le libellé ou l'indication qui
 * convient (« à clôturer après la coupe »). La route est un nom, résolu par
 * l'hôte ; elle garde son propre contrôle de droit.
 */
final readonly class DashboardFigure
{
    public function __construct(
        public string $labelKey,
        public DashboardFigureKind $kind,
        public ?int $value,
        public ?Currency $currency = null,
        public ?BusinessDate $date = null,
        public ?string $hintKey = null,
        public ?string $routeName = null,
    ) {
        if (trim($labelKey) === '') {
            self::malformed('labelKey', 'Un indicateur a une clé de libellé.');
        }

        self::requireNullOrFilled($hintKey, 'hintKey');
        self::requireNullOrFilled($routeName, 'routeName');

        if ($kind === DashboardFigureKind::BusinessDate) {
            if ($date === null || $value !== null || $currency !== null) {
                self::malformed('date', sprintf('L\'indicateur « %s » est une journée : une date, sans valeur ni devise.', $labelKey));
            }

            return;
        }

        if ($value === null || $date !== null) {
            self::malformed('value', sprintf('L\'indicateur « %s » porte une valeur entière, sans journée.', $labelKey));
        }

        if ($kind === DashboardFigureKind::Money) {
            if ($currency === null) {
                self::malformed('currency', sprintf('Le montant « %s » porte sa devise et ses décimales.', $labelKey));
            }

            if ($value < 0) {
                throw new ContractViolation(
                    ViolationCode::AmountNegative,
                    'value',
                    sprintf('Un montant est toujours positif ou nul (%d reçu) : le sens se dit ailleurs.', $value),
                );
            }

            return;
        }

        if ($currency !== null) {
            self::malformed('currency', sprintf('L\'indicateur « %s » n\'est pas un montant : il n\'a pas de devise.', $labelKey));
        }

        if ($value < 0) {
            self::malformed('value', sprintf('L\'indicateur « %s » est positif ou nul (%d reçu).', $labelKey, $value));
        }
    }

    public static function count(string $labelKey, int $value, ?string $hintKey = null, ?string $routeName = null): self
    {
        return new self($labelKey, DashboardFigureKind::Count, $value, null, null, $hintKey, $routeName);
    }

    /**
     * Un taux en points de base : 8 250 = 82,50 %.
     */
    public static function percentBasisPoints(string $labelKey, int $basisPoints, ?string $hintKey = null, ?string $routeName = null): self
    {
        return new self($labelKey, DashboardFigureKind::PercentBasisPoints, $basisPoints, null, null, $hintKey, $routeName);
    }

    public static function money(string $labelKey, Money $money, ?string $hintKey = null, ?string $routeName = null): self
    {
        return new self($labelKey, DashboardFigureKind::Money, $money->amount, $money->currency, null, $hintKey, $routeName);
    }

    public static function businessDate(string $labelKey, BusinessDate $date, ?string $hintKey = null, ?string $routeName = null): self
    {
        return new self($labelKey, DashboardFigureKind::BusinessDate, null, null, $date, $hintKey, $routeName);
    }

    /**
     * La forme lue par l'hôte : toutes les clés toujours présentes.
     *
     * @return array{labelKey: string, kind: string, value: ?int, currency: ?array{code: string, decimals: int}, date: ?string, hintKey: ?string, routeName: ?string}
     */
    public function toArray(): array
    {
        return [
            'labelKey' => $this->labelKey,
            'kind' => $this->kind->value,
            'value' => $this->value,
            'currency' => $this->currency?->toArray(),
            'date' => $this->date?->value,
            'hintKey' => $this->hintKey,
            'routeName' => $this->routeName,
        ];
    }

    private static function requireNullOrFilled(?string $value, string $field): void
    {
        if ($value !== null && trim($value) === '') {
            self::malformed($field, sprintf('« %s » est absent (null) ou renseigné, jamais vide.', $field));
        }
    }

    private static function malformed(string $field, string $message): never
    {
        throw new ContractViolation(ViolationCode::DashboardFigureMalformed, $field, $message);
    }
}
