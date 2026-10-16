<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Company;

/**
 * La réponse à « ces deux établissements appartiennent-ils à la même société ? ».
 */
enum SameCompanyAnswer: string
{
    /** La comptabilité répond oui. */
    case Same = 'same';

    /** La comptabilité répond non : le report est refusé. */
    case Different = 'different';

    /** Comptabilité installée, mais un établissement n'est pas rattaché : le report est refusé. */
    case Unresolved = 'unresolved';

    /** Aucune comptabilité installée : personne ne sait, et rien ne change. */
    case NoAuthority = 'no_authority';

    public function permitsRoomCharge(): bool
    {
        return match ($this) {
            self::Same, self::NoAuthority => true,
            self::Different, self::Unresolved => false,
        };
    }
}
