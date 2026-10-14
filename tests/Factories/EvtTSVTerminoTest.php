<?php

namespace NFePHP\eSocial\Tests\Factories;

use NFePHP\eSocial\Factories\EvtTSVTermino;
use NFePHP\eSocial\Tests\ESocialTestCase;

class EvtTSVTerminoTest extends ESocialTestCase
{
    private function newPayload(array $extra): \stdClass
    {
        $data = array_merge([
            'indretif' => 1,
            'cpftrab' => '12345678901',
            'matricula' => 'MAT906',
            'dtterm' => '2026-08-10',
        ], $extra);

        return json_decode(json_encode($data));
    }

    private function signedDocument(\stdClass $std): \DOMDocument
    {
        // With a certificate, toXML() signs and validates against the event XSD.
        $event = new EvtTSVTermino($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
        $document = new \DOMDocument();
        $this->assertTrue($document->loadXML($event->toXML()));
        $this->assertSame(1, $document->getElementsByTagName('Signature')->length);

        return $document;
    }

    public function testInformedIndCpfDesvincIsEmittedAfterNovoCpf()
    {
        $document = $this->signedDocument($this->newPayload(['novocpf' => '10987654321', 'indcpfdesvinc' => 9]));

        $mudancaCpf = $document->getElementsByTagName('mudancaCPF')->item(0);
        $this->assertSame('novoCPF', $mudancaCpf->firstElementChild->localName);
        $this->assertSame('indCPFDesvinc', $mudancaCpf->lastElementChild->localName);
        $this->assertSame('9', $mudancaCpf->lastElementChild->textContent);
    }

    public function testOmittedIndCpfDesvincIsNotEmitted()
    {
        $document = $this->signedDocument($this->newPayload(['novocpf' => '10987654321']));

        $this->assertSame(1, $document->getElementsByTagName('novoCPF')->length);
        $this->assertSame(0, $document->getElementsByTagName('indCPFDesvinc')->length);
    }

    public function testIndCpfDesvincWithoutNovoCpfIsIgnored()
    {
        $document = $this->signedDocument($this->newPayload(['indcpfdesvinc' => 9]));

        $this->assertSame(0, $document->getElementsByTagName('mudancaCPF')->length);
        $this->assertSame(0, $document->getElementsByTagName('indCPFDesvinc')->length);
    }

    public function testIndCpfDesvincOtherThanNineIsRejected()
    {
        $std = $this->newPayload(['novocpf' => '10987654321', 'indcpfdesvinc' => 1]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('indcpfdesvinc');

        new EvtTSVTermino($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
    }
}
