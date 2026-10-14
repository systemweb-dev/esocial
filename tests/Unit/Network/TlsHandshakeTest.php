<?php

use NFePHP\eSocial\Common\Soap\SoapCurl;

/*
 * Rede de verdade, fora do "composer test". Roda com: vendor/bin/pest --group=network
 * Só faz o handshake TLS verificado com os webservices do eSocial: sem certificado de cliente,
 * sem envelope e sem nenhum dado. Qualquer código HTTP serve; o que importa é o TLS fechar.
 */
it('completa o handshake TLS verificado com o webservice do eSocial', function (string $url) {
    $options = (new SoapCurl())->securityOptions();
    $curl = curl_init($url);
    curl_setopt_array($curl, $options + [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
    ]);

    curl_exec($curl);

    expect(curl_errno($curl))->toBe(0, curl_error($curl));
})->with([
    'produção restrita' => 'https://webservices.producaorestrita.esocial.gov.br/servicos/empregador/enviarloteeventos/WsEnviarLoteEventos.svc',
    'envio' => 'https://webservices.envio.esocial.gov.br/servicos/empregador/enviarloteeventos/WsEnviarLoteEventos.svc',
    'consulta' => 'https://webservices.consulta.esocial.gov.br/servicos/empregador/consultarloteeventos/WsConsultarLoteEventos.svc',
    'download' => 'https://webservices.download.esocial.gov.br/servicos/empregador/dwlcirurgico/WsSolicitarDownloadEventos.svc',
])->group('network');
