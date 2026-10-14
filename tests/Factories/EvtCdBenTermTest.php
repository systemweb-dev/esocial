<?php

namespace NFePHP\eSocial\Tests\Factories;

use NFePHP\eSocial\Factories\EvtCdBenTerm;
use NFePHP\eSocial\Tests\ESocialTestCase;

class EvtCdBenTermTest extends ESocialTestCase
{
    private function newPayload(array $extra): \stdClass
    {
        $data = array_merge([
            'indretif' => 1,
            'cpfbenef' => '12345678901',
            'nrbeneficio' => 'b1234',
            'dttermbeneficio' => '2026-08-10',
            'mtvtermino' => '01',
        ], $extra);

        return json_decode(json_encode($data));
    }

    private function signedDocument(\stdClass $std): \DOMDocument
    {
        // With a certificate, toXML() signs and validates against the event XSD.
        $event = new EvtCdBenTerm($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
        $document = new \DOMDocument();
        $this->assertTrue($document->loadXML($event->toXML()));
        $this->assertSame(1, $document->getElementsByTagName('Signature')->length);

        return $document;
    }

    public function testInformedIndCpfDesvincIsEmittedAfterNovoCpf()
    {
        $document = $this->signedDocument($this->newPayload(['novocpf' => '10987654321', 'indcpfdesvinc' => 9]));

        $infoBenTermino = $document->getElementsByTagName('infoBenTermino')->item(0);
        $this->assertSame('indCPFDesvinc', $infoBenTermino->lastElementChild->localName);
        $this->assertSame('9', $infoBenTermino->lastElementChild->textContent);
        $this->assertSame('novoCPF', $infoBenTermino->lastElementChild->previousElementSibling->localName);
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
        $this->expectExceptionMessage('indcpfdesvinc');

        new EvtCdBenTerm($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
    }
}
