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

/**
 * Executa o Act e devolve a exceção lançada (ou null). Para quando a mensagem esperada depende
 * do dataset e não cabe em ->throws().
 */
function caughtException(callable $act): ?Throwable
{
    try {
        $act();
    } catch (Throwable $e) {
        return $e;
    }

    return null;
}

/**
 * O stdClass do exemplo: o trecho entre "$std = new" e o "try" que monta o evento (só atribuições).
 */
function exampleInput(string $file): stdClass
{
    $source = file_get_contents($file);
    $start = strpos($source, '$std = new');
    $end = strpos($source, "\ntry", $start);
    $std = null;
    eval(substr($source, $start, $end - $start));

    return $std;
}

/**
 * Caminho do exemplo do evento (ex.: 's2200' → examples/Fake/v_S_01_03_00/Fake_s2200_EvtAdmissao.php).
 */
function exampleFile(string $code): string
{
    return current(glob(dirname(__DIR__) . "/examples/Fake/v_S_01_03_00/Fake_{$code}_*.php"));
}

/**
 * Cópia do stdClass sem a chave do caminho (ex.: 'dependente.tpdep'), tirada de cada item dos arrays.
 */
function withoutField(stdClass $std, string $path): stdClass
{
    $copy = unserialize(serialize($std));
    $remove = function ($node, array $keys) use (&$remove) {
        if (is_array($node)) {
            foreach ($node as $item) {
                $remove($item, $keys);
            }
            return;
        }
        $key = array_shift($keys);
        if (!is_object($node) || !property_exists($node, $key)) {
            return;
        }
        if ($keys === []) {
            unset($node->$key);
            return;
        }
        $remove($node->$key, $keys);
    };
    $remove($copy, explode('.', $path));

    return $copy;
}
