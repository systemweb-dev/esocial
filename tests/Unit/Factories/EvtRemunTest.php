<?php

use NFePHP\eSocial\Factories\EvtRemun;

/*
 * No JSON schema e no XSD, observacao fica dentro de infoComplem/sucessaoVinc. O montador lê
 * infocomplem->observacao, então a observação informada no lugar certo nunca chega ao XML.
 */
it('emite a observação da sucessão de vínculo em sucessaoVinc', function () {
    $std = json_decode(json_encode([
        'sequencial' => 1,
        'indretif' => 1,
        'indapuracao' => 1,
        'perapur' => '2017-12',
        'cpftrab' => '12345678901',
        'infocomplem' => [
            'nmtrab' => 'Fulano de Tal',
            'dtnascto' => '1985-02-14',
            'sucessaovinc' => [
                'tpinsc' => 1,
                'nrinsc' => '12345678901234',
                'dtadm' => '2017-06-07',
                'observacao' => 'Sucessão por incorporação',
            ],
        ],
        'dmdev' => [[
            'idedmdev' => 'DM1',
            'codcateg' => 101,
            'infoperapur' => ['ideestablot' => [[
                'tpinsc' => 1,
                'nrinsc' => '12345678901234',
                'codlotacao' => 'L1',
                'remunperapur' => [['itensremun' => [['codrubr' => 'R1', 'idetabrubr' => 'T1', 'vrrubr' => 100]]]],
            ]]],
        ]],
    ]));
    $event = new EvtRemun($this->configJson, $std, $this->certificate, '2017-08-03 10:37:00');

    $document = eventDocument($event);

    $sucessaoVinc = $document->getElementsByTagName('sucessaoVinc')->item(0);
    expect($sucessaoVinc->getElementsByTagName('observacao')->length)->toBe(1)
        ->and($sucessaoVinc->getElementsByTagName('observacao')->item(0)->textContent)->toBe('Sucessão por incorporação');
});
