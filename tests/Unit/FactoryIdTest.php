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

it('usa o próximo sequencial livre quando o Id já foi gerado no processo', function () {
    $date = new DateTime('2017-08-03 10:37:00');
    FactoryId::build(1, '99999999', $date, 1);

    $id = FactoryId::build(1, '99999999', $date, 1);

    expect($id)->toBe('ID1' . '99999999000000' . '20170803103700' . '00002');
});

it('mantém sequenciais diferentes do mesmo segundo', function () {
    $date = new DateTime('2017-08-03 10:37:00');
    FactoryId::build(1, '99999999', $date, 1);

    $id = FactoryId::build(1, '99999999', $date, 2);

    expect(substr($id, -5))->toBe('00002');
});

it('gera Ids diferentes sem sequencial no mesmo segundo', function () {
    $date = new DateTime('2017-08-03 10:37:00');
    $first = FactoryId::build(1, '99999999', $date);

    $second = FactoryId::build(1, '99999999', $date);

    expect($second)->not->toBe($first);
});

it('volta ao sequencial 00001 depois do 99999 ocupado', function () {
    $date = new DateTime('2017-08-03 10:37:00');
    FactoryId::build(1, '99999999', $date, 99999);

    $id = FactoryId::build(1, '99999999', $date, 99999);

    expect(substr($id, -5))->toBe('00001');
});

it('recusa quando os 99999 sequenciais do segundo já foram usados', function () {
    $date = new DateTime('2017-08-03 10:37:00');
    for ($sequential = 1; $sequential <= 99999; $sequential++) {
        FactoryId::build(1, '99999999', $date, $sequential);
    }

    FactoryId::build(1, '99999999', $date, 1);
})->throws(RuntimeException::class, 'sequenciais');

it('esquece os Ids gerados depois do reset', function () {
    $date = new DateTime('2017-08-03 10:37:00');
    FactoryId::build(1, '99999999', $date, 1);
    FactoryId::reset();

    $id = FactoryId::build(1, '99999999', $date, 1);

    expect(substr($id, -5))->toBe('00001');
});
