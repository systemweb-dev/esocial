<?php

use NFePHP\eSocial\Event;
use NFePHP\eSocial\Exception\EventsException;

/*
 * Eventos que a lib não monta: retornos do eSocial (S-5xxx), extintos no S-1.x e os do
 * Judiciário. Chamar pelo nome ou pelo código responde com o motivo, e não com erro fatal.
 */

dataset('eventos indisponíveis', function () {
    foreach (Event::$unavailable as $code => $info) {
        list($name, $kind) = $info;
        $digits = 's' . str_replace('S-', '', $code);
        foreach (['nome' => $name, 'código' => $code, 'código sem hífen' => $digits] as $how => $call) {
            yield "$code por $how" => [$call, $code, $name, $kind];
        }
    }
});

it('responde com o motivo quando o evento não é montado pela biblioteca', function (string $call, string $code, string $name, string $kind) {
    $std = new stdClass();
    $reasons = [
        'retorno' => 'é um evento de retorno do eSocial: o empregador não envia; leia o totalizador no retorno do processamento do lote.',
        'extinto' => 'foi extinto e não existe no leiaute S-1.3.',
        'judiciario' => 'é enviado só pelo Poder Judiciário (procEmi 8): o empregador não envia.',
    ];

    $error = caughtException(function () use ($call, $std) {
        Event::$call('', $std);
    });

    expect($error)->toBeInstanceOf(EventsException::class)
        ->and($error->getMessage())->toBe("$code ($name) {$reasons[$kind]}")
        ->and($error->getCode())->toBe(1003);
})->with('eventos indisponíveis');

it('lista os 24 eventos que a biblioteca não monta', function () {
    $codes = array_keys(Event::$unavailable);

    sort($codes);

    expect($codes)->toBe([
        'S-1030', 'S-1035', 'S-1040', 'S-1050', 'S-1060', 'S-1065', 'S-1080', 'S-1250',
        'S-1295', 'S-1300', 'S-2241', 'S-2245', 'S-2250', 'S-2260', 'S-5001', 'S-5002',
        'S-5003', 'S-5011', 'S-5012', 'S-5013', 'S-5501', 'S-5503', 'S-8200', 'S-8299',
    ]);
});
