<?php

use NFePHP\eSocial\Common\Soap\SoapCurl;

it('verifica o certificado e o nome do servidor por padrão', function () {
    $soap = new SoapCurl($this->certificate);

    $options = $soap->securityOptions();

    expect($options)->toBe([CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
});

it('usa o bundle de CAs informado no lugar do store do sistema', function () {
    $soap = new SoapCurl($this->certificate);
    $bundle = tempnam(sys_get_temp_dir(), 'ca');
    $soap->loadCA($bundle);

    $options = $soap->securityOptions();

    unlink($bundle);
    expect($options)->toBe([
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CAINFO => $bundle,
    ]);
});

it('recusa bundle de CAs que não existe', function () {
    $soap = new SoapCurl($this->certificate);

    $soap->loadCA('/caminho/inexistente/ca.pem');
})->throws(InvalidArgumentException::class, '/caminho/inexistente/ca.pem');

it('desliga a verificação só quando pedido explicitamente', function () {
    $soap = new SoapCurl($this->certificate);
    $soap->disableSecurity(true);

    $options = $soap->securityOptions();

    expect($options)->toBe([CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]);
});
