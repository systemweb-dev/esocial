<?php

namespace NFePHP\eSocial\Common\Soap;

/**
 * SoapClient based in cURL class
 *
 * @category  library
 * @package   NFePHP\eSocial
 * @copyright NFePHP Copyright (c) 2016
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Roberto L. Machado <linux.rlm at gmail dot com>
 * @link      http://github.com/nfephp-org/sped-common for the canonical source repository
 */

use NFePHP\Common\Certificate;
use NFePHP\Common\Exception\SoapException;
use Psr\Log\LoggerInterface;

class SoapCurl extends SoapBase implements SoapInterface
{
    /**
     * Constructor
     * @param Certificate $certificate
     * @param LoggerInterface $logger
     */
    public function __construct(Certificate $certificate = null, LoggerInterface $logger = null)
    {
        parent::__construct($certificate, $logger);
    }
    /**
     * Send soap message to url
     *
     * @param  string $operation
     * @param  string $url
     * @param  string $action
     * @param  string $envelope
     * @param  array $parameters
     * @return string
     * @throws \NFePHP\Common\Exception\SoapException
     */
    public function send(
        $operation,
        $url,
        $action,
        $envelope,
        $parameters
    ) {
        $response = '';
        $this->requestHead = implode("\n", $parameters);
        $this->requestBody = $envelope;
        try {
            $oCurl = curl_init();
            $this->setCurlProxy($oCurl);
            curl_setopt($oCurl, CURLOPT_URL, $url);
            curl_setopt($oCurl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($oCurl, CURLOPT_CONNECTTIMEOUT, $this->soaptimeout);
            curl_setopt($oCurl, CURLOPT_TIMEOUT, $this->soaptimeout + 20);
            curl_setopt($oCurl, CURLOPT_HEADER, 1);
            curl_setopt_array($oCurl, $this->securityOptions());
            curl_setopt($oCurl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, $this->soapprotocol);
            $this->setClientCertificate($oCurl);
            curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, true);
            if (! empty($envelope)) {
                curl_setopt($oCurl, CURLOPT_POST, true);
                curl_setopt($oCurl, CURLOPT_POSTFIELDS, $envelope);
                curl_setopt($oCurl, CURLOPT_HTTPHEADER, $parameters);
            }
            $response = curl_exec($oCurl);
            $this->soaperror = curl_error($oCurl);
            $this->soaperror_code = curl_errno($oCurl);
            $ainfo = curl_getinfo($oCurl);
            if (is_array($ainfo)) {
                $this->soapinfo = $ainfo;
            }
            $headsize = curl_getinfo($oCurl, CURLINFO_HEADER_SIZE);
            $httpcode = curl_getinfo($oCurl, CURLINFO_HTTP_CODE);
            curl_close($oCurl);
            $this->responseHead = trim(substr($response, 0, $headsize));
            $this->responseBody = trim(substr($response, $headsize));
            $this->saveDebugFiles(
                $operation,
                $this->requestHead."\n".$this->requestBody,
                $this->responseHead."\n".$this->responseBody
            );
        } catch (\Exception $e) {
            throw SoapException::unableToLoadCurl($e->getMessage());
        }
        if ($this->soaperror != '') {
            if (intval($this->soaperror_code) == 0) {
                $this->soaperror_code = 7;
            }
            throw SoapException::soapFault($this->soaperror." [$url]", $this->soaperror_code);
        }
        if ($httpcode != 200) {
            if (intval($httpcode) == 0) {
                $httpcode = 52;
            }
            throw SoapException::soapFault(" [$url]" . $this->responseHead, $httpcode);
        }
        return $this->responseBody;
    }
    /**
     * Opções de verificação TLS do servidor aplicadas em send() e wsdl().
     * Padrão: verifica a cadeia (store do sistema) e o nome do host.
     * loadCA() troca o store pelo bundle informado; disableSecurity(true) desliga a verificação
     * (inseguro: só para ambiente controlado, como proxy que intercepta TLS).
     *
     * @return array
     */
    public function securityOptions()
    {
        if ($this->disablesec) {
            return [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0];
        }
        $options = [CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2];
        if (!empty($this->casefaz)) {
            $options[CURLOPT_CAINFO] = $this->casefaz;
        }

        return $options;
    }

    /**
     * Passa o certificado e a chave privada ao cURL direto da memória.
     * Não grava arquivo temporário: a chave privada nunca toca o disco.
     * Requer PHP 8.1+ e cURL 7.71+ (CURLOPT_SSLCERT_BLOB / CURLOPT_SSLKEY_BLOB).
     * @param resource $oCurl
     */
    private function setClientCertificate(&$oCurl)
    {
        if (! is_object($this->certificate)) {
            throw new \RuntimeException('Certificate not found.');
        }
        curl_setopt($oCurl, CURLOPT_SSLCERTTYPE, 'PEM');
        curl_setopt($oCurl, CURLOPT_SSLCERT_BLOB, "{$this->certificate}");
        curl_setopt($oCurl, CURLOPT_SSLKEYTYPE, 'PEM');
        curl_setopt($oCurl, CURLOPT_SSLKEY_BLOB, "{$this->certificate->privateKey}");
    }
    /**
     * Set proxy into cURL parameters
     * @param resource $oCurl
     */
    private function setCurlProxy(&$oCurl)
    {
        if ($this->proxyIP != '') {
            curl_setopt($oCurl, CURLOPT_HTTPPROXYTUNNEL, 1);
            curl_setopt($oCurl, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            curl_setopt($oCurl, CURLOPT_PROXY, $this->proxyIP.':'.$this->proxyPort);
            if ($this->proxyUser != '') {
                curl_setopt($oCurl, CURLOPT_PROXYUSERPWD, $this->proxyUser.':'.$this->proxyPass);
                curl_setopt($oCurl, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
            }
        }
    }
    /**
     * Extract faultstring form response if exists
     * @param  string $body
     * @return string
     */
    private function getFaultString($body)
    {
        if (empty($body)) {
            return '';
        }
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($body);
        $faultstring = '';
        $nodefault = ! empty($dom->getElementsByTagName('faultstring')->item(0))
            ? $dom->getElementsByTagName('faultstring')->item(0)
            : '';
        if (!empty($nodefault)) {
            $faultstring = $nodefault->nodeValue;
        }
        return htmlentities($faultstring, ENT_QUOTES, 'UTF-8');
    }
    /**
     * Recover WSDL form given URL
     * @param  string $url
     * @return string
     */
    public function wsdl($url)
    {
        $response = '';
        $url .= '?WSDL';
        $oCurl = curl_init();
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($oCurl, CURLOPT_CONNECTTIMEOUT, $this->soaptimeout);
        curl_setopt($oCurl, CURLOPT_TIMEOUT, $this->soaptimeout + 20);
        curl_setopt_array($oCurl, $this->securityOptions());
        curl_setopt($oCurl, CURLOPT_SSLVERSION, $this->soapprotocol);
        $this->setClientCertificate($oCurl);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, true);
        $response  = curl_exec($oCurl);
        $soaperror = curl_error($oCurl);
        $ainfo = curl_getinfo($oCurl);
        $headsize = curl_getinfo($oCurl, CURLINFO_HEADER_SIZE);
        $httpcode = curl_getinfo($oCurl, CURLINFO_HTTP_CODE);
        curl_close($oCurl);
        if ($httpcode != 200) {
            return '';
        }
        return $response;
    }
}
