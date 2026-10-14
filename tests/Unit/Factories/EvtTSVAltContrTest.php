<?php

use NFePHP\eSocial\Factories\EvtTSVAltContr;

function tsvAltContrPayload(array $institution, $nivEstagio = null): stdClass
{
    $estagiario = [
        'natestagio' => 'N',
        'dtprevterm' => '2026-12-31',
        'instensino' => $institution,
    ];
    if ($nivEstagio !== null) {
        $estagiario['nivestagio'] = $nivEstagio;
    }

    return json_decode(json_encode([
        'indretif' => 1,
        'trabsemvinculo' => [
            'cpftrab' => '00232133417',
            'matricula' => 'TESTE906',
            'codcateg' => '906',
        ],
        'tsvalteracao' => ['dtalteracao' => '2026-08-01'],
        'estagiario' => $estagiario,
    ]));
}

it('omite nivEstagio e emite só o CNPJ da instituição brasileira', function () {
    $std = tsvAltContrPayload(['cnpjinstensino' => '12345678000195']);
    $event = new EvtTSVAltContr($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('nivEstagio')->length)->toBe(0)
        ->and(childElementNames($document->getElementsByTagName('instEnsino')->item(0)))->toBe(['cnpjInstEnsino'])
        ->and($document->getElementsByTagName('cnpjInstEnsino')->item(0)->textContent)->toBe('12345678000195');
});

it('omite nivEstagio e emite só a razão social da instituição estrangeira', function () {
    $std = tsvAltContrPayload(['nmrazao' => 'Foreign University']);
    $event = new EvtTSVAltContr($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('nivEstagio')->length)->toBe(0)
        ->and(childElementNames($document->getElementsByTagName('instEnsino')->item(0)))->toBe(['nmRazao'])
        ->and($document->getElementsByTagName('nmRazao')->item(0)->textContent)->toBe('Foreign University');
});

it('emite nivEstagio quando ele é informado', function () {
    $std = tsvAltContrPayload(['nmrazao' => 'Foreign University'], 4);
    $event = new EvtTSVAltContr($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('nivEstagio')->item(0)->textContent)->toBe('4');
});

it('recusa nivEstagio fora da faixa de 1 a 9', function (int $nivEstagio) {
    $std = tsvAltContrPayload(['cnpjinstensino' => '12345678000195'], $nivEstagio);

    new EvtTSVAltContr($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
})->with(['abaixo do mínimo' => 0, 'acima do máximo' => 10])
    ->throws(Exception::class, 'estagiario.nivestagio');
