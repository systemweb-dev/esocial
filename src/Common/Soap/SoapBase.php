<?php

namespace NFePHP\eSocial\Common\Soap;

/**
 * Soap base class
 *
 * @category  library
 * @package   NFePHP\eSocial
 * @copyright NFePHP Copyright (c) 2017
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Roberto L. Machado <linux.rlm at gmail dot com>
 * @link      http://github.com/nfephp-org/sped-nfse for the canonical source repository
 */

use NFePHP\Common\Certificate;
use NFePHP\Common\Exception\RuntimeException;
use NFePHP\Common\Files;
use Psr\Log\LoggerInterface;

abstract class SoapBase implements SoapInterface
{
    /**
     * @var string
     */
    public $responseHead;
    /**
     * @var string
     */
    public $responseBody;
    /**
     * @var string
     */
    public $requestHead;
    /**
     * @var string
     */
    public $requestBody;
    /**
     * @var string
     */
    public $soaperror;
    /**
     * @var int
     */
    public $soaperror_code;
    /**
     * @var array
     */
    public $soapinfo = [];
    /**
     * @var int
     */
    protected $soapprotocol = self::SSL_DEFAULT;
    /**
     * @var int
     */
    protected $soaptimeout = 20;
    /**
     * @var string
     */
    protected $proxyIP;
    /**
     * @var string
     */
    protected $proxyPort;
    /**
     * @var string
     */
    protected $proxyUser;
    /**
     * @var string
     */
    protected $proxyPass;
    /**
     * @var Certificate
     */
    protected $certificate;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var string
     */
    protected $tempdir;
    /**
     * @var string
     */
    protected $debugdir;
    /**
     * @var string
     */
    protected $casefaz;
    /**
     * @var bool
     */
    protected $disablesec = false;
    /**
     * @var bool
     */
    protected $disableCertValidation = false;
    /**
     * @var Files
     */
    protected $filesystem;
    /**
     * @var bool
     */
    protected $debugmode = false;
    /**
     * Constructor
     *
     * @param Certificate $certificate
     * @param LoggerInterface $logger
     */
    public function __construct(
        ?Certificate $certificate = null,
        ?LoggerInterface $logger = null
    ) {
        $this->logger      = $logger;
        $this->certificate = $this->checkCertValidity($certificate);
        $this->setTemporaryFolder(sys_get_temp_dir().'/sped/');
    }
    /**
     * Check if certificate is valid
     *
     * @param  Certificate $certificate
     *
     * @return Certificate
     * @throws RuntimeException
     */
    private function checkCertValidity(?Certificate $certificate = null)
    {
        if ($this->disableCertValidation) {
            return $certificate;
        }
        if (!empty($certificate)) {
            if ($certificate->isExpired()) {
                throw new RuntimeException(
                    'The validity of the certificate has expired.'
                );
            }
        }
        return $certificate;
    }
    /**
     * Set another temporayfolder for saving certificates for SOAP utilization
     *
     * @param string $folderRealPath
     */
    public function setTemporaryFolder($folderRealPath)
    {
        $this->tempdir = $folderRealPath;
        $this->setLocalFolder($folderRealPath);
    }
    /**
     * Set Local folder for flysystem
     *
     * @param string $folder
     */
    protected function setLocalFolder($folder = '')
    {
        $this->filesystem = new Files($folder);
    }
    /**
     * Disables the security checking of host and peer certificates
     *
     * @param bool $flag
     */
    public function disableSecurity($flag = false)
    {
        $this->disablesec = $flag;
        return $this->disablesec;
    }
    /**
     * ONlY for tests
     *
     * @param  bool $flag
     *
     * @return bool
     */
    public function disableCertValidation($flag = true)
    {
        $this->disableCertValidation = $flag;
        return $this->disableCertValidation;
    }
    /**
     * Usa o bundle de CAs informado para verificar o servidor.
     * Atenção: o arquivo SUBSTITUI o store de CAs do sistema (CURLOPT_CAINFO), não soma a ele.
     * Os webservices do eSocial usam a raiz pública GlobalSign Root R46, que já está no store
     * do sistema; não é preciso chamar este método para eles.
     *
     * @param string $capath
     * @throws \InvalidArgumentException se o arquivo não existir
     */
    public function loadCA($capath)
    {
        if (!is_file($capath)) {
            throw new \InvalidArgumentException("Bundle de CAs não encontrado: $capath");
        }
        $this->casefaz = $capath;
    }
    /**
     * Set debug mode, this mode will save soap envelopes in temporary directory
     *
     * @param  bool $value
     *
     * @return bool
     */
    public function setDebugMode($value = false)
    {
        return $this->debugmode = $value;
    }
    /**
     * Set certificate class for SSL comunications
     *
     * @param Certificate $certificate
     */
    public function loadCertificate(Certificate $certificate)
    {
        $this->certificate = $this->checkCertValidity($certificate);
    }
    /**
     * Set logger class
     *
     * @param LoggerInterface $logger
     */
    public function loadLogger(LoggerInterface $logger)
    {
        return $this->logger = $logger;
    }
    /**
     * Set timeout for communication
     *
     * @param int $timesecs
     */
    public function timeout($timesecs)
    {
        return $this->soaptimeout = $timesecs;
    }
    /**
     * Set security protocol
     *
     * @param  int $protocol
     *
     * @return int
     */
    public function protocol($protocol = self::SSL_DEFAULT)
    {
        return $this->soapprotocol = $protocol;
    }
    /**
     * Set proxy parameters
     *
     * @param string $ip
     * @param int $port
     * @param string $user
     * @param string $password
     */
    public function proxy($ip, $port, $user, $password)
    {
        $this->proxyIP   = $ip;
        $this->proxyPort = $port;
        $this->proxyUser = $user;
        $this->proxyPass = $password;
    }
    /**
     * Send message to webservice
     */
    abstract public function send(
        $operation,
        $url,
        $action,
        $envelope,
        $parameters
    );
    /**
     * Save request envelope and response for debug reasons
     *
     * @param  string $operation
     * @param  string $request
     * @param  string $response
     *
     * @return void
     */
    public function saveDebugFiles($operation, $request, $response)
    {
        if (! $this->debugmode) {
            return;
        }
        $this->debugdir = $this->certificate->getCnpj().'/debug/';
        $now = \DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));
        $time = substr($now->format("ymdHisu"), 0, 16);
        try {
            $this->filesystem->put(
                $this->debugdir.$time."_".$operation."_sol.txt",
                $request
            );
            $this->filesystem->put(
                $this->debugdir.$time."_".$operation."_res.txt",
                $response
            );
        } catch (\Exception $e) {
            throw new RuntimeException(
                'Unable to create debug files.'
            );
        }
    }
}
