<?php

use NFePHP\eSocial\Factories\EvtDeslig;

function desligPayload(array $mudancaCpf): stdClass
{
    return json_decode(json_encode([
        'indretif' => 1,
        'cpftrab' => '12345678901',
        'matricula' => 'MAT001',
        'mtvdeslig' => '02',
        'dtdeslig' => '2026-08-10',
        'indpagtoapi' => 'N',
        'pensalim' => 0,
        'mudancacpf' => $mudancaCpf,
    ]));
}

it('emite indCPFDesvinc depois de novoCPF em mudancaCPF', function () {
    $std = desligPayload(['novocpf' => '10987654321', 'indcpfdesvinc' => 9]);
    $event = new EvtDeslig($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');

    $document = eventDocument($event);

    $mudancaCpf = $document->getElementsByTagName('mudancaCPF')->item(0);
    expect(childElementNames($mudancaCpf))->toBe(['novoCPF', 'indCPFDesvinc'])
        ->and($mudancaCpf->lastElementChild->textContent)->toBe('9');
});

it('não emite indCPFDesvinc quando ele não é informado', function () {
    $std = desligPayload(['novocpf' => '10987654321']);
    $event = new EvtDeslig($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');

    $document = eventDocument($event);

    expect(childElementNames($document->getElementsByTagName('mudancaCPF')->item(0)))->toBe(['novoCPF']);
});

it('recusa indCPFDesvinc diferente de 9', function () {
    $std = desligPayload(['novocpf' => '10987654321', 'indcpfdesvinc' => 1]);

    new EvtDeslig($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
})->throws(Exception::class, 'mudancacpf.indcpfdesvinc');
