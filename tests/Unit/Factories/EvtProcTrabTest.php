<?php

use NFePHP\eSocial\Factories\EvtProcTrab;

function procTrabPayload(array $infoCompl = []): stdClass
{
    $contrato = [
        'tpcontr' => 6,
        'indcontr' => 'N',
        'indcateg' => 'N',
        'indnatativ' => 'N',
        'indmotdeslig' => 'N',
        'codcateg' => '101',
        'dtinicio' => '2022-11-05',
        'ideestab' => [
            'tpinsc' => 1,
            'nrinsc' => '12345678901234',
            'infovlr' => [
                'compini' => '2023-10',
                'compfim' => '2023-11',
                'indreperc' => 1,
                'indensd' => 'S',
                'indenabono' => 'S',
            ],
        ],
    ];
    if (!empty($infoCompl)) {
        $contrato['infocompl'] = $infoCompl;
    }

    return json_decode(json_encode([
        'indretif' => 1,
        'origem' => 1,
        'nrproctrab' => '12345678901234567890',
        'infoprocjud' => [
            'dtsent' => '2022-12-03',
            'ufvara' => 'SP',
            'codmunic' => '3504808',
            'idvara' => '12',
        ],
        'cpftrab' => '12345678901',
        'infocontr' => [$contrato],
    ]));
}

function procTrabInfoComplWithAllNewFields(): array
{
    return [
        'nmcargo' => 'Analista de Sistemas',
        'codcbo' => '212405',
        'nmfuncao' => 'Coordenador de Projetos',
        'cbofuncao' => '142105',
        'natatividade' => 1,
        'localtrabalho' => [
            'localtrabgeral' => [
                'tpinsc' => 1,
                'nrinsc' => '12345678000195',
                'desccomp' => 'Sede',
            ],
            'localtempdom' => [
                'dsclograd' => 'Rua Teste',
                'nrlograd' => '10',
                'cep' => '01311200',
                'codmunic' => '3550308',
                'uf' => 'SP',
            ],
        ],
    ];
}

function procTrabInfoComplWithRegime(int $tpRegTrab): array
{
    return [
        'infovinc' => [
            'tpregtrab' => $tpRegTrab,
            'tpregprev' => 1,
            'dtadm' => '2022-11-03',
            'infodeslig' => [
                'dtdeslig' => '2023-11-15',
                'mtvdeslig' => '01',
                'pensalim' => 0,
            ],
        ],
    ];
}

it('emite nmSoc em ideTrab', function () {
    $std = procTrabPayload(procTrabInfoComplWithAllNewFields());
    $std->nmsoc = 'Nome Social Teste';
    $event = new EvtProcTrab($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');

    $document = eventDocument($event);

    $ideTrab = $document->getElementsByTagName('ideTrab')->item(0);
    expect($ideTrab->getElementsByTagName('nmSoc')->item(0)->textContent)->toBe('Nome Social Teste');
});

it('emite os campos novos de infoCompl na ordem do XSD', function () {
    $std = procTrabPayload(procTrabInfoComplWithAllNewFields());
    $event = new EvtProcTrab($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');

    $document = eventDocument($event);

    $infoCompl = $document->getElementsByTagName('infoCompl')->item(0);
    expect(childElementNames($infoCompl))
        ->toBe(['nmCargo', 'codCBO', 'nmFuncao', 'CBOFuncao', 'natAtividade', 'localTrabalho'])
        ->and($infoCompl->getElementsByTagName('CBOFuncao')->item(0)->textContent)->toBe('142105');
});

it('emite localTrabalho com localTrabGeral e localTempDom, sem os opcionais não informados', function () {
    $std = procTrabPayload(procTrabInfoComplWithAllNewFields());
    $event = new EvtProcTrab($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');

    $document = eventDocument($event);

    $localTrabalho = $document->getElementsByTagName('localTrabalho')->item(0);
    $localTempDom = $localTrabalho->getElementsByTagName('localTempDom')->item(0);
    expect(childElementNames($localTrabalho))->toBe(['localTrabGeral', 'localTempDom'])
        ->and($localTempDom->getElementsByTagName('tpLograd')->length)->toBe(0)
        ->and($localTempDom->getElementsByTagName('codMunic')->item(0)->textContent)->toBe('3550308');
});

it('não emite os campos novos quando eles não são informados', function () {
    $std = procTrabPayload(['codcbo' => '212405']);
    $event = new EvtProcTrab($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');

    $document = eventDocument($event);

    $count = function (string $tag) use ($document) {
        return $document->getElementsByTagName($tag)->length;
    };
    expect(childElementNames($document->getElementsByTagName('infoCompl')->item(0)))->toBe(['codCBO'])
        ->and(array_map($count, ['nmSoc', 'nmCargo', 'nmFuncao', 'CBOFuncao', 'localTrabalho']))
        ->toBe([0, 0, 0, 0, 0]);
});

it('aceita tpRegTrab 3', function () {
    $std = procTrabPayload(procTrabInfoComplWithRegime(3));
    $event = new EvtProcTrab($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('tpRegTrab')->item(0)->textContent)->toBe('3');
});

it('recusa tpRegTrab acima de 3', function () {
    $std = procTrabPayload(procTrabInfoComplWithRegime(4));

    new EvtProcTrab($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
})->throws(Exception::class, 'infovinc.tpregtrab');

it('recusa tpInsc 2 no localTrabGeral', function () {
    $infoCompl = procTrabInfoComplWithAllNewFields();
    $infoCompl['localtrabalho']['localtrabgeral']['tpinsc'] = 2;
    $std = procTrabPayload($infoCompl);

    new EvtProcTrab($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
})->throws(Exception::class, 'localtrabgeral.tpinsc');
