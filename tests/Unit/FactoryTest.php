<?php

use NFePHP\eSocial\Factories\EvtInfoEmpregador;

/*
 * Comportamentos da classe base Factory, comuns a todos os eventos. O S-1000 é usado como
 * evento de referência por ter o payload mínimo mais simples.
 */

function infoEmpregadorData(): array
{
    return [
        'sequencial' => 1,
        'modo' => 'INC',
        'ideperiodo' => ['inivalid' => '2017-01'],
        'infocadastro' => [
            'classtrib' => '01',
            'indcoop' => 0,
            'indconstr' => 0,
            'inddesfolha' => 0,
            'indporte' => 'S',
            'indoptregeletron' => 1,
        ],
    ];
}

function toStdClass(array $data): stdClass
{
    return json_decode(json_encode($data));
}

describe('validação da entrada', function () {
    it('recusa leiaute que não tem XSD no projeto', function () {
        $config = json_decode($this->configJson, true);
        $config['eventoVersion'] = 'S.9.9.9';
        $std = toStdClass(infoEmpregadorData());

        new EvtInfoEmpregador(json_encode($config), $std, $this->certificate);
    })->throws(InvalidArgumentException::class, 'Schemas não localizados');

    it('recusa campo obrigatório ausente com a mensagem em português', function () {
        $data = infoEmpregadorData();
        unset($data['modo']);
        $std = toStdClass($data);

        new EvtInfoEmpregador($this->configJson, $std, $this->certificate);
    })->throws(Exception::class, '[modo] A propriedade modo é obrigatória');

    it('recusa campo fora do padrão indicando o caminho da propriedade', function () {
        $data = infoEmpregadorData();
        $data['ideperiodo']['inivalid'] = '2017-1';
        $std = toStdClass($data);

        new EvtInfoEmpregador($this->configJson, $std, $this->certificate);
    })->throws(Exception::class, '[ideperiodo.inivalid] Não corresponde ao padrão');

    it('aceita chaves em maiúsculas e as normaliza para minúsculas', function () {
        $std = toStdClass([
            'Sequencial' => 1,
            'MODO' => 'INC',
            'IdePeriodo' => ['IniValid' => '2017-01'],
            'InfoCadastro' => [
                'ClassTrib' => '01',
                'IndCoop' => 0,
                'IndConstr' => 0,
                'IndDesFolha' => 0,
                'IndPorte' => 'S',
                'IndOptRegEletron' => 1,
            ],
        ]);

        $event = new EvtInfoEmpregador($this->configJson, $std, $this->certificate);

        expect($event->toArray())->toBe(infoEmpregadorData());
    });

    it('recusa leiaute encerrado no eSocial', function (string $layout) {
        $config = json_decode($this->configJson, true);
        $config['eventoVersion'] = $layout;
        $std = toStdClass(infoEmpregadorData());

        new EvtInfoEmpregador(json_encode($config), $std, $this->certificate);
    })->with(['S.1.0.0', 'S.1.1.0', 'S.1.2.0', 'S.1.2'])
        ->throws(InvalidArgumentException::class, 'encerrado no eSocial: use S.1.3.0.');

    it('não chama de encerrado o S-1.3 escrito sem o último número', function () {
        $config = json_decode($this->configJson, true);
        $config['eventoVersion'] = 'S.1.3';
        $std = toStdClass(infoEmpregadorData());

        new EvtInfoEmpregador(json_encode($config), $std, $this->certificate);
    })->throws(InvalidArgumentException::class, 'Schemas não localizados');
});

describe('saída do evento', function () {
    it('gera o XML sem a declaração <?xml', function () {
        $event = new EvtInfoEmpregador($this->configJson, toStdClass(infoEmpregadorData()), $this->certificate);

        $xml = $event->toXML();

        expect($xml)->toStartWith('<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtInfoEmpregador/v_S_01_03_00">');
    });

    it('gera o XML sem assinatura quando não há certificado', function () {
        $event = new EvtInfoEmpregador($this->configJson, toStdClass(infoEmpregadorData()));

        $xml = $event->toXML();

        expect($xml)->not->toContain('</Signature>');
    });

    it('assina o XML com o certificado definido depois da criação', function () {
        $event = new EvtInfoEmpregador($this->configJson, toStdClass(infoEmpregadorData()));
        $event->setCertificate($this->certificate);

        $xml = $event->toXML();

        expect(substr_count($xml, '</Signature>'))->toBe(1)
            ->and($event->getCertificate())->toBe($this->certificate);
    });

    it('devolve em JSON os dados recebidos', function () {
        $event = new EvtInfoEmpregador($this->configJson, toStdClass(infoEmpregadorData()), $this->certificate);

        $json = $event->toJson();

        expect(json_decode($json, true))->toBe(infoEmpregadorData());
    });

    it('devolve em stdClass os dados recebidos', function () {
        $event = new EvtInfoEmpregador($this->configJson, toStdClass(infoEmpregadorData()), $this->certificate);

        $std = $event->toStd();

        expect($std)->toEqual(toStdClass(infoEmpregadorData()));
    });

    it('informa o código do evento', function () {
        $event = new EvtInfoEmpregador($this->configJson, toStdClass(infoEmpregadorData()), $this->certificate);

        $alias = $event->alias();

        expect($alias)->toBe('S-1000');
    });
});
