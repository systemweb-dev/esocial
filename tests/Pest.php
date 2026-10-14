<?php

use NFePHP\eSocial\Common\FactoryInterface;
use NFePHP\eSocial\Tests\ESocialTestCase;

/*
 * Os testes ficam em tests/Unit, em sintaxe Pest, e herdam o ESocialTestCase:
 * $this->configJson (config S.1.3.0 / serviço 1.5.0) e $this->certificate (certificado de teste).
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
