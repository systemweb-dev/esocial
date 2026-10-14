<?php

use NFePHP\eSocial\Factories\EvtInfoEmpregador;

/*
 * Regressão: o montador lia $sh->fimValid (camelCase), mas Factory::propertiesToLower() põe todas
 * as chaves da entrada em minúsculo, então <fimValid> nunca era emitido em <novaValidade>.
 */
it('emite fimValid em novaValidade na alteração de vigência', function () {
    $std = json_decode(json_encode([
        'sequencial' => 1,
        'modo' => 'ALT',
        'ideperiodo' => ['inivalid' => '2020-01'],
        'novavalidade' => ['inivalid' => '2020-02', 'fimvalid' => '2020-01'],
    ]));
    $event = new EvtInfoEmpregador($this->configJson, $std, $this->certificate, '2020-02-01 10:00:00');

    $document = eventDocument($event);

    $novaValidade = $document->getElementsByTagName('novaValidade')->item(0);
    expect(childElementNames($novaValidade))->toBe(['iniValid', 'fimValid'])
        ->and($novaValidade->getElementsByTagName('fimValid')->item(0)->textContent)->toBe('2020-01');
});
