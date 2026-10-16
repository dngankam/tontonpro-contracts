<?php

declare(strict_types=1);

use TontonPro\Contracts\Company\NoAccountingResolver;
use TontonPro\Contracts\Company\SameCompanyAnswer;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Tests\Support\Facts;
use TontonPro\Contracts\Tests\Support\Values;

it('sans comptabilité installée, personne ne sait', function () {
    expect((new NoAccountingResolver())->sameCompany(Values::outlet(), Values::hotel()))->toBe(SameCompanyAnswer::NoAuthority);
});

it('ne permet le report que dans la même société ou sans comptabilité', function (SameCompanyAnswer $answer, bool $permitted) {
    expect($answer->permitsRoomCharge())->toBe($permitted);
})->with([
    [SameCompanyAnswer::Same, true],
    [SameCompanyAnswer::NoAuthority, true],
    [SameCompanyAnswer::Different, false],
    [SameCompanyAnswer::Unresolved, false],
]);

it('un fait de report refuse « different »', function () {
    expect(violation(fn () => Facts::saleChargedToRoom(SameCompanyAnswer::Different)))->toBe(ViolationCode::RoomChargeAcrossCompanies)
        ->and(violation(fn () => Facts::saleChargedToRoom((new NoAccountingResolver())->sameCompany(Values::outlet(), Values::hotel()))))->toBeNull();
});
