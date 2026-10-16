<?php

use NFePHP\eSocial\Factories\EvtContratAvNP;

function contratAvNPPayload(array $extra = []): stdClass
{
    return json_decode(json_encode(array_merge([
        'indretif' => 1,
        'perapur' => '2026-09',
        'remunavnp' => [[
            'tpinsc' => '1',
            'nrinsc' => '11111111111111',
            'codlotacao' => 'LOT001',
            'vrbccp00' => 1500.11,
            'vrbccp15' => 0,
            'vrbccp20' => 0,
            'vrbccp25' => 0,
            'vrbccp13' => 0,
            'vrbcfgts' => 1500.11,
            'vrdesccp' => 165.01,
        ]],
    ], $extra)));
}

/*
 * XSD (T_ideEvento_folha_mensal): indGuia é minOccurs 0 e só existe para guia DAE. O montador o
 * tratava como obrigatório e, sem ele, gravava <indGuia/> vazio, que o XSD recusa.
 */
it('gera o S-1270 sem indGuia e não emite a tag', function () {
    $std = contratAvNPPayload();
    $event = new EvtContratAvNP($this->configJson, $std, $this->certificate, '2026-10-16 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('indGuia')->length)->toBe(0);
});

it('emite indGuia quando ele é informado', function () {
    $std = contratAvNPPayload(['indguia' => 1]);
    $event = new EvtContratAvNP($this->configJson, $std, $this->certificate, '2026-10-16 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('indGuia')->item(0)->textContent)->toBe('1');
});
