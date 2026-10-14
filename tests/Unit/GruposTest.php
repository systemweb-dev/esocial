<?php

use NFePHP\Common\Exception\ValidatorException;
use NFePHP\eSocial\Event;
use NFePHP\eSocial\Tools;

/*
 * Grupos do lote (MOD 7.5.7 e MOS S-1.3, seção 10): 1 = tabelas, 2 = não periódicos,
 * 3 = periódicos. Os XML abaixo são mínimos: o lote só lê o nome e o Id do evento e não
 * revalida o conteúdo do evento (xs:any processContents="skip").
 */

function minimalEventXml(string $eventName): string
{
    return "<eSocial><$eventName Id=\"ID1999999990000002017080310370000001\"/></eSocial>";
}

function acceptedGroups(Tools $tools, string $eventName): array
{
    $groups = [];
    foreach ([1, 2, 3] as $group) {
        try {
            $tools->enviarLoteXmlEventos($group, [minimalEventXml($eventName)]);
            $groups[] = $group;
        } catch (ValidatorException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            // recusa por grupo: o evento não é deste grupo
        }
    }

    return $groups;
}

dataset('eventos enviados pelo empregador', function () {
    foreach (Event::$codes as $eventName => $code) {
        if (!in_array($code, ['S-8200', 'S-8299'], true)) {
            yield "$code $eventName" => [$eventName, $code];
        }
    }
});

it('aceita cada evento do empregador em exatamente um grupo', function (string $eventName, string $code) {
    $tools = toolsWithFakeSoap($this->configJson, $this->certificate);

    $groups = acceptedGroups($tools, $eventName);

    $expected = in_array($code, ['S-1000', 'S-1005', 'S-1010', 'S-1020', 'S-1070'], true) ? [1]
        : (preg_match('/^S-(12|13)\d\d$/', $code) ? [3] : [2]);
    expect($groups)->toBe($expected);
})->with('eventos enviados pelo empregador');

it('aceita o grupo informado como texto', function () {
    $tools = toolsWithFakeSoap($this->configJson, $this->certificate);
    $xml = infoEmpregadorEvent($this->configJson, $this->certificate)->toXML();

    $request = fakeSoapRequest($tools->enviarLoteXmlEventos('1', [$xml]));

    expect($request['body'])->toContain('<envioLoteEventos grupo="1">');
});

it('recusa grupo que não existe', function ($group) {
    $tools = toolsWithFakeSoap($this->configJson, $this->certificate);
    $event = infoEmpregadorEvent($this->configJson, $this->certificate);

    $tools->enviarLoteEventos($group, [$event]);
})->with([0, 4, 'x'])->throws(InvalidArgumentException::class, 'use 1 (tabelas), 2 (não periódicos) ou 3 (periódicos)');

it('recusa em lote o evento que o empregador não envia', function () {
    $tools = toolsWithFakeSoap($this->configJson, $this->certificate);
    $xml = minimalEventXml('evtAnotJud');

    $tools->enviarLoteXmlEventos(2, [$xml]);
})->throws(RuntimeException::class, 'O evento S-8200 não pode ser enviado em lote pelo empregador.');

it('explica quando o grupo declarado difere do grupo do evento', function () {
    $tools = toolsWithFakeSoap($this->configJson, $this->certificate);
    $xml = infoEmpregadorEvent($this->configJson, $this->certificate)->toXML();

    $tools->enviarLoteXmlEventos(2, [$xml]);
})->throws(RuntimeException::class, 'não pode diferir do grupo dos eventos');
