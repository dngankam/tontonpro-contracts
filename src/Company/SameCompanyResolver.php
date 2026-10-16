<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Company;

use TontonPro\Contracts\Value\Establishment;

/**
 * « Ces deux établissements appartiennent-ils à la même société ? »
 *
 * Le module émetteur lie cette interface à `NoAccountingResolver` par défaut ;
 * la comptabilité, une fois installée, remplace la liaison.
 */
interface SameCompanyResolver
{
    public function sameCompany(Establishment $a, Establishment $b): SameCompanyAnswer;
}
