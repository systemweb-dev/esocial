<?php

use NFePHP\eSocial\Common\FactoryInterface;
use NFePHP\eSocial\Tests\ESocialTestCase;

/*
 * Os testes ficam em tests/Unit, em sintaxe Pest, e herdam o ESocialTestCase:
 * $this->configJson (config S.1.3.0 / serviço 1.6.0) e $this->certificate (certificado de teste).
 *
 * Convenção: AAA estrito — Arrange, Act e Assert separados por linha em branco, um único Act,
 * nenhuma asserção em helper. Exceção esperada vai em ->throws() no próprio teste.
 */
pest()->extend(ESocialTestCase::class)->in('Unit');

/**
 * Gera o XML do evento e devolve o documento. Com certificado, toXML() assina e valida no XSD
 * do leiaute: XML inválido lança exceção aqui mesmo.
 */
function eventDocument(FactoryInterface $event): DOMDocument
{
    $document = new DOMDocument();
    $document->loadXML($event->toXML());

    return $document;
}

/**
 * Nomes dos elementos filhos diretos, na ordem em que aparecem.
 */
function childElementNames(DOMElement $element): array
{
    $names = [];
    foreach ($element->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $names[] = $child->localName;
        }
    }

    return $names;
}

/**
 * Tools com SoapFake: nada vai para a rede; a resposta é o JSON da requisição que seria enviada.
 */
function toolsWithFakeSoap(string $configJson, $certificate): \NFePHP\eSocial\Tools
{
    $tools = new \NFePHP\eSocial\Tools($configJson, $certificate);
    $tools->loadSoapClass(new \NFePHP\eSocial\Common\Soap\SoapFake());

    return $tools;
}

function fakeSoapRequest(string $response): array
{
    return json_decode($response, true);
}

/**
 * S-1000 mínimo (inclusão, sequencial 1) com data fixa.
 */
function infoEmpregadorEvent(string $configJson, $certificate = null)
{
    $std = json_decode(json_encode([
        'sequencial' => 1,
        'modo' => 'INC',
        'ideperiodo' => ['inivalid' => '2017-01'],
        'infocadastro' => [
            'classtrib' => '01',
            'indcoop' => 0,
            'indconstr' => 0,
            'inddesfolha' => 0,
            'indporte' => 'S',
            'indoptregeletron' => 1,
        ],
    ]));

    return \NFePHP\eSocial\Event::evtInfoEmpregador($configJson, $std, $certificate, '2017-08-03 10:37:00');
}

/**
 * Copia o config JSON mudando chaves de primeiro e segundo nível (ex.: ['empregador' => ['nrInsc' => 'X']]).
 */
function configWith(string $configJson, array $changes): string
{
    return json_encode(array_replace_recursive(json_decode($configJson, true), $changes));
}
