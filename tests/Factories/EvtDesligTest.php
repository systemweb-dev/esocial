<?php

namespace NFePHP\eSocial\Tests\Factories;

use NFePHP\eSocial\Factories\EvtDeslig;
use NFePHP\eSocial\Tests\ESocialTestCase;

class EvtDesligTest extends ESocialTestCase
{
    private function newPayload(array $mudancaCpf): \stdClass
    {
        $data = [
            'indretif' => 1,
            'cpftrab' => '12345678901',
            'matricula' => 'MAT001',
            'mtvdeslig' => '02',
            'dtdeslig' => '2026-08-10',
            'indpagtoapi' => 'N',
            'pensalim' => 0,
            'mudancacpf' => $mudancaCpf,
        ];

        return json_decode(json_encode($data));
    }

    private function signedDocument(\stdClass $std): \DOMDocument
    {
        // With a certificate, toXML() signs and validates against the event XSD.
        $event = new EvtDeslig($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
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

    public function testIndCpfDesvincOtherThanNineIsRejected()
    {
        $std = $this->newPayload(['novocpf' => '10987654321', 'indcpfdesvinc' => 1]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('mudancacpf.indcpfdesvinc');

        new EvtDeslig($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
    }
}
