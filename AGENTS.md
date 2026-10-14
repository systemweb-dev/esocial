# AGENTS.md — fork System Web do sped-esocial

Biblioteca PHP que monta, assina, envia e consulta eventos do eSocial. Fork de
[nfephp-org/sped-esocial](https://github.com/nfephp-org/sped-esocial) mantido pela System Web para o
enviaesocial. Créditos e licença (MIT/LGPLv3+/GPLv3+, "Copyright (c) 2016 NFePHP") ficam como estão no
`README.md` e no `LICENSE`.

## Manter atualizado

O governo ajusta o leiaute S-1.3 por Nota Técnica várias vezes ao ano. Rode a skill
`update-esocial` (`.claude/skills/update-esocial/SKILL.md`) no início de cada mês e sempre que
sair notícia de nota técnica. Estado aplicado: `ESOCIAL-VERSION.json`.

| Fonte | Endereço |
|---|---|
| Portal do eSocial, Documentação Técnica (XSD com data de produção, notas técnicas, pacote de comunicação, mensagens do sistema) | https://www.gov.br/esocial/pt-br/documentacao-tecnica |
| Agenda de implantação (datas por ambiente) | https://www.gov.br/esocial/pt-br/centrais-de-conteudo/agenda/agenda-1 |
| Upstream (**só consulta**) | https://github.com/nfephp-org/sped-esocial |

O que vale é o **eSocial**: toda mudança vem do XSD, da nota técnica ou do leiaute publicados no gov.br.
O upstream NFePHP é só referência de implementação; nada entra no fork só porque está lá.

Os pacotes de XSD ficam em `schemes/v_S_01_03_00/`, **um por vez**: aplique perto da data de produção.

Pacotes preparados antes da data ficam em branches `xsd/<data>` (lista e data de merge em
`pending_packages` do `ESOCIAL-VERSION.json`). Catálogo das Mensagens do Sistema:
`data/mensagens-sistema.json`, lido por `Common\SystemMessages::get($codigo)`.

## Mapa do código

| Caminho | Para quê |
|---|---|
| `schemes/v_S_01_03_00/` | XSD oficiais do leiaute (substituídos a cada pacote) |
| `schemes/comunicacao/v1_6_0/` | XSD/WSDL de envio e consulta de lotes (1.6-Alfa, recomendado; os pacotes 1.1.0 a 1.5.0 continuam disponíveis) |
| `jsonSchemes/v_S_01_03_00/` | validação da entrada (`stdClass`) antes de montar o XML |
| `src/Factories/Evt*.php` | um montador por evento |
| `src/Factories/Traits/TraitSxxxx.php` | o XML de cada evento; `toNodeS130()` é o leiaute atual |
| `src/Common/Soap/SoapCurl.php` | conexão com o governo (certificado pela memória) |
| `src/Tools.php` | envio/consulta de lotes e grupos de eventos |
| `examples/Fake/v_S_01_03_00/` | um exemplo por evento; base do `validate.php` |
| `docs/eventos/` | uma página por evento, **gerada** por `gen_docs.py` (não edite à mão) |
| `docs/Tools.md`, `docs/Config.md` | API do `Tools` e chaves do config (conferidas por `check_docs.py`) |
| `tests/Unit/` | testes em Pest (`composer test`); helpers em `tests/Pest.php` |

## Regras

- Namespace `NFePHP\eSocial` e nomes de classe mantidos.
- Só o leiaute **S-1.3** (S-1.2 encerrado no eSocial em 02/02/2025): `eventoVersion` abaixo
  disso é recusado no construtor do evento. Os `toNodeS100/S110/S120` dos traits continuam
  porque vários `toNodeS130` delegam para eles.
- Eventos que a lib não monta (`Event::$unavailable`: retornos S-5xxx, extintos, S-8200/S-8299)
  respondem com `EventsException` código 1003 e o motivo.
- PHP >= 8.1; parâmetro opcional tipado sempre com `?Tipo $x = null`. Grupo 1 do lote:
  `Tools::EVT_TABELAS` (`EVT_INICIAIS` é alias obsoleto).
- Diffs mínimos: não reformate arquivos inteiros (inclusive JSON).
- Branch `master`; commits em português; push só com ok do responsável.
- Versões semver com tag anotada `vX.Y.Z` (desde `v2.0.0`): pacote XSD ou campo novo = minor,
  correção = patch, remoção = major. Registro no `CHANGELOG.md`.
- Campo `minOccurs="0"` no XSD → `addChild(..., $valor ?? null, false)`. Já corrigidos:
  `codIncCPRP` (S-1010), `indenSD`/`indenAbono` (S-2500); `ideSeqProc` (S-2501) acrescentado.
- Certificado só pela memória (`CURLOPT_SSLCERT_BLOB`/`CURLOPT_SSLKEY_BLOB`); nunca grave PEM.
- TLS do webservice verificado por padrão (`SoapCurl::securityOptions()`, store do sistema; os
  hosts do eSocial usam a raiz pública GlobalSign Root R46). `loadCA()` **substitui** o store;
  `disableSecurity(true)` desliga a verificação e só serve para ambiente controlado.
  Checagem com rede: `vendor/bin/pest --group=network` (fora do `composer test`).
- Pacote de comunicação recomendado (o dos testes e exemplos): `serviceVersion '1.6.0'` (pasta `v1_6_0` = 1.6-Alfa, aceita
  CNPJ alfanumérico). Com `'1.5.0'` o lote de CNPJ alfanumérico é recusado localmente.
- Grupos do lote: 1 = tabelas, 2 = não periódicos, 3 = periódicos (MOS S-1.3, seção 10;
  `Common\Tools::$grupos`). S-5xxx (retornos) e S-8200/S-8299 (Judiciário) não vão em lote.
- O Id do evento é `$evento->evtid`: o `FactoryId` evita repetição no mesmo processo trocando o
  sequencial. Entre processos (vários workers do mesmo empregador) quem chama aloca o
  `sequencial`.
- Nunca rode com Xdebug ou `display_errors` ligados onde há certificado: o stack trace imprime a chave.
- Testes em Pest e AAA estrito: um Act por teste, exceção em `->throws()`, helper sem asserção.
  Campo novo ou bug de montador entra com teste em `tests/Unit/Factories/`.
- O XSD não cobre as regras de negócio do governo (ex.: RAT só se divergir da lei, `horNoturno`,
  `grauExp`, `indApurIR` obrigatórios). Catálogo e XMLs reais em
  `/var/www/enviaesocial/docs/esocial-tests/`.

## Comandos

```bash
python3 .claude/skills/update-esocial/scripts/check.py      # o que saiu de novo
python3 .claude/skills/update-esocial/scripts/compare_xsd.py schemes/v_S_01_03_00 <pasta-nova>
php .claude/skills/update-esocial/scripts/validate.php           # exemplos válidos no XSD
python3 .claude/skills/update-esocial/scripts/gen_docs.py --check  # docs/eventos em dia
python3 .claude/skills/update-esocial/scripts/check_docs.py        # docs só citam API existente
composer test                                                   # suíte Pest (vendor/bin/pest)
```
