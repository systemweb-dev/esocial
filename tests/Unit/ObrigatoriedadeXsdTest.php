<?php

use NFePHP\eSocial\Event;

/*
 * O JSON schema segue a ocorrência do XSD (e do leiaute) relativa ao grupo pai: campo opcional no
 * XSD não é recusado pela lib (a regra condicional fica para o governo) e campo obrigatório no XSD
 * é recusado antes de montar o XML. Base: o exemplo do evento sem o campo.
 */

it('aceita o evento sem o campo que o XSD deixa opcional', function (string $code, string $path) {
    $std = withoutField(exampleInput(exampleFile($code)), $path);
    $event = Event::$code($this->configJson, $std, $this->certificate, '2026-10-18 10:00:00');

    $document = eventDocument($event);

    expect($document->documentElement->localName)->toBe('eSocial');
})->with([
    'S-1200 vlrAdv' => ['s1200', 'dmdev.inforra.ideadv.vlradv'],
    'S-1202 vlrAdv' => ['s1202', 'dmdev.inforra.ideadv.vlradv'],
    'S-1210 nome do dependente' => ['s1210', 'infoircomplem.infodep.nome'],
    'S-1210 codSusp' => ['s1210', 'infoircomplem.infoircr.infoprocret.codsusp'],
    'S-2200 tmpResid' => ['s2200', 'trabimig.tmpresid'],
    'S-2200 tpDep' => ['s2200', 'dependente.tpdep'],
    'S-2200 incTrab' => ['s2200', 'dependente.inctrab'],
    'S-2200 endereco' => ['s2200', 'endereco'],
    'S-2205 endereco' => ['s2205', 'endereco'],
    'S-2205 codPostal' => ['s2205', 'endereco.exterior.codpostal'],
    'S-2205 tpDep' => ['s2205', 'dependente.tpdep'],
    'S-2205 incTrab' => ['s2205', 'dependente.inctrab'],
    'S-2206 tpLograd' => ['s2206', 'infocontrato.localtempdom.tplograd'],
    'S-2206 qtdHrsSem' => ['s2206', 'infocontrato.horcontratual.qtdhrssem'],
    'S-2210 ultDiaTrab' => ['s2210', 'ultdiatrab'],
    'S-2210 houveAfast' => ['s2210', 'houveafast'],
    'S-2210 ufOC' => ['s2210', 'atestado.ufoc'],
    'S-2299 dtAcConv' => ['s2299', 'verbasresc.dmdev.infoperant.ideadc.dtacconv'],
    'S-2300 endereco' => ['s2300', 'endereco'],
    'S-2300 codPostal' => ['s2300', 'endereco.exterior.codpostal'],
    'S-2300 tpDep' => ['s2300', 'dependente.tpdep'],
    'S-2300 CBOCargo' => ['s2300', 'cargofuncao.cbocargo'],
    'S-2300 CBOFuncao' => ['s2300', 'cargofuncao.cbofuncao'],
    'S-2400 tpDep' => ['s2400', 'dependente.tpdep'],
    'S-2405 tpDep' => ['s2405', 'dadosbenef.dependente.tpdep'],
    'S-2500 infoDeslig' => ['s2500', 'infocontr.infocompl.infovinc.infodeslig'],
    'S-2501 codSusp' => ['s2501', 'idetrab.infocrirrf.infoprocret.codsusp'],
]);

it('recusa o evento sem o campo que o XSD exige', function (string $code, string $path) {
    $std = withoutField(exampleInput(exampleFile($code)), $path);

    $error = caughtException(fn () => Event::$code($this->configJson, $std, $this->certificate, '2026-10-18 10:00:00'));

    expect((string) $error?->getMessage())->toContain('A propriedade ' . basename(str_replace('.', '/', $path)) . ' é obrigatória');
})->with([
    'S-1200 codSusp' => ['s1200', 'procjudtrab.codsusp'],
    'S-1200 vrRubr' => ['s1200', 'dmdev.ideestablot.remunperapur.itensremun.vrrubr'],
    'S-1299 infoFech' => ['s1299', 'infofech'],
    'S-2200 nrProcJud' => ['s2200', 'vinculo.infocontrato.alvarajudicial.nrprocjud'],
    'S-2200 matricAnt' => ['s2200', 'vinculo.mudancacpf.matricant'],
    'S-2206 nrProcJud' => ['s2206', 'infocontrato.alvarajudicial.nrprocjud'],
    'S-2210 codSitGeradora' => ['s2210', 'codsitgeradora'],
    'S-2210 atestado' => ['s2210', 'atestado'],
    'S-2210 dscLesao' => ['s2210', 'atestado.dsclesao'],
    'S-2220 procRealizado' => ['s2220', 'exmedocup.aso.exame.procrealizado'],
    'S-2220 nmMed' => ['s2220', 'exmedocup.aso.medico.nmmed'],
    'S-2220 nmResp' => ['s2220', 'exmedocup.respmonit.nmresp'],
    'S-2220 nrCRM' => ['s2220', 'exmedocup.respmonit.nrcrm'],
    'S-2220 ufCRM' => ['s2220', 'exmedocup.respmonit.ufcrm'],
    'S-2231 matricula' => ['s2231', 'matricula'],
    'S-2240 docAval' => ['s2240', 'agnoc.epcepi.epi.docaval'],
    'S-2300 dtOpcFGTS' => ['s2300', 'fgts.dtopcfgts'],
    'S-2300 tpRegPrev' => ['s2300', 'infodirigentesindical.tpregprev'],
    'S-2399 codSusp' => ['s2399', 'verbasresc.procjudtrab.codsusp'],
]);
