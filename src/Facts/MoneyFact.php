<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Facts;

use TontonPro\Contracts\Value\IdempotencyKey;

/**
 * Un fait d'argent : ce qui s'est passé, figé, annoncé par un module dans la
 * transaction de sa source. La comptabilité écoute CETTE interface : un seul
 * écouteur reçoit tous les faits, présents et futurs.
 */
interface MoneyFact
{
    /**
     * Le type du fait (« sale.completed »).
     */
    public static function type(): string;

    public function header(): FactHeader;

    public function idempotencyKey(): IdempotencyKey;

    /**
     * Le corps du fait, sans l'en-tête, dans l'ordre fixé par le code.
     *
     * @return array<string, mixed>
     */
    public function payload(): array;

    /**
     * Relit le corps ; tous les invariants sont revérifiés par le constructeur.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(FactHeader $header, array $payload): static;
}
