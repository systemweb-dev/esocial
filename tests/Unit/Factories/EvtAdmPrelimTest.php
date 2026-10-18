<?php

use NFePHP\eSocial\Factories\EvtAdmPrelim;

function admPrelimData(): array
{
    return [
        'sequencial' => 1,
        'indretif' => 1,
        'cpftrab' => '00232133417',
        'dtnascto' => '1961-02-12',
        'dtadm' => '2017-04-12',
        'matricula' => '12345',
        'codcateg' => '101',
    ];
}

it('emite os dados do registro preliminar em infoRegPrelim, na ordem do XSD', function () {
    $std = json_decode(json_encode(admPrelimData()));
    $event = new EvtAdmPrelim($this->configJson, $std, $this->certificate, '2017-08-03 10:37:00');

    $document = eventDocument($event);

    $infoRegPrelim = $document->getElementsByTagName('infoRegPrelim')->item(0);
    expect(childElementNames($infoRegPrelim))->toBe(['cpfTrab', 'dtNascto', 'dtAdm', 'matricula', 'codCateg'])
        ->and($infoRegPrelim->textContent)->toBe('00232133417' . '1961-02-12' . '2017-04-12' . '12345' . '101');
});

it('identifica o evento com o empregador, a data e o sequencial informados', function () {
    $std = json_decode(json_encode(admPrelimData()));
    $event = new EvtAdmPrelim($this->configJson, $std, $this->certificate, '2017-08-03 10:37:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('evtAdmPrelim')->item(0)->getAttribute('Id'))
        ->toBe('ID1' . '99999999000000' . '20170803103700' . '00001');
});

it('recusa data de admissão anterior a 1900', function () {
    $data = admPrelimData();
    $data['dtadm'] = '1889-12-31';
    $std = json_decode(json_encode($data));

    new EvtAdmPrelim($this->configJson, $std, $this->certificate, '2017-08-03 10:37:00');
})->throws(Exception::class, '[dtadm] Não corresponde ao padrão');

it('emite dtTerm em infoRegCTPS quando informado', function () {
    $std = exampleInput(exampleFile('s2190'));
    $std->inforegctps->tpcontr = 2;
    $std->inforegctps->dtterm = '2027-04-11';
    $event = new EvtAdmPrelim($this->configJson, $std, $this->certificate, '2026-10-18 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('infoRegCTPS')->item(0)->lastElementChild->localName)->toBe('dtTerm')
        ->and($document->getElementsByTagName('dtTerm')->item(0)->textContent)->toBe('2027-04-11');
});
