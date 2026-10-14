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

function infoEmpregadorCadastro(array $extra, array $without = []): stdClass
{
    $cadastro = array_merge([
        'classtrib' => '01',
        'indcoop' => 0,
        'indconstr' => 0,
        'inddesfolha' => 0,
        'indporte' => 'S',
        'indoptregeletron' => 1,
    ], $extra);
    foreach ($without as $key) {
        unset($cadastro[$key]);
    }

    return json_decode(json_encode([
        'sequencial' => 1,
        'modo' => 'INC',
        'ideperiodo' => ['inivalid' => '2026-01'],
        'infocadastro' => $cadastro,
    ]));
}

it('emite indPertIRRF logo depois de indTribFolhaPisPasep', function () {
    $std = infoEmpregadorCadastro(['indtribfolhapispasep' => 'S', 'indpertirrf' => 'S']);
    $event = new EvtInfoEmpregador($this->configJson, $std, $this->certificate, '2026-01-05 10:00:00');

    $document = eventDocument($event);

    $names = childElementNames($document->getElementsByTagName('infoCadastro')->item(0));
    expect(array_slice($names, -2))->toBe(['indTribFolhaPisPasep', 'indPertIRRF'])
        ->and($document->getElementsByTagName('indPertIRRF')->item(0)->textContent)->toBe('S');
});

it('não emite indPertIRRF quando ele não é informado', function () {
    $std = infoEmpregadorCadastro([]);
    $event = new EvtInfoEmpregador($this->configJson, $std, $this->certificate, '2026-01-05 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('indPertIRRF')->length)->toBe(0);
});

it('recusa indPertIRRF diferente de S', function () {
    $std = infoEmpregadorCadastro(['indpertirrf' => 'N']);

    new EvtInfoEmpregador($this->configJson, $std, $this->certificate, '2026-01-05 10:00:00');
})->throws(Exception::class, 'infocadastro.indpertirrf');

it('aceita o S-1000 sem indOptRegEletron e não emite a tag', function () {
    $std = infoEmpregadorCadastro([], ['indoptregeletron']);
    $event = new EvtInfoEmpregador($this->configJson, $std, $this->certificate, '2026-01-05 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('indOptRegEletron')->length)->toBe(0);
});
