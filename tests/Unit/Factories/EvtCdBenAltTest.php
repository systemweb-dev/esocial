<?php

use NFePHP\eSocial\Factories\EvtCdBenAlt;

function cdBenAltPayload(string $dscSuspensao): stdClass
{
    return json_decode(json_encode([
        'indretif' => 1,
        'cpfbenef' => '12345678901',
        'nrbeneficio' => 'b1234',
        'dtaltbeneficio' => '2026-09-02',
        'tpbeneficio' => '0805',
        'tpplanrp' => 0,
        'indsuspensao' => 'S',
        'suspensao' => ['mtvsuspensao' => '01', 'dscsuspensao' => $dscSuspensao],
    ]));
}

/*
 * Regressão: o montador lia "dscssuspensao" e o JSON schema declarava "dscSuspensao" (a entrada vira
 * minúscula antes de validar), então a descrição nunca chegava ao XML nem era conferida.
 */
it('emite dscSuspensao informada', function () {
    $std = cdBenAltPayload('Suspensão por decisão judicial');
    $event = new EvtCdBenAlt($this->configJson, $std, $this->certificate, '2026-10-16 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('dscSuspensao')->item(0)->textContent)
        ->toBe('Suspensão por decisão judicial');
});

it('recusa dscSuspensao acima de 255 caracteres', function () {
    $std = cdBenAltPayload(str_repeat('a', 256));

    new EvtCdBenAlt($this->configJson, $std, $this->certificate, '2026-10-16 10:00:00');
})->throws(Exception::class, 'suspensao.dscsuspensao');
