<?php

use NFePHP\eSocial\Event;
use NFePHP\eSocial\Tools;

/*
 * O SoapFake não acessa a rede: devolve em JSON a url, a action, os cabeçalhos e o envelope SOAP
 * que seriam enviados. Antes de enviar, o Tools valida a requisição no XSD de comunicação do
 * serviceVersion do config (1.6.0 nos testes).
 */

function cdBenTermEvent(string $configJson, $certificate)
{
    $std = json_decode(json_encode([
        'indretif' => 1,
        'cpfbenef' => '12345678901',
        'nrbeneficio' => 'b1234',
        'dttermbeneficio' => '2026-08-10',
        'mtvtermino' => '01',
    ]));

    return Event::evtCdBenTerm($configJson, $std, $certificate, '2026-08-01 10:00:00');
}

describe('consultas e downloads', function () {
    it('monta a requisição e a envia ao serviço certo', function (string $method, array $args, string $service, string $content, bool $signed) {
        $tools = toolsWithFakeSoap($this->configJson, $this->certificate);

        $request = fakeSoapRequest($tools->$method(...$args));

        expect($request['url'])->toStartWith('https://webservices.producaorestrita.esocial.gov.br/')
            ->and($request['url'])->toEndWith("/$service.svc")
            ->and($request['action'])->toEndWith('/' . $request['operation'])
            ->and($request['body'])->toContain($content)
            ->and(str_contains($request['body'], '</Signature>'))->toBe($signed);
    })->with([
        'consultar lote' => ['consultarLoteEventos', ['1.2.201707.0000000000000007638'], 'WsConsultarLoteEventos', '<protocoloEnvio>1.2.201707.0000000000000007638</protocoloEnvio>', false],
        'eventos do empregador' => ['consultarEventosEmpregador', ['S-1200', '2018-12'], 'WsConsultarIdentificadoresEventos', '<tpEvt>S-1200</tpEvt><perApur>2018-12</perApur>', true],
        'eventos de tabela' => ['consultarEventosTabela', ['S-1010'], 'WsConsultarIdentificadoresEventos', '<tpEvt>S-1010</tpEvt>', true],
        'eventos do trabalhador' => ['consultarEventosTrabalhador', ['12345678901', '2012-12-13T12:12:12', '2012-12-14T12:12:12'], 'WsConsultarIdentificadoresEventos', '<cpfTrab>12345678901</cpfTrab>', true],
        'download por Id' => ['downloadEventosPorId', [['ID1999999990000002017080310370000001']], 'WsSolicitarDownloadEventos', '<id>ID1999999990000002017080310370000001</id>', true],
        'download por recibo' => ['downloadEventosPorNrRecibo', [['1.1.0000000000000000001']], 'WsSolicitarDownloadEventos', '<nrRec>1.1.0000000000000000001</nrRec>', true],
    ]);

    it('usa os endereços de produção quando tpAmb é 1', function () {
        $config = json_decode($this->configJson, true);
        $config['tpAmb'] = 1;
        $tools = toolsWithFakeSoap(json_encode($config), $this->certificate);

        $request = fakeSoapRequest($tools->consultarLoteEventos('1.2.201707.0000000000000007638'));

        expect($request['url'])->toBe('https://webservices.consulta.esocial.gov.br/'
            . 'servicos/empregador/consultarloteeventos/WsConsultarLoteEventos.svc');
    });

    it('guarda a última requisição e a última resposta', function () {
        $tools = toolsWithFakeSoap($this->configJson, $this->certificate);

        $response = $tools->consultarLoteEventos('1.2.201707.0000000000000007638');

        expect($tools->lastResponse)->toBe($response)
            ->and($tools->lastRequest)->toStartWith('<v1:ConsultarLoteEventos>');
    });
});

describe('envio de lote de eventos', function () {
    it('envia o evento no grupo declarado, com empregador e transmissor', function () {
        $tools = toolsWithFakeSoap($this->configJson, $this->certificate);
        $event = infoEmpregadorEvent($this->configJson, $this->certificate);

        $request = fakeSoapRequest($tools->enviarLoteEventos(Tools::EVT_INICIAIS, [$event]));

        expect($request['url'])->toEndWith('/WsEnviarLoteEventos.svc')
            ->and($request['body'])->toContain('<envioLoteEventos grupo="1">')
            ->and($request['body'])->toContain("<evento Id=\"{$event->evtid}\">")
            ->and($request['body'])->toContain('<ideTransmissor><tpInsc>1</tpInsc><nrInsc>99999999999999</nrInsc></ideTransmissor>')
            ->and($request['parameters'])->toContain('Content-length: ' . strlen($request['body']))
            ->and($request['parameters'])->toContain("SOAPAction: \"{$request['action']}\"");
    });

    it('recusa evento de outro grupo', function () {
        $tools = toolsWithFakeSoap($this->configJson, $this->certificate);
        $event = infoEmpregadorEvent($this->configJson, $this->certificate);

        $tools->enviarLoteEventos(Tools::EVT_NAO_PERIODICOS, [$event]);
    })->throws(RuntimeException::class, 'O evento S-1000 não pertence a este grupo');

    it('recusa lote com mais de 50 eventos', function () {
        $tools = toolsWithFakeSoap($this->configJson, $this->certificate);
        $events = array_fill(0, 51, infoEmpregadorEvent($this->configJson, $this->certificate));

        $tools->enviarLoteEventos(Tools::EVT_INICIAIS, $events);
    })->throws(InvalidArgumentException::class, 'você está tentando enviar 51 eventos');

    it('não envia nada quando o lote está vazio', function () {
        $tools = toolsWithFakeSoap($this->configJson, $this->certificate);

        $response = $tools->enviarLoteEventos(Tools::EVT_INICIAIS, []);

        expect($response)->toBe('')
            ->and($tools->lastRequest)->toBeNull();
    });

    it('assina com o certificado do Tools o evento criado sem certificado', function () {
        $tools = toolsWithFakeSoap($this->configJson, $this->certificate);
        $event = infoEmpregadorEvent($this->configJson);

        $request = fakeSoapRequest($tools->enviarLoteEventos(Tools::EVT_INICIAIS, [$event]));

        expect($event->getCertificate())->toBe($this->certificate)
            ->and(substr_count($request['body'], '</Signature>'))->toBe(1);
    });
});

describe('envio de lote de XML assinados', function () {
    it('localiza o Id do evento no XML e envia no grupo dele', function () {
        $tools = toolsWithFakeSoap($this->configJson, $this->certificate);
        $event = infoEmpregadorEvent($this->configJson, $this->certificate);

        $request = fakeSoapRequest($tools->enviarLoteXmlEventos(Tools::EVT_INICIAIS, [$event->toXML()]));

        expect($request['body'])->toContain('<envioLoteEventos grupo="1">')
            ->and($request['body'])->toContain("<evento Id=\"{$event->evtid}\">");
    });

    it('recusa grupo declarado diferente do grupo do evento', function () {
        $tools = toolsWithFakeSoap($this->configJson, $this->certificate);
        $xml = infoEmpregadorEvent($this->configJson, $this->certificate)->toXML();

        $tools->enviarLoteXmlEventos(Tools::EVT_NAO_PERIODICOS, [$xml]);
    })->throws(RuntimeException::class, 'O grupo correto deve ser declarado');

    it('recusa eventos de grupos diferentes no mesmo lote', function () {
        $tools = toolsWithFakeSoap($this->configJson, $this->certificate);
        $xmls = [
            infoEmpregadorEvent($this->configJson, $this->certificate)->toXML(),
            cdBenTermEvent($this->configJson, $this->certificate)->toXML(),
        ];

        $tools->enviarLoteXmlEventos(Tools::EVT_INICIAIS, $xmls);
    })->throws(RuntimeException::class, 'apenas eventos pertencentes ao mesmo grupo');

    it('recusa XML sem evento do eSocial identificável', function () {
        $tools = toolsWithFakeSoap($this->configJson, $this->certificate);
        $xml = '<eSocial><outroEvento Id="X"/></eSocial>';

        $tools->enviarLoteXmlEventos(Tools::EVT_INICIAIS, [$xml]);
    })->throws(RuntimeException::class, 'Falha na localização do ID do evento.');
});
