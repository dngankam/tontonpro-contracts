<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts\Concerns;

use TontonPro\Contracts\Company\SameCompanyAnswer;
use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Facts\FactHeader;
use TontonPro\Contracts\Facts\Parts\LineSet;
use TontonPro\Contracts\Facts\Parts\TreasuryOperation;
use TontonPro\Contracts\Value\Currency;
use TontonPro\Contracts\Value\IdempotencyKey;
use TontonPro\Contracts\Value\Money;

/**
 * Ce que tous les faits partagent : l'en-tête, la clé d'idempotence, et les
 * contrôles communs (devise de l'établissement, texte obligatoire, report en
 * chambre permis).
 *
 * @property-read FactHeader $header
 */
trait DescribesMoneyFact
{
    public function header(): FactHeader
    {
        return $this->header;
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return new IdempotencyKey($this->header->source, self::type(), $this->header->factVersion);
    }

    private function requireCurrency(Currency $currency, string $field): void
    {
        $expected = $this->header->currency();

        if (! $currency->equals($expected)) {
            throw new ContractViolation(
                ViolationCode::CurrencyMismatch,
                $field,
                sprintf('Les montants du fait sont en %s, monnaie de l\'établissement, pas en %s.', $expected->code, $currency->code),
            );
        }
    }

    private function requireMoney(Money $money, string $field): void
    {
        $this->requireCurrency($money->currency, $field);
    }

    private function requirePositive(Money $money, string $field): void
    {
        $this->requireMoney($money, $field);

        if ($money->isZero()) {
            throw new ContractViolation(ViolationCode::AmountNotPositive, $field, 'Ce montant est strictement positif.');
        }
    }

    private function requireLines(LineSet $lines, string $field): void
    {
        $this->requireCurrency($lines->currency(), $field);
    }

    private function requireOperation(TreasuryOperation $operation, string $field): void
    {
        $this->requireCurrency($operation->currency(), $field);
    }

    private static function requireText(string $value, ViolationCode $code, string $field, string $message): void
    {
        if (trim($value) === '') {
            throw new ContractViolation($code, $field, $message);
        }
    }

    /**
     * Le parcours entre deux sociétés n'existe pas, et un fait ne peut pas
     * prétendre le contraire. Sans rattachement connu, le report est refusé.
     */
    private static function requireRoomChargePermitted(SameCompanyAnswer $answer, string $field): void
    {
        if ($answer === SameCompanyAnswer::Different) {
            throw new ContractViolation(
                ViolationCode::RoomChargeAcrossCompanies,
                $field,
                'Un report en chambre entre deux sociétés différentes n\'est pas pris en charge.',
            );
        }

        if ($answer === SameCompanyAnswer::Unresolved) {
            throw new ContractViolation(
                ViolationCode::RoomChargeCompanyUnresolved,
                $field,
                'La comptabilité ne sait pas rattacher un des deux établissements : le report est refusé.',
            );
        }
    }
}
