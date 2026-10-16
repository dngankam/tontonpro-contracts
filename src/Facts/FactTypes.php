<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts;

/**
 * La table « type → classe » des faits : seul endroit qui les énumère.
 */
final class FactTypes
{
    /**
     * @return array<string, class-string<MoneyFact>>
     */
    public static function all(): array
    {
        $types = [];

        foreach ([
            SaleCompleted::class,
            SaleCancelled::class,
            SaleChargedToRoom::class,
            RoomChargeAttached::class,
            ServiceConsumed::class,
            InvoiceIssued::class,
            CreditNoteIssued::class,
            PaymentRecorded::class,
            RefundPaid::class,
            SecurityDepositReceived::class,
            SecurityDepositReturned::class,
            SecurityDepositApplied::class,
            LinkedAdjustment::class,
        ] as $class) {
            $types[$class::type()] = $class;
        }

        return $types;
    }

    /**
     * @return class-string<MoneyFact>|null
     */
    public static function classFor(string $type): ?string
    {
        return self::all()[$type] ?? null;
    }
}
