# Plano de implementação — SP1 envio seguro e correto

[spec.md](spec.md)

> **Execução:** implementar task por task. Os steps usam checkbox (`- [ ]`) para acompanhar.
> Os dois modos de execução estão na seção "Execution Handoff" da skill `sw-plan`.

**Objetivo:** aceitar lote de CNPJ alfanumérico (comunicação 1.6-Alfa), verificar o TLS do
webservice, não repetir Id de evento no processo e agrupar eventos conforme o manual.

**Arquitetura:** pacote 1.6-Alfa em `schemes/comunicacao/v1_6_0` (sem mudança em `Tools`);
`SoapCurl::securityOptions()` concentra as opções TLS; `FactoryId` ganha registro estático de
Ids; `Common\Tools::$grupos` reescrito e normalização do grupo em `Tools`; `XsdSeeker::seek()`
sem estado herdado; `check.py` entende a versão Alfa.

**Stack:** PHP 8.4 (dev) / 8.3 (produção do consumidor), Pest 5.3 + PHPUnit 13.4, ext-curl,
`nfephp-org/sped-common` (Validator lança `NFePHP\Common\Exception\ValidatorException`, que
estende `RuntimeException`), Python 3 stdlib (`unittest`) para o `check.py`.

**Restrições verificáveis (do spec):**
| Restrição | Como o plano checa |
|---|---|
| `grep -n "curl_setopt(.*SSL_VERIFY" src/Common/Soap/SoapCurl.php` vazio | Task 3, step 6 |
| `composer test` verde; `vendor/bin/pest --group=network` verde com rede | Task 4, steps 4-5; Task 8, step 3 |
| Cada código de `Event::$codes` (exceto S-8200/S-8299) aceito em exatamente um grupo | Task 6, steps 1-4 |
| `validate.php` → 40/40 | Task 1, step 9; Task 8, step 4 |
| `git diff --stat` não toca `src/Factories/`, `jsonSchemes/`, `schemes/v_S_*` | Task 8, step 5 |

**Convenções para quem executa:**
- Raiz: `/var/www/esocial`. Testes: `composer test` (roda `vendor/bin/pest` com Xdebug
  desligado). Um arquivo: `php -d xdebug.mode=off vendor/bin/pest <arquivo>`.
- Testes em Pest e **AAA estrito**: Arrange, Act e Assert separados por linha em branco, um
  único Act, exceção esperada em `->throws()`, helper sem asserção (`tests/Pest.php`).
- Dados fictícios; rede só no grupo `network`.
- Pacote 1.6-Alfa já baixado e achatado em
  `ALFA=/tmp/claude-1000/-var-www-esocial/b3c667c3-32b6-440c-9034-e207a90f4558/scratchpad/analise-tls/dl-alfa/flat`
  (17 arquivos). Se sumir, baixe numa pasta **vazia**:
  `https://www.gov.br/esocial/pt-br/documentacao-tecnica/manuais/pacote-de-comunicacao-esocial-v1-6-alfa.zip`
  e copie todos os `.xsd`/`.wsdl` (de todas as subpastas) para uma pasta só.
- Não commitar em task; commit só no checkpoint com o "sim" do usuário.

**Ordem pelo risco:** a Task 1 ataca a suposição central (o lote com CNPJ alfanumérico passa na
validação local com o 1.6-Alfa e o restante continua igual). A suposição de servidor (aceitar o
lote) e a de CA na imagem de produção só se validam no enviaesocial (Rollout do spec).

---

### Task 1: Pacote de Comunicação 1.6-Alfa e padrão 1.6.0

**Arquivos:**
- Criar: `schemes/comunicacao/v1_6_0/` (17 arquivos do pacote)
- Criar: `tests/Unit/ComunicacaoTest.php`
- Alterar: `tests/Pest.php` (recebe helpers de `ToolsTest`)
- Alterar: `tests/Unit/ToolsTest.php:1-41` (helpers saem; comentário do topo)
- Alterar: `tests/ESocialTestCase.php:29` (`serviceVersion`)
- Alterar: `examples/Fake/v_S_01_03_00/*.php` (só `serviceVersion`)
- Alterar: `docs/Config.md:10`
- Alterar: `ESOCIAL-VERSION.json` (`communication`)

**Depende de:** nada

**Contrato que esta task publica:** helpers globais em `tests/Pest.php`:
`toolsWithFakeSoap(string $configJson, $certificate): NFePHP\eSocial\Tools`,
`fakeSoapRequest(string $response): array`,
`infoEmpregadorEvent(string $configJson, $certificate = null)`,
`configWith(string $configJson, array $changes): string`.

- [x] **Step 1: mover os helpers do ToolsTest para tests/Pest.php**

Em `tests/Unit/ToolsTest.php`, remover as funções `toolsWithFakeSoap`, `fakeSoapRequest` e
`infoEmpregadorEvent` (mantém `cdBenTermEvent`) e trocar o comentário do topo por:

```php
/*
 * O SoapFake não acessa a rede: devolve em JSON a url, a action, os cabeçalhos e o envelope SOAP
 * que seriam enviados. Antes de enviar, o Tools valida a requisição no XSD de comunicação do
 * serviceVersion do config (1.6.0 nos testes).
 */
```

Remover também de `ToolsTest.php` os `use NFePHP\eSocial\Common\Soap\SoapFake;` (passa a ser
usado só no `Pest.php`). Em `tests/Pest.php`, acrescentar no fim:

```php
/**
 * Tools com SoapFake: nada vai para a rede; a resposta é o JSON da requisição que seria enviada.
 */
function toolsWithFakeSoap(string $configJson, $certificate): \NFePHP\eSocial\Tools
{
    $tools = new \NFePHP\eSocial\Tools($configJson, $certificate);
    $tools->loadSoapClass(new \NFePHP\eSocial\Common\Soap\SoapFake());

    return $tools;
}

function fakeSoapRequest(string $response): array
{
    return json_decode($response, true);
}

/**
 * S-1000 mínimo (inclusão, sequencial 1) com data fixa.
 */
function infoEmpregadorEvent(string $configJson, $certificate = null)
{
    $std = json_decode(json_encode([
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
    ]));

    return \NFePHP\eSocial\Event::evtInfoEmpregador($configJson, $std, $certificate, '2017-08-03 10:37:00');
}

/**
 * Copia o config JSON mudando chaves de primeiro e segundo nível (ex.: ['empregador' => ['nrInsc' => 'X']]).
 */
function configWith(string $configJson, array $changes): string
{
    return json_encode(array_replace_recursive(json_decode($configJson, true), $changes));
}
```

- [x] **Step 2: confirmar que a suíte segue verde com os helpers movidos**

Rode: `composer test`
Esperado: `Tests:    41 deprecated, 133 passed`.

- [x] **Step 3: escrever os testes que falham**

Criar `tests/Unit/ComunicacaoTest.php`:

```php
<?php

use NFePHP\Common\Exception\ValidatorException;
use NFePHP\eSocial\Common\XsdSeeker;

/*
 * Pacote de Comunicação 1.6-Alfa (schemes/comunicacao/v1_6_0): aceita CNPJ alfanumérico na
 * identificação do empregador; o 1.5.0 só aceita dígitos. O lote é validado localmente no XSD
 * de envio antes de ir para o webservice.
 */

it('envia lote de empregador com CNPJ alfanumérico no serviço 1.6.0', function () {
    $config = configWith($this->configJson, ['serviceVersion' => '1.6.0', 'empregador' => ['nrInsc' => 'A1B2C3D4']]);
    $tools = toolsWithFakeSoap($config, $this->certificate);
    $event = infoEmpregadorEvent($config, $this->certificate);

    $request = fakeSoapRequest($tools->enviarLoteEventos(1, [$event]));

    expect($request['body'])->toContain('<ideEmpregador><tpInsc>1</tpInsc><nrInsc>A1B2C3D4</nrInsc></ideEmpregador>');
});

it('recusa lote de empregador com CNPJ alfanumérico no serviço 1.5.0', function () {
    $config = configWith($this->configJson, ['serviceVersion' => '1.5.0', 'empregador' => ['nrInsc' => 'A1B2C3D4']]);
    $tools = toolsWithFakeSoap($config, $this->certificate);
    $event = infoEmpregadorEvent($config, $this->certificate);

    $tools->enviarLoteEventos(1, [$event]);
})->throws(ValidatorException::class);

it('envia lote de empregador com CNPJ numérico no serviço 1.6.0', function () {
    $config = configWith($this->configJson, ['serviceVersion' => '1.6.0']);
    $tools = toolsWithFakeSoap($config, $this->certificate);
    $event = infoEmpregadorEvent($config, $this->certificate);

    $request = fakeSoapRequest($tools->enviarLoteEventos(1, [$event]));

    expect($request['body'])->toContain('<nrInsc>99999999</nrInsc>');
});

it('encontra os schemas do pacote 1.6-Alfa', function () {
    $path = dirname(__DIR__, 2) . '/schemes/comunicacao/v1_6_0/';

    $list = XsdSeeker::seek($path);

    expect($list['EnvioLoteEventos']['name'])->toBe('EnvioLoteEventos-v1_1_1.xsd')
        ->and($list['RetornoEvento']['name'])->toBe('RetornoEvento-v1_3_0.xsd');
});
```

- [x] **Step 4: rodar e confirmar que falham pelo motivo certo**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/ComunicacaoTest.php`
Esperado: os testes com 1.6.0 FALHAM (pasta `v1_6_0` não existe: "Schemas não localizados" ou
`ValidatorException` "Failed to locate the main schema", porque o `XsdSeeker::$list` ainda
herda o estado de chamadas anteriores); `recusa ... 1.5.0` PASSA (pattern `\d{8,15}` do
`nrInsc` do lote: prova que hoje o alfanumérico é barrado); `encontra os schemas` FALHA no
`RetornoEvento` (vem `v1_2_1` herdado ou vazio).

- [x] **Step 5: copiar o pacote**

```bash
ls "$ALFA" | wc -l    # esperado: 17
mkdir -p schemes/comunicacao/v1_6_0 && cp "$ALFA"/*.xsd "$ALFA"/*.wsdl schemes/comunicacao/v1_6_0/
ls schemes/comunicacao/v1_6_0 | wc -l    # esperado: 17
```

- [x] **Step 6: rodar e confirmar que passam**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/ComunicacaoTest.php`
Esperado: 4 testes PASSAM.

- [x] **Step 7: padrão do projeto vira 1.6.0**

```bash
sed -i "s/'serviceVersion' => '1.5.0'/'serviceVersion' => '1.6.0'/" tests/ESocialTestCase.php examples/Fake/v_S_01_03_00/*.php
grep -c "'serviceVersion' => '1.6.0'" tests/ESocialTestCase.php    # 1
grep -L "'serviceVersion' => '1.6.0'" examples/Fake/v_S_01_03_00/*.php    # vazio
sed -i 's#(config S.1.3.0 / serviço 1.5.0)#(config S.1.3.0 / serviço 1.6.0)#' tests/Pest.php
grep -c "serviço 1.6.0" tests/Pest.php    # 1
sed -i "10s/'serviceVersion' => '1.1.0',/'serviceVersion' => '1.6.0',/" docs/Config.md
sed -n 10p docs/Config.md    # 'serviceVersion' => '1.6.0',//versão do webservice
```

Em `ESOCIAL-VERSION.json`, trocar só o valor (a chave `communication` também aparece em
`_comments`; altere a linha `"communication": "1.5.0",`):

```json
  "communication": "1.6.0-Alfa",
```

- [x] **Step 8: rodar a suíte inteira**

Rode: `composer test`
Esperado: nenhum `failed` (só `passed` e os `deprecated` do `$certificate` já conhecidos). O teste do
`XsdSeeker` que espera `RetornoEvento-v1_2_1.xsd` lê `v1_5_0` explicitamente e continua passando.

- [x] **Step 9: exemplos válidos no XSD**

Rode: `php .claude/skills/update-esocial/scripts/validate.php`
Esperado: `40/40 exemplos válidos no XSD de v_S_01_03_00`.

---

### Task 2: check.py reconhece a versão Alfa

**Arquivos:**
- Alterar: `.claude/skills/update-esocial/scripts/check.py` (função `parse`, cálculo de `comm` em `main`)
- Criar: `.claude/skills/update-esocial/scripts/test_check.py`

**Depende de:** nada

**Contrato que esta task publica:** `check.comm_key(v: str) -> tuple`,
`check.comm_is_news(published: list, applied: str) -> bool`.

- [x] **Step 1: escrever os testes que falham**

Criar `.claude/skills/update-esocial/scripts/test_check.py`:

```python
"""Testes do check.py sem rede: a página é um trecho fixo do Portal do eSocial."""
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import check  # noqa: E402

PAGE = (
    '<a href="https://www.gov.br/esocial/pt-br/documentacao-tecnica/manuais/2026-10-26_esquemas_xsd_v_s_01_03_00-1.zip">XSD</a>'
    '<a class="outstanding-link" href="https://www.gov.br/esocial/pt-br/documentacao-tecnica/manuais/pacote-de-comunicacao-esocial-v1-6.zip">Pacote de Comunicação eSocial v.1.6</a>'
    '<a class="outstanding-link" href="https://www.gov.br/esocial/pt-br/documentacao-tecnica/manuais/pacote-de-comunicacao-esocial-v1-6-alfa.zip">Pacote de Comunicação eSocial v.1.6 - Alfa</a>'
)


class ComunicacaoTest(unittest.TestCase):
    def test_lista_a_versao_base_e_a_alfa(self):
        found = check.parse(PAGE)

        self.assertEqual(sorted(found['comm']), ['1.6', '1.6-Alfa'])

    def test_alfa_aplicada_nao_e_novidade_mesmo_com_a_base_publicada(self):
        published = check.parse(PAGE)['comm']

        news = check.comm_is_news(published, '1.6.0-Alfa')

        self.assertFalse(news)

    def test_versao_anterior_aplicada_e_novidade(self):
        published = check.parse(PAGE)['comm']

        news = check.comm_is_news(published, '1.5.0')

        self.assertTrue(news)

    def test_versao_maior_publicada_e_novidade(self):
        published = ['1.6-Alfa', '1.7']

        news = check.comm_is_news(published, '1.6.0-Alfa')

        self.assertTrue(news)


if __name__ == '__main__':
    unittest.main()
```

- [x] **Step 2: rodar e confirmar que falham**

Rode: `python3 -m unittest discover -s .claude/skills/update-esocial/scripts -p 'test_*.py' -v`
Esperado: `test_lista_a_versao_base_e_a_alfa` FALHA (hoje vem `['1.', '1.6']`); os outros dão ERRO
com `AttributeError: module 'check' has no attribute 'comm_is_news'`.

- [x] **Step 3: implementar**

Em `check.py`, dentro de `parse`, trocar a linha do `comm = sorted(...)` por:

```python
    comm = sorted({m.group(1) + ('-Alfa' if m.group(2) else '')
                   for t, _ in items
                   for m in [re.search(r'Pacote de Comunica[çc][ãa]o eSocial v\.?\s*(\d+(?:\.\d+)*)(\s*-\s*Alfa)?', t)] if m},
                  key=lambda v: comm_key(v))
```

Acrescentar depois de `def ver(...)`:

```python
def comm_key(v: str) -> tuple:
    """Ordem do Pacote de Comunicação: 1.6 < 1.6-Alfa (superconjunto mais novo) < 1.7."""
    nums = [int(x) for x in re.findall(r'\d+', v.split('-')[0])]
    return tuple((nums + [0, 0, 0])[:3]) + (1 if 'alfa' in v.lower() else 0,)


def comm_is_news(published: list, applied: str) -> bool:
    """Há pacote publicado acima do aplicado (ver comm_key)."""
    return bool(published) and max(comm_key(v) for v in published) > comm_key(applied)
```

Em `main`, trocar o bloco da comunicação por:

```python
    comm = max(found['comm'], key=comm_key) if found['comm'] else '?'
    print(f'Comunicação   aplicada {state["communication"]} | publicada {comm}')
    has_news |= comm_is_news(found['comm'], state['communication'])
```

- [x] **Step 4: rodar e confirmar que passam**

Rode: `python3 -m unittest discover -s .claude/skills/update-esocial/scripts -p 'test_*.py' -v`
Esperado: 4 testes OK.

- [x] **Step 5: conferir contra o portal**

Rode: `python3 .claude/skills/update-esocial/scripts/check.py`
Esperado: linha `Comunicação   aplicada 1.6.0-Alfa | publicada 1.6-Alfa` (depois da Task 1) e
a comunicação não conta como novidade (o exit continua 10 por causa dos pacotes XSD).

---

### Task 3: TLS ligado por padrão no SoapCurl

**Arquivos:**
- Criar: `tests/Unit/SoapCurlTest.php`
- Alterar: `src/Common/Soap/SoapCurl.php:62-69` (send) e `:182-183` (wsdl); novo método
- Alterar: `src/Common/Soap/SoapBase.php:271-281` (`loadCA`)

**Depende de:** nada

**Contrato que esta task publica:** `SoapCurl::securityOptions(): array` (chaves `CURLOPT_*`).

- [x] **Step 1: escrever os testes que falham**

Criar `tests/Unit/SoapCurlTest.php`:

```php
<?php

use NFePHP\eSocial\Common\Soap\SoapCurl;

it('verifica o certificado e o nome do servidor por padrão', function () {
    $soap = new SoapCurl($this->certificate);

    $options = $soap->securityOptions();

    expect($options)->toBe([CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
});

it('usa o bundle de CAs informado no lugar do store do sistema', function () {
    $soap = new SoapCurl($this->certificate);
    $bundle = dirname(__DIR__, 2) . '/console/icpbrasilv5.crt';
    $soap->loadCA($bundle);

    $options = $soap->securityOptions();

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
```

- [x] **Step 2: rodar e confirmar que falham**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/SoapCurlTest.php`
Esperado: 3 testes com "Call to undefined method ...securityOptions()"; `recusa bundle` FALHA
com "Failed asserting that exception of type InvalidArgumentException is thrown".

- [x] **Step 3: `loadCA` recusa arquivo inexistente**

Em `src/Common/Soap/SoapBase.php`, substituir:

```php
    /**
     * Load path to CA and enable to use on SOAP
     *
     * @param string $capath
     */
    public function loadCA($capath)
    {
        if (is_file($capath)) {
            $this->casefaz = $capath;
        }
    }
```

por:

```php
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
```

- [x] **Step 4: `securityOptions()` e uso em `send()`/`wsdl()`**

Em `src/Common/Soap/SoapCurl.php`, no `send()`, substituir:

```php
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($oCurl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            if (! $this->disablesec) {
                curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, 2);
                if (is_file($this->casefaz ?? '')) {
                    curl_setopt($oCurl, CURLOPT_CAINFO, $this->casefaz);
                }
            }
```

por:

```php
            curl_setopt_array($oCurl, $this->securityOptions());
            curl_setopt($oCurl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
```

No `wsdl()`, substituir:

```php
        curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, 0);
```

por:

```php
        curl_setopt_array($oCurl, $this->securityOptions());
```

Acrescentar o método logo antes de `private function setClientCertificate`:

```php
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
```

- [x] **Step 5: rodar e confirmar que passam**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/SoapCurlTest.php`
Esperado: 4 testes PASSAM.

- [x] **Step 6: restrição de TLS**

Rode: `grep -n "curl_setopt(.*SSL_VERIFY" src/Common/Soap/SoapCurl.php`
Esperado: nenhuma linha. (É essa checagem que garante que `wsdl()` também usa
`securityOptions()`: não há como ler de volta as opções de um handle cURL no PHP.)

---

### Task 4: Checagem de rede do TLS (grupo `network`)

**Arquivos:**
- Criar: `tests/Unit/Network/TlsHandshakeTest.php`
- Alterar: `composer.json:56` (script `test`)

**Depende de:** Task 3

- [x] **Step 1: excluir o grupo `network` da suíte padrão**

Em `composer.json`, trocar a linha do script:

```json
        "test": "@php -d xdebug.mode=off vendor/bin/pest --exclude-group=network",
```

- [x] **Step 2: escrever o teste**

Criar `tests/Unit/Network/TlsHandshakeTest.php`:

```php
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
```

- [x] **Step 3: provar que o teste acusa verificação quebrada**

Rode:

```bash
php -d xdebug.mode=off -d curl.cainfo=/dev/null vendor/bin/pest --group=network
```

Esperado: FALHA nos 4 com `curl_errno` diferente de 0 (60 "SSL certificate problem" ou 77
"error setting certificate verify locations"), provando que a verificação está ligada.

- [x] **Step 4: rodar com o store normal**

Rode: `php -d xdebug.mode=off vendor/bin/pest --group=network`
Esperado: 4 PASSAM.

- [x] **Step 5: confirmar que a suíte padrão não roda o grupo**

Rode: `composer test`
Esperado: verde e sem nenhum teste "handshake" na saída.

---

### Task 5: Id único no processo (FactoryId)

**Arquivos:**
- Alterar: `src/Common/FactoryId.php` (classe inteira)
- Alterar: `tests/ESocialTestCase.php` (`setUp`)
- Alterar: `tests/Unit/FactoryIdTest.php` (acrescentar testes)
- Alterar: `tests/Unit/ToolsTest.php` (acrescentar teste de lote)

**Depende de:** Task 1 (helpers em `tests/Pest.php`)

**Contrato que esta task publica:** `FactoryId::build($tpInsc, $nrInsc, ?DateTime $date = null, $sequential = null): string`,
`FactoryId::reset(): void`.

- [x] **Step 1: escrever os testes que falham**

Acrescentar em `tests/Unit/FactoryIdTest.php`:

```php
it('usa o próximo sequencial livre quando o Id já foi gerado no processo', function () {
    $date = new DateTime('2017-08-03 10:37:00');
    FactoryId::build(1, '99999999', $date, 1);

    $id = FactoryId::build(1, '99999999', $date, 1);

    expect($id)->toBe('ID1' . '99999999000000' . '20170803103700' . '00002');
});

it('mantém sequenciais diferentes do mesmo segundo', function () {
    $date = new DateTime('2017-08-03 10:37:00');
    FactoryId::build(1, '99999999', $date, 1);

    $id = FactoryId::build(1, '99999999', $date, 2);

    expect(substr($id, -5))->toBe('00002');
});

it('gera Ids diferentes sem sequencial no mesmo segundo', function () {
    $date = new DateTime('2017-08-03 10:37:00');
    $first = FactoryId::build(1, '99999999', $date);

    $second = FactoryId::build(1, '99999999', $date);

    expect($second)->not->toBe($first);
});

it('volta ao sequencial 00001 depois do 99999 ocupado', function () {
    $date = new DateTime('2017-08-03 10:37:00');
    FactoryId::build(1, '99999999', $date, 99999);

    $id = FactoryId::build(1, '99999999', $date, 99999);

    expect(substr($id, -5))->toBe('00001');
});

it('recusa quando os 99999 sequenciais do segundo já foram usados', function () {
    $date = new DateTime('2017-08-03 10:37:00');
    for ($sequential = 1; $sequential <= 99999; $sequential++) {
        FactoryId::build(1, '99999999', $date, $sequential);
    }

    FactoryId::build(1, '99999999', $date, 1);
})->throws(RuntimeException::class, 'sequenciais');

it('esquece os Ids gerados depois do reset', function () {
    $date = new DateTime('2017-08-03 10:37:00');
    FactoryId::build(1, '99999999', $date, 1);
    FactoryId::reset();

    $id = FactoryId::build(1, '99999999', $date, 1);

    expect(substr($id, -5))->toBe('00001');
});
```

Acrescentar em `tests/Unit/ToolsTest.php`, dentro do `describe('envio de lote de eventos', ...)`:

```php
    it('envia no mesmo lote dois eventos iguais montados no mesmo processo, com Ids diferentes', function () {
        $tools = toolsWithFakeSoap($this->configJson, $this->certificate);
        $first = infoEmpregadorEvent($this->configJson, $this->certificate);
        $second = infoEmpregadorEvent($this->configJson, $this->certificate);

        $request = fakeSoapRequest($tools->enviarLoteEventos(Tools::EVT_INICIAIS, [$first, $second]));

        expect($second->evtid)->not->toBe($first->evtid)
            ->and($request['body'])->toContain("<evento Id=\"{$first->evtid}\">")
            ->and($request['body'])->toContain("<evento Id=\"{$second->evtid}\">");
    });
```

- [x] **Step 2: rodar e confirmar que falham**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/FactoryIdTest.php tests/Unit/ToolsTest.php`
Esperado: FALHAM "próximo sequencial livre" (vem `00001`), "volta ao 00001", "recusa" (exceção
não lançada), "reset" (método indefinido) e o teste de lote (Ids iguais). "sequenciais
diferentes" e "sem sequencial" podem passar já hoje.

- [x] **Step 3: implementar o FactoryId**

Substituir o conteúdo de `src/Common/FactoryId.php` por:

```php
<?php

namespace NFePHP\eSocial\Common;

use DateTime;

/**
 * Class FactoryId
 *
 * Id do evento: "ID" + tpInsc + nrInsc (completado com zeros à direita até 14) +
 * AAAAMMDDHHMMSS + sequencial de 5 dígitos.
 *
 * Os Ids gerados ficam registrados no processo: se o mesmo empregador, segundo e sequencial se
 * repetirem, usa o próximo sequencial livre. Por isso o Id de um evento é o que está em
 * $evento->evtid, e não deve ser recalculado por fora. Entre processos diferentes não há como
 * garantir unicidade: quem roda vários workers para o mesmo empregador aloca o sequencial.
 */
class FactoryId
{
    /**
     * Quantas bases (empregador + segundo) o registro guarda antes de descartar as mais antigas.
     */
    const MAX_BASES = 1000;

    /**
     * @var array<string, array<int, bool>> base => sequenciais já usados
     */
    private static $issued = [];

    /**
     * @param int $tpInsc
     * @param string $nrInsc
     * @param DateTime|null $date
     * @param int|null $sequential
     * @return string
     */
    public static function build(
        $tpInsc,
        $nrInsc,
        ?DateTime $date = null,
        $sequential = null
    ) {
        if (empty($date)) {
            $date = new DateTime();
        }
        $base = "ID"
            . $tpInsc
            . str_pad($nrInsc, 14, '0', STR_PAD_RIGHT)
            . $date->format('YmdHis');
        $sequential = empty($sequential) ? random_int(1, 99999) : (int) $sequential;
        $sequential = self::reserve($base, $sequential);

        return $base . str_pad($sequential, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Esquece os Ids gerados neste processo.
     */
    public static function reset()
    {
        self::$issued = [];
    }

    /**
     * @param string $base
     * @param int $sequential
     * @return int sequencial reservado
     */
    private static function reserve($base, $sequential)
    {
        $used = self::$issued[$base] ?? [];
        if (count($used) >= 99999) {
            throw new \RuntimeException(
                "Todos os 99999 sequenciais de $base já foram usados neste processo."
            );
        }
        while (isset($used[$sequential])) {
            $sequential = $sequential >= 99999 ? 1 : $sequential + 1;
        }
        // libera a cópia: sem isto cada escrita abaixo duplica o array (copy-on-write) e o
        // registro vira O(n²)
        unset($used);
        if (!isset(self::$issued[$base]) && count(self::$issued) >= self::MAX_BASES) {
            array_shift(self::$issued);
        }
        self::$issued[$base][$sequential] = true;

        return $sequential;
    }
}
```


- [x] **Step 4: isolar os testes**

Em `tests/ESocialTestCase.php`, acrescentar `use NFePHP\eSocial\Common\FactoryId;` e, logo após
`parent::setUp();`:

```php
        // Os Ids gerados ficam registrados no processo; cada teste começa sem nenhum.
        FactoryId::reset();
```

- [x] **Step 5: rodar e confirmar que passam**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/FactoryIdTest.php tests/Unit/ToolsTest.php`
Esperado: tudo PASSA (o teste dos 99999 leva menos de 2 s).

- [x] **Step 6: suíte inteira**

Rode: `composer test`
Esperado: verde (os testes que esperam `...00001` continuam passando por causa do `reset()`).

---

### Task 6: Grupos conforme o manual e erros claros de grupo

**Arquivos:**
- Alterar: `src/Common/Tools.php:79-154` (`$eventGroup` e `$grupos`)
- Alterar: `src/Tools.php` (`enviarLoteEventos`, `enviarLoteXmlEventos`, `getIdFromXml`, novo `normalizeGrupo`)
- Criar: `tests/Unit/GruposTest.php`

**Depende de:** Task 1 (helpers)

**Contrato que esta task publica:** grupos aceitos `1|2|3` como `int` ou string numérica.

- [x] **Step 1: escrever os testes que falham**

Criar `tests/Unit/GruposTest.php`:

```php
<?php

use NFePHP\eSocial\Event;
use NFePHP\eSocial\Tools;

/*
 * Grupos do lote (MOD 7.5.7 e MOS S-1.3, seção 10): 1 = tabelas, 2 = não periódicos,
 * 3 = periódicos. Os XML abaixo são mínimos: o lote só lê o nome e o Id do evento e não
 * revalida o conteúdo do evento (xs:any processContents="skip").
 */

function minimalEventXml(string $eventName): string
{
    return "<eSocial><$eventName Id=\"ID1999999990000002017080310370000001\"/></eSocial>";
}

function acceptedGroups(Tools $tools, string $eventName): array
{
    $groups = [];
    foreach ([1, 2, 3] as $group) {
        try {
            $tools->enviarLoteXmlEventos($group, [minimalEventXml($eventName)]);
            $groups[] = $group;
        } catch (RuntimeException $e) {
        }
    }

    return $groups;
}

dataset('eventos enviados pelo empregador', function () {
    foreach (Event::$codes as $eventName => $code) {
        if (!in_array($code, ['S-8200', 'S-8299'], true)) {
            yield "$code $eventName" => [$eventName, $code];
        }
    }
});

it('aceita cada evento do empregador em exatamente um grupo', function (string $eventName, string $code) {
    $tools = toolsWithFakeSoap($this->configJson, $this->certificate);

    $groups = acceptedGroups($tools, $eventName);

    $expected = in_array($code, ['S-1000', 'S-1005', 'S-1010', 'S-1020', 'S-1070'], true) ? [1]
        : (preg_match('/^S-(12|13)\d\d$/', $code) ? [3] : [2]);
    expect($groups)->toBe($expected);
})->with('eventos enviados pelo empregador');

it('aceita o grupo informado como texto', function () {
    $tools = toolsWithFakeSoap($this->configJson, $this->certificate);
    $xml = infoEmpregadorEvent($this->configJson, $this->certificate)->toXML();

    $request = fakeSoapRequest($tools->enviarLoteXmlEventos('1', [$xml]));

    expect($request['body'])->toContain('<envioLoteEventos grupo="1">');
});

it('recusa grupo que não existe', function ($group) {
    $tools = toolsWithFakeSoap($this->configJson, $this->certificate);
    $event = infoEmpregadorEvent($this->configJson, $this->certificate);

    $tools->enviarLoteEventos($group, [$event]);
})->with([0, 4, 'x'])->throws(InvalidArgumentException::class, 'use 1 (tabelas), 2 (não periódicos) ou 3 (periódicos)');

it('recusa em lote o evento que o empregador não envia', function () {
    $tools = toolsWithFakeSoap($this->configJson, $this->certificate);
    $xml = minimalEventXml('evtAnotJud');

    $tools->enviarLoteXmlEventos(2, [$xml]);
})->throws(RuntimeException::class, 'O evento S-8200 não pode ser enviado em lote pelo empregador.');

it('explica quando o grupo declarado difere do grupo do evento', function () {
    $tools = toolsWithFakeSoap($this->configJson, $this->certificate);
    $xml = infoEmpregadorEvent($this->configJson, $this->certificate)->toXML();

    $tools->enviarLoteXmlEventos(2, [$xml]);
})->throws(RuntimeException::class, 'não pode diferir do grupo dos eventos');
```

Nota sobre o critério do `$expected`: os periódicos do S-1.3 são exatamente os S-12xx e S-1298/
S-1299 (todos começam com `S-12`); nenhum `S-13xx` existe no `Event::$codes`, então a regex
`S-(12|13)\d\d` não inclui nada a mais.

- [x] **Step 2: rodar e confirmar que falham**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/GruposTest.php`
Esperado: o dataset passa para a maioria (a tabela atual já tem esses eventos) — confirme que
nenhum falha por evento em dois grupos; FALHAM "grupo como texto" (exceção "não pode
diferir"), "grupo que não existe" (TypeError/warning em vez de InvalidArgumentException),
"evento que o empregador não envia" (mensagem errada) e "difere do grupo" (texto "diferirdo").

- [x] **Step 3: tabela de grupos**

Em `src/Common/Tools.php`, substituir o bloco das linhas 79-154 (de `protected $eventGroup = [`
até o `];` que fecha `$grupos`, logo antes de `protected $groupbycode = [];`) por:

```php
    protected $eventGroup = [
        1 => 'EVENTOS DE TABELAS',
        2 => 'EVENTOS NÃO PERIÓDICOS',
        3 => 'EVENTOS PERIÓDICOS',
    ];

    /**
     * Eventos que o empregador envia, por grupo do lote (MOD 7.5.7; MOS S-1.3, seção 10).
     * S-5xxx são retornos do eSocial e S-8200/S-8299 são do Judiciário: não vão em lote.
     * @var array
     */
    protected $grupos = [
        1 => [
            'S-1000', 'S-1005', 'S-1010', 'S-1020', 'S-1070',
        ],
        2 => [
            'S-2190', 'S-2200', 'S-2205', 'S-2206', 'S-2210', 'S-2220', 'S-2221', 'S-2230',
            'S-2231', 'S-2240', 'S-2298', 'S-2299', 'S-2300', 'S-2306', 'S-2399', 'S-2400',
            'S-2405', 'S-2410', 'S-2416', 'S-2418', 'S-2420', 'S-2500', 'S-2501', 'S-2555',
            'S-3000', 'S-3500',
        ],
        3 => [
            'S-1200', 'S-1202', 'S-1207', 'S-1210', 'S-1260', 'S-1270', 'S-1280', 'S-1298',
            'S-1299',
        ],
    ];
```

Antes de substituir, confira que as linhas 84-87 (entre `$eventGroup` e `$grupos`) só têm o
docblock de `$grupos`; se tiverem outra coisa, mantenha.

- [x] **Step 4: normalizar o grupo e corrigir as mensagens**

Em `src/Tools.php`, acrescentar antes de `protected function checkCertificate`:

```php
    /**
     * Aceita o grupo como int ou texto numérico e devolve o int (1, 2 ou 3).
     * @param int|string $grupo
     * @return int
     * @throws InvalidArgumentException
     */
    protected function normalizeGrupo($grupo)
    {
        if ((is_int($grupo) || (is_string($grupo) && ctype_digit($grupo)))
            && isset($this->grupos[(int) $grupo])
        ) {
            return (int) $grupo;
        }
        throw new InvalidArgumentException(
            "Grupo inválido [$grupo]: use 1 (tabelas), 2 (não periódicos) ou 3 (periódicos)."
        );
    }
```

Em `enviarLoteEventos`, logo depois do `if (empty($eventos)) { return ''; }`, acrescentar:

```php
        $grupo = $this->normalizeGrupo($grupo);
```

Em `enviarLoteXmlEventos`, logo depois do `if (empty($eventos)) { return ''; }`, acrescentar a
mesma linha. No mesmo método, substituir:

```php
            if (empty($grp)) {
                $grp = $resp['grupo'];
            }
```

por:

```php
            if (empty($resp['grupo'])) {
                throw new \RuntimeException(
                    "O evento {$resp['codigo']} não pode ser enviado em lote pelo empregador."
                );
            }
            if (empty($grp)) {
                $grp = $resp['grupo'];
            }
```

e substituir:

```php
                throw new \RuntimeException('O grupo correto deve ser declarado e não pode diferir'
                    . 'do grupo dos eventos');
```

por:

```php
                throw new \RuntimeException('O grupo correto deve ser declarado e não pode diferir '
                    . 'do grupo dos eventos');
```

Em `getIdFromXml`, substituir:

```php
        $id = null;
        $grupo = null;
```

por:

```php
        $id = null;
        $grupo = null;
        $codigo = null;
```

e:

```php
                $id = $tag->getAttribute('Id');
                $code = $possibles[$tagname];
                $grupo = $this->groupbycode[$code];
                break;
            }
        }
        return ['id' => $id, 'grupo' => $grupo];
```

por:

```php
                $id = $tag->getAttribute('Id');
                $codigo = $possibles[$tagname];
                $grupo = $this->groupbycode[$codigo] ?? null;
                break;
            }
        }
        return ['id' => $id, 'grupo' => $grupo, 'codigo' => $codigo];
```

Atualize o `@return` do docblock de `getIdFromXml` se ele listar as chaves.

- [x] **Step 5: rodar e confirmar que passam**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/GruposTest.php tests/Unit/ToolsTest.php`
Esperado: tudo PASSA (`ToolsTest` "recusa evento de outro grupo" continua com a mensagem
"O evento S-1000 não pertence a este grupo").

---

### Task 7: XsdSeeker sem estado herdado

**Arquivos:**
- Alterar: `src/Common/XsdSeeker.php` (`seek`)
- Alterar: `tests/Unit/XsdSeekerTest.php:26-32` (teste da pasta inexistente) e novo teste

**Depende de:** nada

- [x] **Step 1: escrever/reescrever os testes**

Em `tests/Unit/XsdSeekerTest.php`, substituir o teste "devolve a lista sem alteração quando a
pasta não existe" por:

```php
it('devolve todas as operações sem versão quando a pasta não existe', function () {
    XsdSeeker::seek(dirname(__DIR__, 2) . '/schemes/comunicacao/v1_5_0/');

    $list = XsdSeeker::seek('/caminho/que/nao/existe/');

    expect(array_unique(array_column($list, 'version')))->toBe([''])
        ->and(array_keys($list))->toBe(array_keys(XsdSeeker::$list));
});

it('não herda versões de uma pasta lida antes', function () {
    XsdSeeker::seek(dirname(__DIR__, 2) . '/schemes/comunicacao/v1_5_0/');

    $list = XsdSeeker::seek(dirname(__DIR__, 2) . '/schemes/comunicacao/v1_1_0/');

    expect($list['ConsultaIdentificadoresEventosEmpregador']['version'])->toBe('')
        ->and($list['EnvioLoteEventos']['name'])->toBe('EnvioLoteEventos-v1_1_0.xsd');
});
```

- [x] **Step 2: rodar e confirmar que falham**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/XsdSeekerTest.php`
Esperado: os 2 FALHAM (versões da `v1_5_0` sobram na lista).

- [x] **Step 3: implementar**

Em `src/Common/XsdSeeker.php`, substituir o método `seek` por:

```php
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
```

Atenção: `getVersion` hoje é chamado para cada arquivo antes de comparar a chave; no código novo
só é chamado quando a chave casa, o que evita erro com arquivos sem `-` no nome
(`xmldsig-core-schema.xsd` tem hífen e não casa com nenhuma chave).

- [x] **Step 4: rodar e confirmar que passam**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/XsdSeekerTest.php tests/Unit/ComunicacaoTest.php`
Esperado: tudo PASSA.

---

### Task 8: Documentação e verificação final

**Arquivos:**
- Alterar: `AGENTS.md` (seção "Regras")

**Depende de:** Tasks 1-7

- [x] **Step 1: AGENTS.md**

Em `AGENTS.md`, na seção "## Regras", depois da linha que começa com
"- Certificado só pela memória", acrescentar:

```markdown
- TLS do webservice verificado por padrão (`SoapCurl::securityOptions()`, store do sistema; os
  hosts do eSocial usam a raiz pública GlobalSign Root R46). `loadCA()` **substitui** o store;
  `disableSecurity(true)` desliga a verificação e só serve para ambiente controlado.
  Checagem com rede: `vendor/bin/pest --group=network` (fora do `composer test`).
- Pacote de comunicação padrão: `serviceVersion '1.6.0'` (pasta `v1_6_0` = 1.6-Alfa, aceita
  CNPJ alfanumérico). Com `'1.5.0'` o lote de CNPJ alfanumérico é recusado localmente.
- O Id do evento é `$evento->evtid`: o `FactoryId` evita repetição no mesmo processo trocando o
  sequencial. Entre processos (vários workers do mesmo empregador) quem chama aloca o
  `sequencial`.
```

No mapa do código do `AGENTS.md`, trocar a linha
`| \`schemes/comunicacao/v1_5_0/\` | XSD/WSDL de envio e consulta de lotes |` por
`| \`schemes/comunicacao/v1_6_0/\` | XSD/WSDL de envio e consulta de lotes (1.6-Alfa; \`v1_5_0\` mantido até o SP2) |`.
Em `ESOCIAL-VERSION.json`, atualizar `"updated_at"` (o valor, não o de `_comments`) para a data do dia.

- [x] **Step 2: suíte Python**

Rode: `python3 -m unittest discover -s .claude/skills/update-esocial/scripts -p 'test_*.py'`
Esperado: `OK`.

- [x] **Step 3: suíte PHP**

Rode: `composer test`
Esperado: tudo verde.

- [x] **Step 4: exemplos**

Rode: `php .claude/skills/update-esocial/scripts/validate.php`
Esperado: `40/40`.

- [x] **Step 5: escopo do diff**

Rode: `git status --short && git diff --stat -- src/Factories jsonSchemes schemes/v_S_01_00_00 schemes/v_S_01_01_00 schemes/v_S_01_02_00 schemes/v_S_01_03_00`
Esperado: a segunda parte vazia; a primeira lista só os arquivos das Tasks 1-8 e
`schemes/comunicacao/v1_6_0/` novo.

---

## Ajustes durante a execução

- **2026-10-14 (planejamento)** — spec previa provar `wsdl()` "por um método de montagem
  compartilhado, sem rede": o PHP não permite ler de volta as opções de um handle cURL, então a
  prova é a restrição verificável 1 (nenhum `curl_setopt(...SSL_VERIFY...)` fora de
  `securityOptions()`), Task 3 step 6.
- **2026-10-14 (planejamento)** — o teste "cada código dos grupos está em `Event::$codes`" do
  spec não é observável sem ler propriedade protegida; a remoção dos códigos inexistentes
  (S-4000, extintos, S-5xxx) não tem efeito visível pela API (não existe evento montável com
  esses códigos). Fica coberto o sentido observável: cada evento do empregador em exatamente um
  grupo (Task 6).
- **2026-10-14 (execução, batch 1)** — juiz: `__pycache__/` do unittest ia para o git → apagado e
  incluído no `.gitignore`; teste "recusa ... 1.5.0" passou a exigir a mensagem do pattern do
  `nrInsc`; asserções de envio miram o cabeçalho do lote (`<envioLoteEventos grupo="1"><ideEmpregador>…`),
  não o trecho que também existe dentro do evento. Task 8 ganhou a correção do mapa do código do
  `AGENTS.md` e o `updated_at`.
- **2026-10-14 (execução, batch 2)** — o `grep -c` do step 3 da Task 5 saiu com código 1 (escape
  do `$`) e interrompeu a cadeia `&&`, então o `FactoryId::reset()` não entrou no `setUp` na
  primeira tentativa; os testes acusaram o vazamento do registro e a edição foi reaplicada.
- **2026-10-14 (execução, batch 3)** — juiz: `acceptedGroups()` relança `ValidatorException`
  (não mascara falha de validação como "sem grupo"); `normalizeGrupo()` mostra o tipo quando o
  grupo não é escalar; `AGENTS.md` ganhou a regra dos grupos. Fica para o SP2: alias
  `EVT_TABELAS` para a constante `EVT_INICIAIS` e validação de `sequencial` > 99999 no
  `FactoryId`.
