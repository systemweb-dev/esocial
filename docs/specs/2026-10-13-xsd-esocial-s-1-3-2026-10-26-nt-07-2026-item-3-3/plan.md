# Plano de implementação — XSD eSocial S-1.3 2026-10-26 (NT 07/2026 item 3.3)

[spec.md](spec.md)

> **Execução:** implementar task por task. Os steps usam checkbox (`- [ ]`) para acompanhar.
> Os dois modos de execução estão na seção "Execution Handoff" da skill `sw-plan`.

**Objetivo:** aplicar o pacote XSD S-1.3 de 2026-10-26 e suportar os campos novos de S-2500,
S-2299, S-2399 e S-2420 no montador `toNodeS130` e nos JSON schemas do S-1.3.

**Arquitetura:** os XSDs do pacote substituem `schemes/v_S_01_03_00/`. Cada evento afetado
ganha os campos novos só no método `toNodeS130` do seu trait (`src/Factories/Traits/`) e no
seu `.schema` em `jsonSchemes/v_S_01_03_00/`. Campos opcionais seguem o padrão
`addChild($pai, "Tag", $valor ?? null, false)`.

**Stack:** PHP 8.4, `nfephp-org/sped-common` (DOMImproved, Signer, Validator),
`justinrainbow/json-schema` (draft-03, `required` booleano), PHPUnit 10.

**Restrições verificáveis (do spec):**
| Restrição | Como o plano checa |
|---|---|
| `validate.php` passa com todos os exemplos do S-1.3 | Task 1 step 4 (baseline) e Task 6 step 5 |
| `phpunit` passa, incluindo os testes novos | Tasks 2–5 (step final) e Task 6 step 6 |
| Nenhuma mudança em `toNodeS100/S110/S120` nem fora dos arquivos do escopo | Task 6 step 7 |
| `compare_xsd.py` após a cópia: 0 arquivos diferentes | Task 1 step 3 |

**Convenções para quem executa:**
- Raiz do projeto: `/var/www/esocial`. Rode tudo a partir dela.
- Pacote já baixado e extraído em
  `PKG=/tmp/claude-1000/-var-www-esocial/b3c667c3-32b6-440c-9034-e207a90f4558/scratchpad/xsd-2026-10-26/x`
  (52 `.xsd`). Se a pasta não existir mais, baixe de novo numa pasta temporária **vazia**:
  `https://www.gov.br/esocial/pt-br/documentacao-tecnica/manuais/2026-10-26_esquemas_xsd_v_s_01_03_00-1.zip`.
- `Factory::propertiesToLower()` põe todas as chaves do JSON de entrada em minúsculo; os traits
  sempre leem `$this->std->chaveminuscula`.
- Com certificado (o de teste em `tests/fixtures/certs/`), `toXML()` assina **e valida no XSD**
  do evento — por isso o teste que chama `toXML()` já prova a validade no XSD.
- O JSON schema roda no construtor do evento; erro vira `\Exception` com mensagem
  `[caminho.da.propriedade] mensagem`.
- Não commitar em nenhuma task. Commit só no checkpoint, com o "sim" do usuário.
- Não reformatar arquivos: diffs mínimos.

**Ordem pelo risco:** a suposição de maior risco do spec (validador aceitar `enum: [9]` com
`null`) já foi testada no planejamento e **caiu** — ver "Ajustes durante a execução". A task 1
ataca a segunda: os exemplos atuais continuarem válidos no XSD novo.

---

### Task 1: Copiar os XSDs do pacote e medir o baseline

**Arquivos:**
- Alterar: `schemes/v_S_01_03_00/*.xsd` (sobrescrita pelos 52 do pacote)

**Depende de:** nada

- [x] **Step 1: conferir o pacote**

Rode: `ls "$PKG"/*.xsd | wc -l`
Esperado: `52`

- [x] **Step 2: copiar os XSDs**

```bash
cp "$PKG"/*.xsd schemes/v_S_01_03_00/
```

- [x] **Step 3: confirmar que o fork ficou igual ao pacote**

Rode: `python3 .claude/skills/update-esocial/scripts/compare_xsd.py schemes/v_S_01_03_00 "$PKG"`
Esperado: nenhum arquivo diferente (saída `0 arquivo(s) diferente(s)` e exit 0).

- [x] **Step 4: baseline dos exemplos no XSD novo**

Rode: `php .claude/skills/update-esocial/scripts/validate.php`
Esperado: todos `ok`. Se algum `Fake_s*` falhar, **pare**: é quebra causada pelo pacote fora
dos campos novos (ex.: data < 1890 em algum exemplo). Reporte o erro ao usuário antes de seguir.

- [x] **Step 5: baseline do PHPUnit**

Rode: `php -d xdebug.mode=off vendor/bin/phpunit`
Esperado: `OK, but there were issues!` com `Tests: 16` (as 6 deprecations já existiam).

---

### Task 2: S-2500 — nmSoc, nmCargo, nmFuncao, CBOFuncao, localTrabalho, tpRegTrab 3

**Arquivos:**
- Criar: `tests/Factories/EvtProcTrabTest.php`
- Alterar: `jsonSchemes/v_S_01_03_00/evtProcTrab.schema` (raiz após `dtnascto` ~l.120;
  `infocompl.properties` após `codcbo` ~l.188 e após `infoterm` ~l.367-382;
  `infovinc.tpregtrab` ~l.235-240)
- Alterar: `src/Factories/Traits/TraitS2500.php` (só `toNodeS130`: `dtNascto` ~l.1413-1418,
  `codCBO` ~l.1484-1489, fim de `infoCompl` ~l.1680-1681)

**Depende de:** Task 1

- [x] **Step 1: escrever os testes que falham**

Criar `tests/Factories/EvtProcTrabTest.php`:

```php
<?php

namespace NFePHP\eSocial\Tests\Factories;

use NFePHP\eSocial\Factories\EvtProcTrab;
use NFePHP\eSocial\Tests\ESocialTestCase;

class EvtProcTrabTest extends ESocialTestCase
{
    private function newPayload(?array $infoCompl = null): \stdClass
    {
        $contrato = [
            'tpcontr' => 6,
            'indcontr' => 'N',
            'indcateg' => 'N',
            'indnatativ' => 'N',
            'indmotdeslig' => 'N',
            'codcateg' => '101',
            'dtinicio' => '2022-11-05',
            'ideestab' => [
                'tpinsc' => 1,
                'nrinsc' => '12345678901234',
                'infovlr' => [
                    'compini' => '2023-10',
                    'compfim' => '2023-11',
                    'indreperc' => 1,
                    'indensd' => 'S',
                    'indenabono' => 'S',
                ],
            ],
        ];
        if ($infoCompl !== null) {
            $contrato['infocompl'] = $infoCompl;
        }
        $data = [
            'indretif' => 1,
            'origem' => 1,
            'nrproctrab' => '12345678901234567890',
            'infoprocjud' => [
                'dtsent' => '2022-12-03',
                'ufvara' => 'SP',
                'codmunic' => '3504808',
                'idvara' => '12',
            ],
            'cpftrab' => '12345678901',
            'infocontr' => [$contrato],
        ];

        return json_decode(json_encode($data));
    }

    private function newInfoComplWithAllNewFields(): array
    {
        return [
            'nmcargo' => 'Analista de Sistemas',
            'codcbo' => '212405',
            'nmfuncao' => 'Coordenador de Projetos',
            'cbofuncao' => '142105',
            'natatividade' => 1,
            'localtrabalho' => [
                'localtrabgeral' => [
                    'tpinsc' => 1,
                    'nrinsc' => '12345678000195',
                    'desccomp' => 'Sede',
                ],
                'localtempdom' => [
                    'dsclograd' => 'Rua Teste',
                    'nrlograd' => '10',
                    'cep' => '01311200',
                    'codmunic' => '3550308',
                    'uf' => 'SP',
                ],
            ],
        ];
    }

    private function signedDocument(\stdClass $std): \DOMDocument
    {
        // With a certificate, toXML() signs and validates against the event XSD.
        $event = new EvtProcTrab($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
        $document = new \DOMDocument();
        $this->assertTrue($document->loadXML($event->toXML()));
        $this->assertSame(1, $document->getElementsByTagName('Signature')->length);

        return $document;
    }

    private function childNames(\DOMElement $element): array
    {
        $names = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $names[] = $child->localName;
            }
        }

        return $names;
    }

    public function testNewFieldsAreEmittedInXsdOrder()
    {
        $std = $this->newPayload($this->newInfoComplWithAllNewFields());
        $std->nmsoc = 'Nome Social Teste';

        $document = $this->signedDocument($std);

        $this->assertSame('Nome Social Teste', $document->getElementsByTagName('nmSoc')->item(0)->textContent);
        $infoCompl = $document->getElementsByTagName('infoCompl')->item(0);
        $this->assertSame(
            ['nmCargo', 'codCBO', 'nmFuncao', 'CBOFuncao', 'natAtividade', 'localTrabalho'],
            $this->childNames($infoCompl)
        );
        $this->assertSame('142105', $document->getElementsByTagName('CBOFuncao')->item(0)->textContent);
        $localTrabalho = $document->getElementsByTagName('localTrabalho')->item(0);
        $this->assertSame(['localTrabGeral', 'localTempDom'], $this->childNames($localTrabalho));
        $this->assertSame(0, $document->getElementsByTagName('tpLograd')->length);
        $this->assertSame('3550308', $document->getElementsByTagName('codMunic')->item(1)->textContent);
    }

    public function testOmittedNewFieldsAreNotEmitted()
    {
        $std = $this->newPayload(['codcbo' => '212405']);

        $document = $this->signedDocument($std);

        $this->assertSame(['codCBO'], $this->childNames($document->getElementsByTagName('infoCompl')->item(0)));
        foreach (['nmSoc', 'nmCargo', 'nmFuncao', 'CBOFuncao', 'localTrabalho'] as $tag) {
            $this->assertSame(0, $document->getElementsByTagName($tag)->length, $tag);
        }
    }

    public function testLocalTrabGeralRejectsInscriptionTypeTwo()
    {
        $infoCompl = $this->newInfoComplWithAllNewFields();
        $infoCompl['localtrabalho']['localtrabgeral']['tpinsc'] = 2;
        $std = $this->newPayload($infoCompl);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('localtrabgeral.tpinsc');

        new EvtProcTrab($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
    }
}
```

Nota: `codMunic` aparece primeiro em `infoProcJud`, por isso o `item(1)` é o do `localTempDom`.

- [x] **Step 2: rodar e confirmar que falham pelo motivo certo**

Rode: `php -d xdebug.mode=off vendor/bin/phpunit tests/Factories/EvtProcTrabTest.php`
Esperado: `testNewFieldsAreEmittedInXsdOrder` FALHA (`nmSoc` não existe → erro em `item(0)->textContent`
de null, ou lista de filhos sem os campos novos); `testOmittedNewFieldsAreNotEmitted` PASSA;
`testLocalTrabGeralRejectsInscriptionTypeTwo` FALHA ("Failed asserting that exception of type
Exception is thrown" — hoje o schema não conhece `localtrabalho`).

- [x] **Step 3: JSON schema — `nmsoc` na raiz**

Em `jsonSchemes/v_S_01_03_00/evtProcTrab.schema`, substituir o bloco `dtnascto` da raiz:

```json
        "dtnascto": {
            "required": false,
            "type": ["string","null"],
            "$ref": "#/definitions/data"
        },
        "infocontr": {
```

por:

```json
        "dtnascto": {
            "required": false,
            "type": ["string","null"],
            "$ref": "#/definitions/data"
        },
        "nmsoc": {
            "required": false,
            "type": ["string","null"],
            "minLength": 2,
            "maxLength": 70
        },
        "infocontr": {
```

- [x] **Step 4: JSON schema — `nmcargo`, `nmfuncao`, `cbofuncao` em `infocompl`**

Substituir:

```json
                            "codcbo": {
                                "required": false,
                                "type": ["string","null"],
                                "pattern": "^[0-9]{6}$"
                            },
```

por:

```json
                            "nmcargo": {
                                "required": false,
                                "type": ["string","null"],
                                "minLength": 1,
                                "maxLength": 100
                            },
                            "codcbo": {
                                "required": false,
                                "type": ["string","null"],
                                "pattern": "^[0-9]{6}$"
                            },
                            "nmfuncao": {
                                "required": false,
                                "type": ["string","null"],
                                "minLength": 1,
                                "maxLength": 100
                            },
                            "cbofuncao": {
                                "required": false,
                                "type": ["string","null"],
                                "pattern": "^[0-9]{6}$"
                            },
```

- [x] **Step 5: JSON schema — `localtrabalho` em `infocompl`**

Substituir o fim do bloco `infoterm` de `infocompl`:

```json
                                    "mtvdesligtsv": {
                                        "required": false,
                                        "type": ["string","null"],
                                        "pattern": "^(01|02|03|04|05|06|99)$"
                                    }
                                }
                            }
                        }
                    },
                    "mudcategativ": {
```

por:

```json
                                    "mtvdesligtsv": {
                                        "required": false,
                                        "type": ["string","null"],
                                        "pattern": "^(01|02|03|04|05|06|99)$"
                                    }
                                }
                            },
                            "localtrabalho": {
                                "required": false,
                                "type": ["object","null"],
                                "properties": {
                                    "localtrabgeral": {
                                        "required": false,
                                        "type": ["object","null"],
                                        "properties": {
                                            "tpinsc": {
                                                "required": true,
                                                "type": "integer",
                                                "enum": [1, 3, 4]
                                            },
                                            "nrinsc": {
                                                "required": true,
                                                "type": "string",
                                                "pattern": "^([0-9]{12}|[A-Z0-9]{12}[0-9]{2})$"
                                            },
                                            "desccomp": {
                                                "required": false,
                                                "type": ["string","null"],
                                                "minLength": 1,
                                                "maxLength": 80
                                            }
                                        }
                                    },
                                    "localtempdom": {
                                        "required": false,
                                        "type": ["object","null"],
                                        "properties": {
                                            "tplograd": {
                                                "required": false,
                                                "type": ["string","null"],
                                                "minLength": 1,
                                                "maxLength": 4
                                            },
                                            "dsclograd": {
                                                "required": true,
                                                "type": "string",
                                                "minLength": 1,
                                                "maxLength": 100
                                            },
                                            "nrlograd": {
                                                "required": true,
                                                "type": "string",
                                                "minLength": 1,
                                                "maxLength": 10
                                            },
                                            "complemento": {
                                                "required": false,
                                                "type": ["string","null"],
                                                "minLength": 1,
                                                "maxLength": 30
                                            },
                                            "bairro": {
                                                "required": false,
                                                "type": ["string","null"],
                                                "minLength": 1,
                                                "maxLength": 90
                                            },
                                            "cep": {
                                                "required": true,
                                                "type": "string",
                                                "pattern": "^[0-9]{8}$"
                                            },
                                            "codmunic": {
                                                "required": true,
                                                "type": "string",
                                                "pattern": "^[0-9]{7}$"
                                            },
                                            "uf": {
                                                "required": true,
                                                "type": "string",
                                                "pattern": "^(AC|AL|AP|AM|BA|CE|DF|ES|GO|MA|MT|MS|MG|PA|PB|PR|PE|PI|RJ|RN|RS|RO|RR|SC|SP|SE|TO)$"
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    },
                    "mudcategativ": {
```

(`complemento` 1–30 confere com `TS_codigo_esocial` do pacote.)

- [x] **Step 6: JSON schema — `tpregtrab` aceita 3**

Em `infovinc.tpregtrab`, trocar `"maximum": 2` por `"maximum": 3` (só nesse bloco; o
`tpregprev` logo abaixo não muda):

```json
                                    "tpregtrab": {
                                        "required": true,
                                        "type": "integer",
                                        "minimum": 1,
                                        "maximum": 3
                                    },
```

- [x] **Step 7: montador — `nmSoc` em `toNodeS130`**

Em `TraitS2500::toNodeS130` (a partir da l.1268), logo depois do `addChild` de `dtNascto`
(~l.1413-1418), inserir:

```php
        $this->dom->addChild(
            $idetrab,
            "nmSoc",
            $this->std->nmsoc ?? null,
            false
        );
```

Use o `dtNascto` que vem **depois** da l.1268 (há outros nos métodos S100/S120).

- [x] **Step 8: montador — `nmCargo`, `nmFuncao`, `CBOFuncao`**

No mesmo método, substituir o `codCBO` de `infoCompl` (~l.1484-1489):

```php
                $this->dom->addChild(
                    $icom,
                    "codCBO",
                    $icl->codcbo ?? null,
                    false
                );
```

por:

```php
                $this->dom->addChild(
                    $icom,
                    "nmCargo",
                    $icl->nmcargo ?? null,
                    false
                );
                $this->dom->addChild(
                    $icom,
                    "codCBO",
                    $icl->codcbo ?? null,
                    false
                );
                $this->dom->addChild(
                    $icom,
                    "nmFuncao",
                    $icl->nmfuncao ?? null,
                    false
                );
                $this->dom->addChild(
                    $icom,
                    "CBOFuncao",
                    $icl->cbofuncao ?? null,
                    false
                );
```

- [x] **Step 9: montador — `localTrabalho`**

No mesmo método, no fim de `infoCompl` (~l.1679-1681), substituir:

```php
                    $icom->appendChild($infoTerm);
                }
                $infoctr->appendChild($icom);
```

por (atenção: há o mesmo trecho no S120 em ~l.1064-1066; edite o de ~l.1679):

```php
                    $icom->appendChild($infoTerm);
                }
                if (!empty($icl->localtrabalho)) {
                    $lt = $icl->localtrabalho;
                    $localTrabalho = $this->dom->createElement("localTrabalho");
                    if (!empty($lt->localtrabgeral)) {
                        $lg = $lt->localtrabgeral;
                        $localTrabGeral = $this->dom->createElement("localTrabGeral");
                        $this->dom->addChild(
                            $localTrabGeral,
                            "tpInsc",
                            $lg->tpinsc,
                            true
                        );
                        $this->dom->addChild(
                            $localTrabGeral,
                            "nrInsc",
                            $lg->nrinsc,
                            true
                        );
                        $this->dom->addChild(
                            $localTrabGeral,
                            "descComp",
                            $lg->desccomp ?? null,
                            false
                        );
                        $localTrabalho->appendChild($localTrabGeral);
                    }
                    if (!empty($lt->localtempdom)) {
                        $ld = $lt->localtempdom;
                        $localTempDom = $this->dom->createElement("localTempDom");
                        $this->dom->addChild(
                            $localTempDom,
                            "tpLograd",
                            $ld->tplograd ?? null,
                            false
                        );
                        $this->dom->addChild(
                            $localTempDom,
                            "dscLograd",
                            $ld->dsclograd,
                            true
                        );
                        $this->dom->addChild(
                            $localTempDom,
                            "nrLograd",
                            $ld->nrlograd,
                            true
                        );
                        $this->dom->addChild(
                            $localTempDom,
                            "complemento",
                            $ld->complemento ?? null,
                            false
                        );
                        $this->dom->addChild(
                            $localTempDom,
                            "bairro",
                            $ld->bairro ?? null,
                            false
                        );
                        $this->dom->addChild(
                            $localTempDom,
                            "cep",
                            $ld->cep,
                            true
                        );
                        $this->dom->addChild(
                            $localTempDom,
                            "codMunic",
                            $ld->codmunic,
                            true
                        );
                        $this->dom->addChild(
                            $localTempDom,
                            "uf",
                            $ld->uf,
                            true
                        );
                        $localTrabalho->appendChild($localTempDom);
                    }
                    $icom->appendChild($localTrabalho);
                }
                $infoctr->appendChild($icom);
```

- [x] **Step 10: rodar e confirmar que passam**

Rode: `php -d xdebug.mode=off vendor/bin/phpunit tests/Factories/EvtProcTrabTest.php`
Esperado: `OK` (3 testes; pode haver deprecations já existentes).

---

### Task 3: S-2299 — `mudancaCPF/indCPFDesvinc`

**Arquivos:**
- Criar: `tests/Factories/EvtDesligTest.php`
- Alterar: `jsonSchemes/v_S_01_03_00/evtDeslig.schema` (~l.148-158)
- Alterar: `src/Factories/Traits/TraitS2299.php` (só `toNodeS130`, bloco `mudancacpf` ~l.1758-1768)

**Depende de:** Task 1

- [x] **Step 1: escrever os testes que falham**

Criar `tests/Factories/EvtDesligTest.php`:

```php
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
```

- [x] **Step 2: rodar e confirmar que falham pelo motivo certo**

Rode: `php -d xdebug.mode=off vendor/bin/phpunit tests/Factories/EvtDesligTest.php`
Esperado: `testInformedIndCpfDesvincIsEmittedAfterNovoCpf` FALHA (último filho é `novoCPF`);
`testOmittedIndCpfDesvincIsNotEmitted` PASSA; `testIndCpfDesvincOtherThanNineIsRejected` FALHA
(exceção não lançada).

- [x] **Step 3: JSON schema**

Em `jsonSchemes/v_S_01_03_00/evtDeslig.schema`, substituir:

```json
        "mudancacpf": {
            "required": false,
            "type": ["object","null"],
            "properties": {
                "novocpf": {
                    "required": true,
                    "type": "string",
                    "pattern": "^[0-9]{11}$"
                }
            }
        },
```

por:

```json
        "mudancacpf": {
            "required": false,
            "type": ["object","null"],
            "properties": {
                "novocpf": {
                    "required": true,
                    "type": "string",
                    "pattern": "^[0-9]{11}$"
                },
                "indcpfdesvinc": {
                    "required": false,
                    "type": ["integer","null"],
                    "minimum": 9,
                    "maximum": 9
                }
            }
        },
```

- [x] **Step 4: montador**

Em `TraitS2299::toNodeS130` (a partir da l.1579), no bloco `mudancacpf` (~l.1758-1768),
substituir:

```php
            $this->dom->addChild(
                $mudancaCPF,
                "novoCPF",
                $mcpf->novocpf,
                true
            );
            $infoDeslig->appendChild($mudancaCPF);
```

por:

```php
            $this->dom->addChild(
                $mudancaCPF,
                "novoCPF",
                $mcpf->novocpf,
                true
            );
            $this->dom->addChild(
                $mudancaCPF,
                "indCPFDesvinc",
                $mcpf->indcpfdesvinc ?? null,
                false
            );
            $infoDeslig->appendChild($mudancaCPF);
```

O mesmo trecho existe nos métodos S100/S110/S120 (ex.: ~l.179); edite **só** o de ~l.1758.

- [x] **Step 5: rodar e confirmar que passam**

Rode: `php -d xdebug.mode=off vendor/bin/phpunit tests/Factories/EvtDesligTest.php`
Esperado: `OK` (3 testes).

---

### Task 4: S-2399 — `mudancaCPF/indCPFDesvinc`

**Arquivos:**
- Criar: `tests/Factories/EvtTSVTerminoTest.php`
- Alterar: `jsonSchemes/v_S_01_03_00/evtTSVTermino.schema` (~l.73-77)
- Alterar: `src/Factories/Traits/TraitS2399.php` (só `toNodeS130`, bloco `novocpf` ~l.677-686)

**Depende de:** Task 1

- [x] **Step 1: escrever os testes que falham**

Criar `tests/Factories/EvtTSVTerminoTest.php`:

```php
<?php

namespace NFePHP\eSocial\Tests\Factories;

use NFePHP\eSocial\Factories\EvtTSVTermino;
use NFePHP\eSocial\Tests\ESocialTestCase;

class EvtTSVTerminoTest extends ESocialTestCase
{
    private function newPayload(array $extra): \stdClass
    {
        $data = array_merge([
            'indretif' => 1,
            'cpftrab' => '12345678901',
            'matricula' => 'MAT906',
            'dtterm' => '2026-08-10',
        ], $extra);

        return json_decode(json_encode($data));
    }

    private function signedDocument(\stdClass $std): \DOMDocument
    {
        // With a certificate, toXML() signs and validates against the event XSD.
        $event = new EvtTSVTermino($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
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

    public function testIndCpfDesvincWithoutNovoCpfIsIgnored()
    {
        $document = $this->signedDocument($this->newPayload(['indcpfdesvinc' => 9]));

        $this->assertSame(0, $document->getElementsByTagName('mudancaCPF')->length);
        $this->assertSame(0, $document->getElementsByTagName('indCPFDesvinc')->length);
    }

    public function testIndCpfDesvincOtherThanNineIsRejected()
    {
        $std = $this->newPayload(['novocpf' => '10987654321', 'indcpfdesvinc' => 1]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('indcpfdesvinc');

        new EvtTSVTermino($this->configJson, $std, $this->certificate, '2026-08-01 10:00:00');
    }
}
```

- [x] **Step 2: rodar e confirmar que falham pelo motivo certo**

Rode: `php -d xdebug.mode=off vendor/bin/phpunit tests/Factories/EvtTSVTerminoTest.php`
Esperado: `testInformedIndCpfDesvincIsEmittedAfterNovoCpf` FALHA (último filho é `novoCPF`);
`testIndCpfDesvincOtherThanNineIsRejected` FALHA (exceção não lançada); os outros dois PASSAM.

- [x] **Step 3: JSON schema**

Em `jsonSchemes/v_S_01_03_00/evtTSVTermino.schema`, substituir:

```json
        "novocpf": {
            "required": false,
            "type": ["string","null"],
            "pattern": "^[0-9]{11}$"
        },
        "verbasresc": {
```

por:

```json
        "novocpf": {
            "required": false,
            "type": ["string","null"],
            "pattern": "^[0-9]{11}$"
        },
        "indcpfdesvinc": {
            "required": false,
            "type": ["integer","null"],
            "minimum": 9,
            "maximum": 9
        },
        "verbasresc": {
```

- [x] **Step 4: montador**

Em `TraitS2399::toNodeS130` (a partir da l.583), no bloco `novocpf` (~l.677-686), substituir:

```php
            $this->dom->addChild(
                $mudancaCPF,
                "novoCPF",
                $this->std->novocpf,
                true
            );
            $infoTSVTermino->appendChild($mudancaCPF);
```

por:

```php
            $this->dom->addChild(
                $mudancaCPF,
                "novoCPF",
                $this->std->novocpf,
                true
            );
            $this->dom->addChild(
                $mudancaCPF,
                "indCPFDesvinc",
                $this->std->indcpfdesvinc ?? null,
                false
            );
            $infoTSVTermino->appendChild($mudancaCPF);
```

O mesmo trecho existe em S100 (~l.104) e S110 (~l.383); edite **só** o de ~l.677.

- [x] **Step 5: rodar e confirmar que passam**

Rode: `php -d xdebug.mode=off vendor/bin/phpunit tests/Factories/EvtTSVTerminoTest.php`
Esperado: `OK` (4 testes).

---

### Task 5: S-2420 — `toNodeS130` próprio com `indCPFDesvinc`

**Arquivos:**
- Criar: `tests/Factories/EvtCdBenTermTest.php`
- Alterar: `jsonSchemes/v_S_01_03_00/evtCdBenTerm.schema` (~l.47-51, fim do arquivo)
- Alterar: `src/Factories/Traits/TraitS2420.php` (`toNodeS130`, l.113-119)

**Depende de:** Task 1

- [x] **Step 1: escrever os testes que falham**

Criar `tests/Factories/EvtCdBenTermTest.php`:

```php
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
```

- [x] **Step 2: rodar e confirmar que falham pelo motivo certo**

Rode: `php -d xdebug.mode=off vendor/bin/phpunit tests/Factories/EvtCdBenTermTest.php`
Esperado: `testInformedIndCpfDesvincIsEmittedAfterNovoCpf` FALHA (último filho é `novoCPF`);
`testIndCpfDesvincOtherThanNineIsRejected` FALHA; `testOmittedIndCpfDesvincIsNotEmitted` PASSA.

- [x] **Step 3: JSON schema**

Em `jsonSchemes/v_S_01_03_00/evtCdBenTerm.schema`, substituir o fim do arquivo:

```json
        "novocpf": {
            "required": false,
            "type": ["string","null"],
            "pattern": "^[0-9]{11}$"
        }
    }
}
```

por:

```json
        "novocpf": {
            "required": false,
            "type": ["string","null"],
            "pattern": "^[0-9]{11}$"
        },
        "indcpfdesvinc": {
            "required": false,
            "type": ["integer","null"],
            "minimum": 9,
            "maximum": 9
        }
    }
}
```

Preserve o final do arquivo como está hoje (com ou sem quebra de linha final).

- [x] **Step 4: montador — `toNodeS130` próprio**

Em `src/Factories/Traits/TraitS2420.php`, substituir:

```php
    /**
     * builder for version S.1.3.0
     */
    protected function toNodeS130()
    {
        return $this->toNodeS100();
    }
}
```

por:

```php
    /**
     * builder for version S.1.3.0
     */
    protected function toNodeS130()
    {
        $ideEmpregador = $this->node->getElementsByTagName('ideEmpregador')->item(0);
        //o idEvento pode variar de evento para evento
        //então cada factory individualmente terá de construir o seu
        $ideEvento = $this->dom->createElement("ideEvento");
        $this->dom->addChild(
            $ideEvento,
            "indRetif",
            $this->std->indretif,
            true
        );
        if ($this->std->indretif == 2) {
            $this->dom->addChild(
                $ideEvento,
                "nrRecibo",
                $this->std->nrrecibo,
                true
            );
        }
        $this->dom->addChild(
            $ideEvento,
            "tpAmb",
            $this->tpAmb,
            true
        );
        $this->dom->addChild(
            $ideEvento,
            "procEmi",
            $this->procEmi,
            true
        );
        $this->dom->addChild(
            $ideEvento,
            "verProc",
            $this->verProc,
            true
        );
        $this->node->insertBefore($ideEvento, $ideEmpregador);

        $ideBeneficio = $this->dom->createElement("ideBeneficio");
        $this->dom->addChild(
            $ideBeneficio,
            "cpfBenef",
            $this->std->cpfbenef,
            true
        );
        $this->dom->addChild(
            $ideBeneficio,
            "nrBeneficio",
            $this->std->nrbeneficio,
            true
        );
        $this->node->appendChild($ideBeneficio);

        $infoBenTermino = $this->dom->createElement("infoBenTermino");
        $this->dom->addChild(
            $infoBenTermino,
            "dtTermBeneficio",
            $this->std->dttermbeneficio,
            true
        );
        $this->dom->addChild(
            $infoBenTermino,
            "mtvTermino",
            $this->std->mtvtermino,
            true
        );
        $this->dom->addChild(
            $infoBenTermino,
            "cnpjOrgaoSuc",
            !empty($this->std->cnpjorgaosuc) ? $this->std->cnpjorgaosuc : null,
            false
        );
        $this->dom->addChild(
            $infoBenTermino,
            "novoCPF",
            !empty($this->std->novocpf) ? $this->std->novocpf : null,
            false
        );
        $this->dom->addChild(
            $infoBenTermino,
            "indCPFDesvinc",
            $this->std->indcpfdesvinc ?? null,
            false
        );
        $this->node->appendChild($infoBenTermino);
        //finalização do xml
        $this->eSocial->appendChild($this->node);
        $this->sign();
    }
}
```

Os métodos `toNodeS100`, `toNodeS110` e `toNodeS120` ficam como estão.

- [x] **Step 5: rodar e confirmar que passam**

Rode: `php -d xdebug.mode=off vendor/bin/phpunit tests/Factories/EvtCdBenTermTest.php`
Esperado: `OK` (3 testes).

---

### Task 6: Exemplos do S-1.3 e validação final

**Arquivos:**
- Alterar: `examples/Fake/v_S_01_03_00/Fake_s2500_EvtProcTrab.php` (~l.58-73)
- Alterar: `examples/Fake/v_S_01_03_00/Fake_s2299_EvtDeslig.php` (~l.69-70)
- Alterar: `examples/Fake/v_S_01_03_00/Fake_s2399_EvtTSVTermino.php` (~l.45)
- Alterar: `examples/Fake/v_S_01_03_00/Fake_s2420_evtCdBenTerm_.php` (~l.40)

**Depende de:** Tasks 2, 3, 4 e 5

- [x] **Step 1: exemplo do S-2500**

Em `Fake_s2500_EvtProcTrab.php`, substituir:

```php
$std->dtnascto = '1997-02-22'; //opcional
```

por:

```php
$std->dtnascto = '1997-02-22'; //opcional
$std->nmsoc = 'Fulana da Silva'; //opcional
```

E substituir:

```php
$std->infocontr[0]->infocompl = new \stdClass(); //opcional
$std->infocontr[0]->infocompl->codcbo = '123456'; //opcional
$std->infocontr[0]->infocompl->natatividade = 1; //opcional
```

por:

```php
$std->infocontr[0]->infocompl = new \stdClass(); //opcional
$std->infocontr[0]->infocompl->nmcargo = 'Analista de Sistemas'; //opcional
$std->infocontr[0]->infocompl->codcbo = '123456'; //opcional
$std->infocontr[0]->infocompl->nmfuncao = 'Coordenador de Projetos'; //opcional
$std->infocontr[0]->infocompl->cbofuncao = '142105'; //opcional
$std->infocontr[0]->infocompl->natatividade = 1; //opcional

$std->infocontr[0]->infocompl->localtrabalho = new \stdClass(); //opcional
$std->infocontr[0]->infocompl->localtrabalho->localtrabgeral = new \stdClass(); //opcional
$std->infocontr[0]->infocompl->localtrabalho->localtrabgeral->tpinsc = 1; //1, 3 ou 4
$std->infocontr[0]->infocompl->localtrabalho->localtrabgeral->nrinsc = '12345678000195';
$std->infocontr[0]->infocompl->localtrabalho->localtrabgeral->desccomp = 'Sede'; //opcional
$std->infocontr[0]->infocompl->localtrabalho->localtempdom = new \stdClass(); //opcional
$std->infocontr[0]->infocompl->localtrabalho->localtempdom->tplograd = 'R'; //opcional
$std->infocontr[0]->infocompl->localtrabalho->localtempdom->dsclograd = 'Rua Teste';
$std->infocontr[0]->infocompl->localtrabalho->localtempdom->nrlograd = '10';
$std->infocontr[0]->infocompl->localtrabalho->localtempdom->complemento = 'Casa 2'; //opcional
$std->infocontr[0]->infocompl->localtrabalho->localtempdom->bairro = 'Centro'; //opcional
$std->infocontr[0]->infocompl->localtrabalho->localtempdom->cep = '01311200';
$std->infocontr[0]->infocompl->localtrabalho->localtempdom->codmunic = '3550308';
$std->infocontr[0]->infocompl->localtrabalho->localtempdom->uf = 'SP';
```

- [x] **Step 2: exemplo do S-2299**

Em `Fake_s2299_EvtDeslig.php`, substituir:

```php
$std->mudancacpf->novocpf = '12345678901'; //Obrigatório
```

por:

```php
$std->mudancacpf->novocpf = '12345678901'; //Obrigatório
$std->mudancacpf->indcpfdesvinc = 9; //Opcional (S-1.3, só aceita 9)
```

- [x] **Step 3: exemplo do S-2399**

Em `Fake_s2399_EvtTSVTermino.php`, substituir:

```php
$std->novocpf = "12345678901";
```

por:

```php
$std->novocpf = "12345678901";
$std->indcpfdesvinc = 9; //Opcional (S-1.3, só aceita 9; exige novocpf)
```

- [x] **Step 4: exemplo do S-2420**

Em `Fake_s2420_evtCdBenTerm_.php`, substituir:

```php
$std->novocpf = '12345678901'; //opcional
```

por:

```php
$std->novocpf = '12345678901'; //opcional
$std->indcpfdesvinc = 9; //opcional (S-1.3, só aceita 9)
```

- [x] **Step 5: validar todos os exemplos no XSD novo**

Rode: `php .claude/skills/update-esocial/scripts/validate.php`
Esperado: todos `ok`, incluindo `Fake_s2500_EvtProcTrab`, `Fake_s2299_EvtDeslig`,
`Fake_s2399_EvtTSVTermino` e `Fake_s2420_evtCdBenTerm_`; exit 0.

- [x] **Step 6: suíte completa**

Rode: `php -d xdebug.mode=off vendor/bin/phpunit`
Esperado: `Tests: 29` (16 + 13 novos), sem falhas nem erros.

- [x] **Step 7: conferir o escopo do diff**

Rode: `git status --short && git diff --stat`
Esperado: só mudam `schemes/v_S_01_03_00/*.xsd` (até 17 arquivos com diferença real), os 4
traits, os 4 `.schema`, os 4 `Fake_*`, e surgem os 4 testes novos (mais o dossiê em
`docs/specs/`). Depois rode `git diff -U0 src/Factories/Traits/` e confira que nenhum hunk cai
dentro de `toNodeS100`, `toNodeS110` ou `toNodeS120`.

---

## Ajustes durante a execução

- **2026-10-13 (planejamento)** — suposição 1 do spec **derrubada**: com o validador do
  projeto, `"enum": [9]` rejeita `null` ("Não possui um valor na enumeração [9]"). Os schemas
  usam `"minimum": 9, "maximum": 9` com `["integer","null"]`, que aceita ausente e `null` e
  rejeita 1 (testado em memória). É também o padrão dos schemas S-1.3, que não usam `enum`.
  O `enum: [1, 3, 4]` de `localtrabgeral.tpinsc` fica, porque lá o campo é obrigatório e não
  aceita `null`.
- **2026-10-13 (planejamento)** — o `toNodeS130` do S-2500 não gera `ideSeqTrab`; o `nmSoc`
  entra logo após `dtNascto`. A ausência de `ideSeqTrab` fica fora deste escopo.
- **2026-10-13 (execução)** — Task 2: o teste do S-2500 declarava `array $infoCompl = null`,
  que gera deprecation no PHP 8.4; trocado por `?array` no teste e neste plano.
- **2026-10-13 (execução)** — Task 5: `evtCdBenTerm.schema` não termina com quebra de linha;
  a substituição do step 3 foi aplicada preservando o final do arquivo.
- **2026-10-13 (execução)** — os 4 arquivos de teste foram criados de uma vez no início do
  batch 1; até as Tasks 4 e 5 a suíte completa mostrava 4 falhas esperadas (testes vermelhos
  de S-2399 e S-2420).
- **2026-10-13 (execução)** — `.phpunit.result.cache` (versionado) muda a cada execução do
  phpunit; fica fora do commit.
