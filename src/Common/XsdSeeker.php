<?php

namespace NFePHP\eSocial\Common;

class XsdSeeker
{

    public static $list = [
        'ConsultaLoteEventos' => ['version' => '', 'name' => ''],
        'EnvioLoteEventos' => ['version' => '', 'name' => ''],
        'ConsultaIdentificadoresEventosEmpregador' => ['version' => '', 'name' => ''],
        'ConsultaIdentificadoresEventosTabela' => ['version' => '', 'name' => ''],
        'ConsultaIdentificadoresEventosTrabalhador' => ['version' => '', 'name' => ''],
        'RetornoEnvioLoteEventos' => ['version' => '', 'name' => ''],
        'RetornoEvento' => ['version' => '', 'name' => ''],
        'RetornoProcessamentoLote' => ['version' => '', 'name' => ''],
        'SolicitacaoDownloadEventosPorId' => ['version' => '', 'name' => ''],
        'SolicitacaoDownloadEventosPorNrRecibo' => ['version' => '', 'name' => ''],
        'WsConsultarLoteEventos' => ['version' => '', 'name' => ''],
        'WsEnviarLoteEventos' => ['version' => '', 'name' => ''],
        'WsConsultarIdentificadoresEventos' => ['version' => '', 'name' => ''],
        'WsSolicitarDownloadEventos' => ['version' => '', 'name' => ''],
    ];

    /**
     * Procura, na pasta do pacote de comunicação, o arquivo e a versão de cada operação.
     * Cada chamada parte de uma lista limpa; $list guarda o resultado da última chamada.
     *
     * @param string $path
     * @return array
     */
    public static function seek($path)
    {
        $list = [];
        foreach (array_keys(self::$list) as $key) {
            $list[$key] = ['version' => '', 'name' => ''];
        }
        if (is_dir($path)) {
            foreach (scandir($path) as $filename) {
                if ($filename == '.' || $filename == '..') {
                    continue;
                }
                foreach (array_keys($list) as $key) {
                    if (substr($filename, 0, strlen($key)) == $key) {
                        $list[$key] = ['version' => self::getVersion($filename), 'name' => $filename];
                        break;
                    }
                }
            }
        }
        self::$list = $list;

        return $list;
    }

    public static function getVersion($filename)
    {
        $p = explode('-', $filename);
        $v = explode('.', $p[1]);
        return $v[0];
    }
}
