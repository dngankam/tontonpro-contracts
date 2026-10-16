<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Company;

use TontonPro\Contracts\Value\Establishment;

/**
 * La réponse par défaut, sans comptabilité installée : personne ne sait,
 * donc aucun refus.
 */
final readonly class NoAccountingResolver implements SameCompanyResolver
{
    public function sameCompany(Establishment $a, Establishment $b): SameCompanyAnswer
    {
        return SameCompanyAnswer::NoAuthority;
    }
}
