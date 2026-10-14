<?php

use NFePHP\eSocial\Common\FactoryId;

it('monta o Id com tipo, inscrição completada até 14, data e sequencial de 5 dígitos', function () {
    $id = FactoryId::build(1, '99999999', new DateTime('2017-08-03 10:37:00'), 7);

    expect($id)->toBe('ID1' . '99999999000000' . '20170803103700' . '00007');
});

it('completa o CPF à direita até 14 posições', function () {
    $id = FactoryId::build(2, '12345678901', new DateTime('2017-08-03 10:37:00'), 1);

    expect(substr($id, 3, 14))->toBe('12345678901000');
});

it('gera um sequencial de 5 dígitos quando ele não é informado', function () {
    $id = FactoryId::build(1, '99999999', new DateTime('2017-08-03 10:37:00'));

    expect($id)->toMatch('/^ID199999999000000' . '20170803103700' . '\d{5}$/');
});
