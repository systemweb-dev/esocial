<?php

use NFePHP\eSocial\Common\Standardize;

/*
 * Respostas do webservice como chegam no corpo SOAP: o Standardize localiza o nó raiz do eSocial
 * dentro do envelope e o converte em stdClass, array ou JSON.
 */

function soapResponse(string $esocialXml): string
{
    return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body>'
        . '<EnviarLoteEventosResponse xmlns="http://www.esocial.gov.br/servicos/empregador/lote/eventos/envio/v1_1_0">'
        . '<EnviarLoteEventosResult>' . $esocialXml . '</EnviarLoteEventosResult>'
        . '</EnviarLoteEventosResponse></s:Body></s:Envelope>';
}

function retornoEnvioLote(): string
{
    return soapResponse(
        '<eSocial xmlns="http://www.esocial.gov.br/schema/lote/eventos/envio/retornoEnvio/v1_1_0">'
        . '<retornoEnvioLoteEventos>'
        . '<ideEmpregador><tpInsc>1</tpInsc><nrInsc>99999999</nrInsc></ideEmpregador>'
        . '<status><cdResposta>201</cdResposta><descResposta>Lote Recebido com Sucesso.</descResposta></status>'
        . '<dadosRecepcaoLote><dhRecepcao>2017-08-03T10:37:00</dhRecepcao>'
        . '<protocoloEnvio>1.2.201708.0000000000000012345</protocoloEnvio></dadosRecepcaoLote>'
        . '</retornoEnvioLoteEventos></eSocial>'
    );
}

it('identifica o tipo de retorno dentro do envelope SOAP', function () {
    $xml = retornoEnvioLote();

    $type = (new Standardize())->whichIs($xml);

    expect($type)->toBe('retornoEnvioLoteEventos');
});

it('converte o retorno do envio de lote em stdClass', function () {
    $xml = retornoEnvioLote();

    $std = (new Standardize())->toStd($xml);

    expect($std->status->cdResposta)->toBe('201')
        ->and($std->dadosRecepcaoLote->protocoloEnvio)->toBe('1.2.201708.0000000000000012345');
});

it('converte o retorno do envio de lote em array', function () {
    $xml = retornoEnvioLote();

    $array = (new Standardize())->toArray($xml);

    expect($array['status']['descResposta'])->toBe('Lote Recebido com Sucesso.');
});

it('converte o retorno do envio de lote em JSON', function () {
    $xml = retornoEnvioLote();

    $json = (new Standardize())->toJson($xml);

    expect(json_decode($json, true)['dadosRecepcaoLote']['protocoloEnvio'])
        ->toBe('1.2.201708.0000000000000012345');
});

it('expõe os atributos XML na chave attributes', function () {
    $xml = soapResponse(
        '<eSocial xmlns="http://www.esocial.gov.br/schema/lote/eventos/envio/retornoProcessamento/v1_3_0">'
        . '<retornoProcessamentoLoteEventos>'
        . '<status><cdResposta>201</cdResposta></status>'
        . '<retornoEventos><evento Id="ID1999999990000002017080310370000001"><retornoEvento/></evento></retornoEventos>'
        . '</retornoProcessamentoLoteEventos></eSocial>'
    );

    $array = (new Standardize())->toArray($xml);

    expect($array['retornoEventos']['evento']['attributes']['Id'])->toBe('ID1999999990000002017080310370000001')
        ->and($array['retornoEventos']['evento'])->not->toHaveKey('@attributes');
});

it('recusa conteúdo que não é XML', function () {
    $standardize = new Standardize();

    $standardize->toStd('isto não é xml');
})->throws(InvalidArgumentException::class, 'O argumento passado não é um XML válido.');

it('recusa XML que não é do eSocial', function () {
    $standardize = new Standardize();

    $standardize->toStd('<nfeProc><NFe/></nfeProc>');
})->throws(InvalidArgumentException::class, 'Este xml não pertence ao projeto eSocial.');
