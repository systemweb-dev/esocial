<?php

use NFePHP\eSocial\Factories\EvtFechaEvPer;

function fechaEvPerPayload(array $infoFech): stdClass
{
    return json_decode(json_encode([
        'indapuracao' => 1,
        'perapur' => '2026-09',
        'infofech' => array_merge([
            'evtremun' => 'N',
            'evtpgtos' => 'N',
            'evtcontratavnp' => 'N',
            'evtinfocomplper' => 'N',
            'transdctfweb' => 'S',
        ], $infoFech),
    ]));
}

/*
 * XSD (infoFech): evtComProd é obrigatório (S ou N). O montador o lia, mas o JSON schema não o
 * declarava: sem ele o erro só aparecia na validação do XML.
 */
it('emite evtComProd informado', function () {
    $std = fechaEvPerPayload(['evtcomprod' => 'S']);
    $event = new EvtFechaEvPer($this->configJson, $std, $this->certificate, '2026-10-16 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('evtComProd')->item(0)->textContent)->toBe('S');
});

it('recusa o S-1299 sem evtComProd', function () {
    $std = fechaEvPerPayload([]);

    new EvtFechaEvPer($this->configJson, $std, $this->certificate, '2026-10-16 10:00:00');
})->throws(Exception::class, 'infofech.evtcomprod');
