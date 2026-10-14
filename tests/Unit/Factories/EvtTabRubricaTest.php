<?php

use NFePHP\eSocial\Factories\EvtTabRubrica;

function tabRubricaComCodIncCP(string $codIncCP): stdClass
{
    return json_decode(json_encode([
        'sequencial' => 1,
        'codrubr' => 'R001',
        'idetabrubr' => 'TAB1',
        'inivalid' => '2026-12',
        'modo' => 'INC',
        'dadosrubrica' => [
            'dscrubr' => 'Salário-paternidade',
            'natrubr' => 1409,
            'tprubr' => 1,
            'codinccp' => $codIncCP,
            'codincirrf' => '11',
            'codincfgts' => '11',
            'tetoremun' => 'N',
        ],
    ]));
}

it('aceita os códigos de salário-paternidade em codIncCP (pacote 2026-12-14)', function (string $codIncCP) {
    $std = tabRubricaComCodIncCP($codIncCP);
    $event = new EvtTabRubrica($this->configJson, $std, $this->certificate, '2026-12-14 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('codIncCP')->item(0)->textContent)->toBe($codIncCP);
})->with(['23', '24', '27', '28']);
