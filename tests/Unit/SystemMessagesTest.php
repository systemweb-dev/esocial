<?php

use NFePHP\eSocial\Common\SystemMessages;

/*
 * Catálogo oficial das Mensagens do Sistema (data/mensagens-sistema.json). O retorno do lote traz
 * <ocorrencia><codigo>191</codigo>; a mensagem correspondente é MS0191.
 */

it('devolve o texto e as ações sugeridas de uma mensagem', function () {
    $codigo = 191;

    $message = SystemMessages::get($codigo);

    expect($message['codigo'])->toBe('MS0191')
        ->and($message['texto'])->toBe('Não é permitido o desligamento do trabalhador.')
        ->and($message['acoes'])->toHaveCount(2)
        ->and($message['variantes'])->toBe([]);
});

it('aceita o código como número, texto ou com o prefixo MS', function ($codigo) {
    $expected = SystemMessages::get(191);

    $message = SystemMessages::get($codigo);

    expect($message)->toBe($expected);
})->with(['191', '0191', 'MS0191', 'ms0191']);

it('devolve a mensagem-base com as variantes de sufixo do mesmo número', function () {
    $codigo = 266;

    $message = SystemMessages::get($codigo);

    expect($message['codigo'])->toBe('MS0266')
        ->and($message['texto'])->toBe("Rubrica '{0}' incompatível com Contribuinte Individual.")
        ->and(array_column($message['variantes'], 'codigo'))
        ->toBe(['MS0266_V01_00_00', 'MS0266_V01_02_00', 'MS0266_V02_05_00']);
});

it('devolve só a variante pedida pelo código completo', function () {
    $codigo = 'MS0266_V01_00_00';

    $message = SystemMessages::get($codigo);

    expect($message['codigo'])->toBe('MS0266_V01_00_00')
        ->and($message['texto'])->toBe("Rubrica '{0}' incompatível com Contribuinte Individual.")
        ->and($message)->not->toHaveKey('variantes');
});

it('devolve null para código que não existe', function ($codigo) {
    $message = SystemMessages::get($codigo);

    expect($message)->toBeNull();
})->with([9999, 'abc', 'MS0001_INEXISTENTE']);

it('informa a versão do catálogo', function () {
    $version = SystemMessages::version();

    expect($version)->toBe('2.5');
});
