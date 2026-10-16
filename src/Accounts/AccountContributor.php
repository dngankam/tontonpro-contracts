<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Accounts;

/**
 * Un module qui contribue au plan de comptes.
 */
interface AccountContributor
{
    /**
     * @return iterable<AccountContribution>
     */
    public function accountContributions(): iterable;
}
