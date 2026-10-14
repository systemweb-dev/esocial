<?php

use NFePHP\eSocial\Factories\EvtContProc;

function contProcPayload(array $ideTrab): stdClass
{
    return json_decode(json_encode([
        'sequencial' => 1,
        'indretif' => 1,
        'nrproctrab' => '12345678901234567890',
        'perapurpgto' => '2023-10',
        'idetrab' => $ideTrab,
    ]));
}

/*
 * calcTrib (perRef, vrBcCpMensal, vrBcCp13 e infoCRContrib) está no JSON schema e no XSD
 * (evtContProc.xsd, ideTrab/calcTrib), mas o bloco do montador que deveria emiti-lo está vazio.
 */
it('emite calcTrib com infoCRContrib', function () {
    $std = contProcPayload([[
        'cpftrab' => '12345678901',
        'calctrib' => [[
            'perref' => '2023-10',
            'vrbccpmensal' => 2555.34,
            'vrbccp13' => 0,
            'infocrcontrib' => [['tpcr' => '113851', 'vrcr' => 325.87]],
        ]],
    ]]);
    $event = new EvtContProc($this->configJson, $std, $this->certificate, '2023-11-01 10:00:00');

    $document = eventDocument($event);

    $calcTrib = $document->getElementsByTagName('calcTrib')->item(0);
    expect($calcTrib)->not->toBeNull()
        ->and($calcTrib->getAttribute('perRef'))->toBe('2023-10')
        ->and((float) $calcTrib->getAttribute('vrBcCpMensal'))->toBe(2555.34)
        ->and($calcTrib->getElementsByTagName('infoCRContrib')->item(0)->getAttribute('tpCR'))->toBe('113851');
});

/*
 * ideTrab aceita várias ocorrências (maxOccurs="unbounded"), mas o montador faz o append fora do
 * loop: só o último trabalhador chega ao XML.
 */
it('emite um ideTrab para cada trabalhador informado', function () {
    $std = contProcPayload([['cpftrab' => '12345678901'], ['cpftrab' => '98765432100']]);
    $event = new EvtContProc($this->configJson, $std, $this->certificate, '2023-11-01 10:00:00');

    $document = eventDocument($event);

    $cpfs = [];
    foreach ($document->getElementsByTagName('ideTrab') as $ideTrab) {
        $cpfs[] = $ideTrab->getAttribute('cpfTrab');
    }
    expect($cpfs)->toBe(['12345678901', '98765432100']);
});

function contProcInfoRra(array $ideAdv): array
{
    return [
        'cpftrab' => '12345678901',
        'infocrirrf' => [[
            'tpcr' => '188951',
            'vrcr' => 326.91,
            'inforra' => [
                'descrra' => 'Diferenças salariais',
                'qtdmesesrra' => 4,
                'despprocjud' => ['vlrdespcustas' => 200, 'vlrdespadvogados' => 12000],
                'ideadv' => [$ideAdv],
            ],
        ]],
    ];
}

it('emite ideAdv em infoRRA com a inscrição do advogado', function () {
    $std = contProcPayload([contProcInfoRra(['tpinsc' => 1, 'nrinsc' => '12345678901234', 'vlradv' => 12000])]);
    $event = new EvtContProc($this->configJson, $std, $this->certificate, '2023-11-01 10:00:00');

    $document = eventDocument($event);

    $ideAdv = $document->getElementsByTagName('infoRRA')->item(0)->getElementsByTagName('ideAdv')->item(0);
    expect($ideAdv->getAttribute('tpInsc'))->toBe('1')
        ->and($ideAdv->getAttribute('nrInsc'))->toBe('12345678901234');
});

it('valida a inscrição do advogado em infoRRA', function () {
    $std = contProcPayload([contProcInfoRra(['tpinsc' => 3, 'nrinsc' => '12345678901234'])]);

    new EvtContProc($this->configJson, $std, $this->certificate, '2023-11-01 10:00:00');
})->throws(Exception::class, 'inforra.ideadv[0].tpinsc');

it('recusa código de receita de contribuição fora do formato de 6 dígitos', function () {
    $std = contProcPayload([[
        'cpftrab' => '12345678901',
        'calctrib' => [[
            'perref' => '2023-10',
            'vrbccpmensal' => 2555.34,
            'vrbccp13' => 0,
            'infocrcontrib' => [['tpcr' => '11385', 'vrcr' => 325.87]],
        ]],
    ]]);

    new EvtContProc($this->configJson, $std, $this->certificate, '2023-11-01 10:00:00');
})->throws(Exception::class, 'infocrcontrib[0].tpcr');
