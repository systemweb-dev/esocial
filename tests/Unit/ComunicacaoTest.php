<?php

use NFePHP\Common\Exception\ValidatorException;
use NFePHP\eSocial\Common\XsdSeeker;

/*
 * Pacote de Comunicação 1.6-Alfa (schemes/comunicacao/v1_6_0): aceita CNPJ alfanumérico na
 * identificação do empregador; o 1.5.0 só aceita dígitos. O lote é validado localmente no XSD
 * de envio antes de ir para o webservice.
 */

it('envia lote de empregador com CNPJ alfanumérico no serviço 1.6.0', function () {
    $config = configWith($this->configJson, ['serviceVersion' => '1.6.0', 'empregador' => ['nrInsc' => 'A1B2C3D4']]);
    $tools = toolsWithFakeSoap($config, $this->certificate);
    $event = infoEmpregadorEvent($config, $this->certificate);

    $request = fakeSoapRequest($tools->enviarLoteEventos(1, [$event]));

    expect($request['body'])->toContain('<envioLoteEventos grupo="1"><ideEmpregador><tpInsc>1</tpInsc><nrInsc>A1B2C3D4</nrInsc></ideEmpregador>');
});

it('recusa lote de empregador com CNPJ alfanumérico no serviço 1.5.0', function () {
    $config = configWith($this->configJson, ['serviceVersion' => '1.5.0', 'empregador' => ['nrInsc' => 'A1B2C3D4']]);
    $tools = toolsWithFakeSoap($config, $this->certificate);
    $event = infoEmpregadorEvent($config, $this->certificate);

    $tools->enviarLoteEventos(1, [$event]);
})->throws(ValidatorException::class, "The value 'A1B2C3D4' is not accepted by the pattern '\\d{8,15}'");

it('envia lote de empregador com CNPJ numérico no serviço 1.6.0', function () {
    $config = configWith($this->configJson, ['serviceVersion' => '1.6.0']);
    $tools = toolsWithFakeSoap($config, $this->certificate);
    $event = infoEmpregadorEvent($config, $this->certificate);

    $request = fakeSoapRequest($tools->enviarLoteEventos(1, [$event]));

    expect($request['body'])->toContain('<envioLoteEventos grupo="1"><ideEmpregador><tpInsc>1</tpInsc><nrInsc>99999999</nrInsc></ideEmpregador>');
});

it('encontra os schemas do pacote 1.6-Alfa', function () {
    $path = dirname(__DIR__, 2) . '/schemes/comunicacao/v1_6_0/';

    $list = XsdSeeker::seek($path);

    expect($list['EnvioLoteEventos']['name'])->toBe('EnvioLoteEventos-v1_1_1.xsd')
        ->and($list['RetornoEvento']['name'])->toBe('RetornoEvento-v1_3_0.xsd');
});
