<?php

use NFePHP\eSocial\Event;

/*
 * Os exemplos de examples/Fake/<leiaute>/Fake_s*.php são os payloads de referência de cada evento.
 * Cada exemplo monta o evento com o certificado de teste e imprime o XML; aqui o XML é conferido
 * contra o XSD oficial em schemes/<leiaute>.
 *
 * Os exemplos leem o certificado e o bootstrap por caminho relativo, a partir da pasta do S-1.3
 * (o único leiaute aceito pelo eSocial e pela biblioteca).
 */

function runEsocialExample(string $file): string
{
    $previousDir = getcwd();
    $previousLevel = error_reporting();
    $previousDisplay = ini_get('display_errors');
    chdir(dirname(__DIR__, 2) . '/examples/Fake/v_S_01_03_00');
    ob_start();
    try {
        (static function () use ($file) {
            include $file;
        })();
    } finally {
        $output = (string) ob_get_clean();
        chdir($previousDir);
        error_reporting($previousLevel);
        ini_set('display_errors', $previousDisplay);
    }

    return $output;
}

/**
 * Roda o exemplo e devolve o XML impresso como documento. Se o exemplo não gerou XML,
 * lança exceção com a saída dele (normalmente a mensagem de erro do montador).
 */
function exampleDocument(string $file): DOMDocument
{
    $output = runEsocialExample($file);
    $start = strpos($output, '<eSocial');
    if ($start === false) {
        throw new RuntimeException('o exemplo não gerou XML: ' . trim(strip_tags($output)));
    }
    $document = new DOMDocument();
    $document->loadXML(substr($output, $start));

    return $document;
}

function xsdErrors(DOMDocument $document, string $xsd): string
{
    $previous = libxml_use_internal_errors(true);
    libxml_clear_errors();
    $valid = $document->schemaValidate($xsd);
    $errors = array_map(function ($error) {
        return trim($error->message);
    }, libxml_get_errors());
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $valid ? '' : implode(' | ', array_slice($errors, 0, 3));
}

dataset('exemplos', function () {
    foreach (['S-1.3' => 'v_S_01_03_00'] as $label => $layout) {
        $files = glob(dirname(__DIR__, 2) . "/examples/Fake/$layout/Fake_s*.php");
        sort($files);
        foreach ($files as $file) {
            yield "$label " . basename($file, '.php') => [$file, $layout];
        }
    }
});

it('gera o evento do exemplo assinado e válido no XSD do leiaute', function (string $file, string $layout) {
    preg_match('/Fake_s(\d{4})_/', basename($file), $match);
    $expectedCode = 'S-' . $match[1];

    $document = exampleDocument($file);

    $eventName = $document->documentElement->firstElementChild->localName;
    expect(Event::$codes[$eventName] ?? null)->toBe($expectedCode)
        ->and($document->documentElement->namespaceURI)->toEndWith("/evt/$eventName/$layout")
        ->and($document->getElementsByTagName('Signature')->length)->toBe(1)
        ->and(xsdErrors($document, dirname(__DIR__, 2) . "/schemes/$layout/$eventName.xsd"))->toBe('');
})->with('exemplos');
