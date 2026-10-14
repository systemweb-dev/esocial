<?php

namespace NFePHP\eSocial\Tests\Factories;

use NFePHP\eSocial\Factories\EvtProcTrab;
use NFePHP\eSocial\Tests\ESocialTestCase;

class EvtProcTrabTest extends ESocialTestCase
{
    private function newPayload(?array $infoCompl = null): \stdClass
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
        if ($infoCompl !== null) {
            $contrato['infocompl'] = $infoCompl;
        }
        $data = [
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
        ];

        return json_decode(json_encode($data));
    }

    private function newInfoComplWithAllNewFields(): array
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

    private function signedDocument(\stdClass $std): \DOMDocument
    {
        // With a certificate, toXML() signs and validates against the event XSD.
        $event = new EvtProcTrab($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
        $document = new \DOMDocument();
        $this->assertTrue($document->loadXML($event->toXML()));
        $this->assertSame(1, $document->getElementsByTagName('Signature')->length);

        return $document;
    }

    private function childNames(\DOMElement $element): array
    {
        $names = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $names[] = $child->localName;
            }
        }

        return $names;
    }

    public function testNewFieldsAreEmittedInXsdOrder()
    {
        $std = $this->newPayload($this->newInfoComplWithAllNewFields());
        $std->nmsoc = 'Nome Social Teste';

        $document = $this->signedDocument($std);

        $this->assertSame('Nome Social Teste', $document->getElementsByTagName('nmSoc')->item(0)->textContent);
        $infoCompl = $document->getElementsByTagName('infoCompl')->item(0);
        $this->assertSame(
            ['nmCargo', 'codCBO', 'nmFuncao', 'CBOFuncao', 'natAtividade', 'localTrabalho'],
            $this->childNames($infoCompl)
        );
        $this->assertSame('142105', $document->getElementsByTagName('CBOFuncao')->item(0)->textContent);
        $localTrabalho = $document->getElementsByTagName('localTrabalho')->item(0);
        $this->assertSame(['localTrabGeral', 'localTempDom'], $this->childNames($localTrabalho));
        $this->assertSame(0, $document->getElementsByTagName('tpLograd')->length);
        $this->assertSame('3550308', $document->getElementsByTagName('codMunic')->item(1)->textContent);
    }

    public function testOmittedNewFieldsAreNotEmitted()
    {
        $std = $this->newPayload(['codcbo' => '212405']);

        $document = $this->signedDocument($std);

        $this->assertSame(['codCBO'], $this->childNames($document->getElementsByTagName('infoCompl')->item(0)));
        foreach (['nmSoc', 'nmCargo', 'nmFuncao', 'CBOFuncao', 'localTrabalho'] as $tag) {
            $this->assertSame(0, $document->getElementsByTagName($tag)->length, $tag);
        }
    }

    public function testLocalTrabGeralRejectsInscriptionTypeTwo()
    {
        $infoCompl = $this->newInfoComplWithAllNewFields();
        $infoCompl['localtrabalho']['localtrabgeral']['tpinsc'] = 2;
        $std = $this->newPayload($infoCompl);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('localtrabgeral.tpinsc');

        new EvtProcTrab($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
    }
}
