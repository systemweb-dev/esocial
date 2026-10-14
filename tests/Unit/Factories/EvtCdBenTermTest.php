<?php

use NFePHP\eSocial\Factories\EvtCdBenTerm;

function cdBenTermPayload(array $extra): stdClass
{
    return json_decode(json_encode(array_merge([
        'indretif' => 1,
        'cpfbenef' => '12345678901',
        'nrbeneficio' => 'b1234',
        'dttermbeneficio' => '2026-08-10',
        'mtvtermino' => '01',
    ], $extra)));
}

it('emite indCPFDesvinc depois de novoCPF em infoBenTermino', function () {
    $std = cdBenTermPayload(['novocpf' => '10987654321', 'indcpfdesvinc' => 9]);
    $event = new EvtCdBenTerm($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');

    $document = eventDocument($event);

    $infoBenTermino = $document->getElementsByTagName('infoBenTermino')->item(0);
    expect(array_slice(childElementNames($infoBenTermino), -2))->toBe(['novoCPF', 'indCPFDesvinc'])
        ->and($infoBenTermino->lastElementChild->textContent)->toBe('9');
});

it('não emite indCPFDesvinc quando ele não é informado', function () {
    $std = cdBenTermPayload(['novocpf' => '10987654321']);
    $event = new EvtCdBenTerm($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('novoCPF')->length)->toBe(1)
        ->and($document->getElementsByTagName('indCPFDesvinc')->length)->toBe(0);
});

it('recusa indCPFDesvinc diferente de 9', function () {
    $std = cdBenTermPayload(['novocpf' => '10987654321', 'indcpfdesvinc' => 1]);

    new EvtCdBenTerm($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
})->throws(Exception::class, '[indcpfdesvinc]');
