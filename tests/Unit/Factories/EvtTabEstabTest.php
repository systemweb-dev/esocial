<?php

use NFePHP\eSocial\Factories\EvtTabEstab;

/*
 * O XSD do S-1005 usa o mesmo T_ideEstab na inclusão e na alteração: fimValid é opcional nos dois
 * (evtTabEstab.xsd, ideEstab de inclusao). Os outros eventos de tabela já emitem fimValid na inclusão.
 */
it('emite fimValid em ideEstab na inclusão', function () {
    $std = json_decode(json_encode([
        'sequencial' => 1,
        'tpinsc' => 1,
        'nrinsc' => '12345678901234',
        'inivalid' => '2017-01',
        'fimvalid' => '2017-12',
        'modo' => 'INC',
        'dadosestab' => ['cnaeprep' => '1234567', 'aliqgilrat' => ['aliqrat' => 1]],
    ]));
    $event = new EvtTabEstab($this->configJson, $std, $this->certificate, '2017-08-03 10:37:00');

    $document = eventDocument($event);

    $ideEstab = $document->getElementsByTagName('inclusao')->item(0)->getElementsByTagName('ideEstab')->item(0);
    expect(childElementNames($ideEstab))->toBe(['tpInsc', 'nrInsc', 'iniValid', 'fimValid'])
        ->and($ideEstab->getElementsByTagName('fimValid')->item(0)->textContent)->toBe('2017-12');
});
