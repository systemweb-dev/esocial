<?php

use NFePHP\eSocial\Factories\EvtPgtos;

function pgtosComReembolso(string $indOrgReemb, ?string $cnpjOper = '12345678000195'): stdClass
{
    $reembolso = [
        'indorgreemb' => $indOrgReemb,
        'cnpjoper' => $cnpjOper,
        'detreembtit' => [['tpinsc' => '1', 'nrinsc' => '12345678000195', 'vlrreemb' => 100.0]],
    ];
    if ($cnpjOper === null) {
        unset($reembolso['cnpjoper']);
    }

    return json_decode(json_encode([
        'sequencial' => 1,
        'indretif' => 1,
        'perapur' => '2026-10',
        'cpfbenef' => '12345678901',
        'infopgto' => [[
            'dtpgto' => '2026-10-05',
            'tppgto' => 1,
            'perref' => '2026-09',
            'idedmdev' => 'DM1',
            'vrliq' => 1000.0,
        ]],
        'infoircomplem' => [['inforeembmed' => [$reembolso]]],
    ]));
}

/*
 * XSD (evtPgtos, infoReembMed/cnpjOper, minOccurs 0): "Informação obrigatória e exclusiva se
 * {indOrgReemb} = [1]". Com o reembolso pelo próprio empregador (2) o CNPJ da operadora não vai.
 */
it('aceita o reembolso pelo empregador sem cnpjOper e não emite a tag', function () {
    $std = pgtosComReembolso('2', null);
    $event = new EvtPgtos($this->configJson, $std, $this->certificate, '2026-10-14 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('cnpjOper')->length)->toBe(0);
});
