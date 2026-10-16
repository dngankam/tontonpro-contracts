<?php

declare(strict_types=1);

namespace TontonPro\Contracts;

/**
 * Le marqueur de version, lisible partout sans le chargeur de Composer.
 * `VERSION` suit le tag ; `FACT_SCHEMA` augmente à tout changement de forme
 * d'un fait sérialisé.
 */
final class ContractsVersion
{
    public const string VERSION = '0.1.0';

    public const int FACT_SCHEMA = 1;
}
