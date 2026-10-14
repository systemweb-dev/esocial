<?php

use NFePHP\eSocial\Factories\EvtTSVTermino;

function tsvTerminoPayload(array $extra): stdClass
{
    return json_decode(json_encode(array_merge([
        'indretif' => 1,
        'cpftrab' => '12345678901',
        'matricula' => 'MAT906',
        'dtterm' => '2026-08-10',
    ], $extra)));
}

it('emite indCPFDesvinc depois de novoCPF em mudancaCPF', function () {
    $std = tsvTerminoPayload(['novocpf' => '10987654321', 'indcpfdesvinc' => 9]);
    $event = new EvtTSVTermino($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');

    $document = eventDocument($event);

    $mudancaCpf = $document->getElementsByTagName('mudancaCPF')->item(0);
    expect(childElementNames($mudancaCpf))->toBe(['novoCPF', 'indCPFDesvinc'])
        ->and($mudancaCpf->lastElementChild->textContent)->toBe('9');
});

it('não emite indCPFDesvinc quando ele não é informado', function () {
    $std = tsvTerminoPayload(['novocpf' => '10987654321']);
    $event = new EvtTSVTermino($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');

    $document = eventDocument($event);

    expect(childElementNames($document->getElementsByTagName('mudancaCPF')->item(0)))->toBe(['novoCPF']);
});

it('ignora indCPFDesvinc sem novoCPF', function () {
    $std = tsvTerminoPayload(['indcpfdesvinc' => 9]);
    $event = new EvtTSVTermino($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('mudancaCPF')->length)->toBe(0)
        ->and($document->getElementsByTagName('indCPFDesvinc')->length)->toBe(0);
});

it('recusa indCPFDesvinc diferente de 9', function () {
    $std = tsvTerminoPayload(['novocpf' => '10987654321', 'indcpfdesvinc' => 1]);

    new EvtTSVTermino($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
})->throws(Exception::class, '[indcpfdesvinc]');
