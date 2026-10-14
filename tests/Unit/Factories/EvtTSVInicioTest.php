<?php

use NFePHP\eSocial\Factories\EvtTSVInicio;

function tsvInicioPayload(array $institution, $nivEstagio = null): stdClass
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
        'cpftrab' => '00232133417',
        'nmtrab' => 'Trabalhador Teste',
        'sexo' => 'M',
        'racacor' => 2,
        'grauinstr' => '08',
        'dtnascto' => '2000-01-01',
        'paisnascto' => '105',
        'paisnac' => '105',
        'endereco' => ['brasil' => [
            'dsclograd' => 'Rua Teste',
            'nrlograd' => '1',
            'cep' => '01311200',
            'codmunic' => '3550308',
            'uf' => 'SP',
        ]],
        'cadini' => 'N',
        'matricula' => 'TESTE906',
        'codcateg' => '906',
        'dtinicio' => '2026-08-01',
        'infoestagiario' => $estagiario,
    ]));
}

it('omite nivEstagio e emite só o CNPJ da instituição brasileira', function () {
    $std = tsvInicioPayload(['cnpjinstensino' => '12345678000195']);
    $event = new EvtTSVInicio($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('nivEstagio')->length)->toBe(0)
        ->and(childElementNames($document->getElementsByTagName('instEnsino')->item(0)))->toBe(['cnpjInstEnsino'])
        ->and($document->getElementsByTagName('cnpjInstEnsino')->item(0)->textContent)->toBe('12345678000195');
});

it('omite nivEstagio e emite só a razão social da instituição estrangeira', function () {
    $std = tsvInicioPayload(['nmrazao' => 'Foreign University']);
    $event = new EvtTSVInicio($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('nivEstagio')->length)->toBe(0)
        ->and(childElementNames($document->getElementsByTagName('instEnsino')->item(0)))->toBe(['nmRazao'])
        ->and($document->getElementsByTagName('nmRazao')->item(0)->textContent)->toBe('Foreign University');
});

it('emite nivEstagio quando ele é informado', function () {
    $std = tsvInicioPayload(['nmrazao' => 'Foreign University'], 4);
    $event = new EvtTSVInicio($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('nivEstagio')->item(0)->textContent)->toBe('4');
});

it('recusa nivEstagio fora da faixa de 1 a 9', function (int $nivEstagio) {
    $std = tsvInicioPayload(['cnpjinstensino' => '12345678000195'], $nivEstagio);

    new EvtTSVInicio($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
})->with(['abaixo do mínimo' => 0, 'acima do máximo' => 10])
    ->throws(Exception::class, 'infoestagiario.nivestagio');
