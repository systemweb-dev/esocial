<?php

use NFePHP\eSocial\Factories\EvtCAT;

function catPayload(string $dscCompLesao): stdClass
{
    return json_decode(json_encode([
        'indretif' => 1,
        'cpftrab' => '12345678901',
        'matricula' => 'MAT001',
        'dtacid' => '2026-09-10',
        'tpacid' => 1,
        'hracid' => '0930',
        'hrstrabantesacid' => '0200',
        'tpcat' => 1,
        'indcatobito' => 'N',
        'indcomunpolicia' => 'N',
        'codsitgeradora' => '200004300',
        'iniciatcat' => 1,
        'ultdiatrab' => '2026-09-10',
        'houveafast' => 'N',
        'tplocal' => 1,
        'tplograd' => 'R',
        'dsclograd' => 'Rua Exemplo',
        'nrlograd' => '100',
        'cep' => '01001000',
        'codmunic' => '3550308',
        'uf' => 'SP',
        'idelocalacid' => ['tpinsc' => 1, 'nrinsc' => '12345678000195'],
        'parteatingida' => ['codparteating' => '753030000', 'lateralidade' => 1],
        'agentecausador' => ['codagntcausador' => '302010300'],
        'atestado' => [
            'dtatendimento' => '2026-09-10',
            'hratendimento' => '1000',
            'indinternacao' => 'N',
            'durtrat' => 2,
            'indafast' => 'N',
            'dsclesao' => '706090000',
            'dsccomplesao' => $dscCompLesao,
            'codcid' => 'S610',
            'nmemit' => 'Dr. Exemplo',
            'ideoc' => 1,
            'nroc' => '123456',
            'ufoc' => 'SP',
        ],
    ]));
}

/*
 * Regressão: o JSON schema declarava "dsccompLesao" e a entrada vira minúscula antes de validar,
 * então o limite de 200 caracteres do dscCompLesao nunca era conferido na entrada.
 */
it('recusa dscCompLesao acima de 200 caracteres', function () {
    $std = catPayload(str_repeat('a', 201));

    new EvtCAT($this->configJson, $std, $this->certificate, '2026-10-16 10:00:00');
})->throws(Exception::class, 'atestado.dsccomplesao');
