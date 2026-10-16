<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts\Parts;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Value\ExchangeRate;
use TontonPro\Contracts\Value\Fund;
use TontonPro\Contracts\Value\Money;
use TontonPro\Contracts\Value\PaymentMeans;
use TontonPro\Contracts\Value\RateOverride;
use TontonPro\Contracts\Value\SourceReference;
use TontonPro\Contracts\Vocabulary\MovementNature;
use TontonPro\Contracts\Vocabulary\RateRole;

/**
 * Un mouvement PHYSIQUE de fonds : montant et devise réellement reçus ou
 * rendus, dans le fonds qui les détient, et leur contre-valeur dans la
 * monnaie de l'établissement, conservée sans recalcul.
 *
 * M1 devise du montant = devise du fonds ; M2 montant > 0 ; M3 même devise que
 * l'établissement : contre-valeur = montant, ni taux ni dérogation ; M4 devise
 * étrangère : un taux `proposed` dans le sens (devise reçue → monnaie de
 * l'établissement) ; M5 contre-valeur comptable ⟺ taux `accounting` ;
 * M6 un taux par rôle au plus, `bank_executed` refusé.
 */
final readonly class TreasuryMovement
{
    /**
     * @param list<ExchangeRate> $rates
     */
    public function __construct(
        public string $movementRef,
        public MovementNature $nature,
        public Fund $fund,
        public PaymentMeans $means,
        public Money $amount,
        public Money $counterValue,
        public ?Money $accountingCounterValue = null,
        public array $rates = [],
        public ?RateOverride $rateOverride = null,
    ) {
        if (preg_match(SourceReference::LOCAL_ID_PATTERN, $movementRef) !== 1) {
            throw new ContractViolation(ViolationCode::MovementRefInvalid, 'movementRef', sprintf('La référence de mouvement « %s » est invalide.', $movementRef));
        }

        if (! $amount->currency->equals($fund->currency)) {
            throw new ContractViolation(
                ViolationCode::FundCurrencyMismatch,
                'amount',
                sprintf('Le fonds « %s » détient des %s, pas des %s.', $fund->label, $fund->currency->code, $amount->currency->code),
            );
        }

        if ($amount->isZero()) {
            throw new ContractViolation(ViolationCode::MovementAmountZero, 'amount', 'Un mouvement de fonds est strictement positif.');
        }

        if ($accountingCounterValue !== null && ! $accountingCounterValue->currency->equals($counterValue->currency)) {
            throw new ContractViolation(ViolationCode::CurrencyMismatch, 'accountingCounterValue', 'Les deux contre-valeurs sont dans la monnaie de l\'établissement.');
        }

        if ($amount->currency->equals($counterValue->currency)) {
            $this->requireNoConversion();
        } else {
            $this->requireConversion();
        }
    }

    public function rate(RateRole $role): ?ExchangeRate
    {
        foreach ($this->rates as $rate) {
            if ($rate->role === $role) {
                return $rate;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'movementRef' => $this->movementRef,
            'nature' => $this->nature->value,
            'fund' => $this->fund->toArray(),
            'means' => $this->means->toArray(),
            'amount' => $this->amount->toArray(),
            'counterValue' => $this->counterValue->toArray(),
            'accountingCounterValue' => $this->accountingCounterValue?->toArray(),
            'rates' => array_map(static fn (ExchangeRate $rate): array => $rate->toArray(), $this->rates),
            'rateOverride' => $this->rateOverride?->toArray(),
        ];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('movementRef', 'nature', 'fund', 'means', 'amount', 'counterValue', 'accountingCounterValue', 'rates', 'rateOverride');
        $accounting = $reader->nullableObject('accountingCounterValue');
        $override = $reader->nullableObject('rateOverride');

        return new self(
            $reader->string('movementRef'),
            $reader->enum('nature', MovementNature::class),
            Fund::fromReader($reader->object('fund')),
            PaymentMeans::fromReader($reader->object('means')),
            Money::fromReader($reader->object('amount')),
            Money::fromReader($reader->object('counterValue')),
            $accounting === null ? null : Money::fromReader($accounting),
            array_map(ExchangeRate::fromReader(...), $reader->objects('rates')),
            $override === null ? null : RateOverride::fromReader($override),
        );
    }

    private function requireNoConversion(): void
    {
        if (! $this->counterValue->equals($this->amount) || $this->rates !== []
            || $this->accountingCounterValue !== null || $this->rateOverride !== null) {
            throw new ContractViolation(
                ViolationCode::MovementRateUnexpected,
                'rates',
                'Un mouvement dans la monnaie de l\'établissement n\'a ni taux, ni contre-valeur distincte, ni dérogation.',
            );
        }
    }

    private function requireConversion(): void
    {
        $roles = [];

        foreach ($this->rates as $index => $rate) {
            $field = sprintf('rates[%d]', $index);

            if ($rate->role === RateRole::BankExecuted) {
                throw new ContractViolation(ViolationCode::RateRoleNotAllowed, $field, 'Le taux exécuté par la banque sert au change ultérieur, pas à un mouvement.');
            }

            if (isset($roles[$rate->role->value])) {
                throw new ContractViolation(ViolationCode::RateRoleDuplicate, $field, sprintf('Un seul taux « %s » par mouvement.', $rate->role->value));
            }

            $roles[$rate->role->value] = true;

            if (! $rate->base->equals($this->amount->currency) || ! $rate->quote->equals($this->counterValue->currency)) {
                throw new ContractViolation(
                    ViolationCode::RateDirectionInvalid,
                    $field,
                    sprintf('Le taux se lit « 1 %s = x %s ».', $this->amount->currency->code, $this->counterValue->currency->code),
                );
            }
        }

        if (! isset($roles[RateRole::Proposed->value])) {
            throw new ContractViolation(ViolationCode::MovementRateMissing, 'rates', 'Un mouvement en devise étrangère porte le taux proposé.');
        }

        if (($this->accountingCounterValue !== null) !== isset($roles[RateRole::Accounting->value])) {
            throw new ContractViolation(
                ViolationCode::MovementAccountingRateIncoherent,
                'accountingCounterValue',
                'La contre-valeur comptable va avec le taux comptable, et seulement avec lui.',
            );
        }
    }
}
