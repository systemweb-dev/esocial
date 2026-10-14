<?php

use NFePHP\eSocial\Common\XsdSeeker;

it('extrai a versão do nome do arquivo de schema', function () {
    expect(XsdSeeker::getVersion('RetornoEvento-v1_2_1.xsd'))->toBe('v1_2_1');
});

it('encontra o schema e a versão de cada operação no pacote de comunicação', function (string $operation, string $file) {
    preg_match('/-(v\d+_\d+_\d+)\./', $file, $match);

    $list = XsdSeeker::seek(dirname(__DIR__, 2) . '/schemes/comunicacao/v1_5_0/');

    expect($list[$operation]['name'])->toBe($file)
        ->and($list[$operation]['version'])->toBe($match[1]);
})->with([
    ['EnvioLoteEventos', 'EnvioLoteEventos-v1_1_1.xsd'],
    ['ConsultaLoteEventos', 'ConsultaLoteEventos-v1_0_0.xsd'],
    ['ConsultaIdentificadoresEventosEmpregador', 'ConsultaIdentificadoresEventosEmpregador-v1_0_0.xsd'],
    ['RetornoEvento', 'RetornoEvento-v1_2_1.xsd'],
    ['RetornoEnvioLoteEventos', 'RetornoEnvioLoteEventos-v1_1_0.xsd'],
    ['WsEnviarLoteEventos', 'WsEnviarLoteEventos-v1_1_0.wsdl'],
    ['WsSolicitarDownloadEventos', 'WsSolicitarDownloadEventos-v1_0_0.wsdl'],
]);

it('devolve todas as operações sem versão quando a pasta não existe', function () {
    XsdSeeker::seek(dirname(__DIR__, 2) . '/schemes/comunicacao/v1_5_0/');

    $list = XsdSeeker::seek('/caminho/que/nao/existe/');

    expect(array_unique(array_column($list, 'version')))->toBe([''])
        ->and(array_keys($list))->toBe(array_keys(XsdSeeker::$list));
});

it('não herda versões de uma pasta lida antes', function () {
    XsdSeeker::seek(dirname(__DIR__, 2) . '/schemes/comunicacao/v1_5_0/');

    $list = XsdSeeker::seek(dirname(__DIR__, 2) . '/schemes/comunicacao/v1_1_0/');

    expect($list['ConsultaIdentificadoresEventosEmpregador']['version'])->toBe('')
        ->and($list['EnvioLoteEventos']['name'])->toBe('EnvioLoteEventos-v1_1_0.xsd');
});
