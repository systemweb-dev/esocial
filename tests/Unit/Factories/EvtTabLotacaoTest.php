<?php

use NFePHP\eSocial\Factories\EvtTabLotacao;

/*
 * dadosOpPort (aliqRat, fap) é obrigatório quando tpLotacao = 08 (evtTabLotacao.xsd, dadosLotacao).
 * O campo existe no JSON schema, mas o montador não o lê.
 */
it('emite dadosOpPort na lotação de operador portuário', function () {
    $std = json_decode(json_encode([
        'sequencial' => 1,
        'codlotacao' => 'L1',
        'inivalid' => '2017-01',
        'modo' => 'INC',
        'dadoslotacao' => [
            'tplotacao' => '08',
            'tpinsc' => 1,
            'nrinsc' => '12345678901234',
            'fpas' => '507',
            'codtercs' => '0064',
            'dadosopport' => ['aliqrat' => 3, 'fap' => 1.04],
        ],
    ]));
    $event = new EvtTabLotacao($this->configJson, $std, $this->certificate, '2017-08-03 10:37:00');

    $document = eventDocument($event);

    $dadosOpPort = $document->getElementsByTagName('dadosOpPort')->item(0);
    expect($dadosOpPort)->not->toBeNull()
        ->and(childElementNames($dadosOpPort))->toBe(['aliqRat', 'fap'])
        ->and($dadosOpPort->getElementsByTagName('aliqRat')->item(0)->textContent)->toBe('3')
        ->and((float) $dadosOpPort->getElementsByTagName('fap')->item(0)->textContent)->toBe(1.04);
});
