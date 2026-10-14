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

## Mapa do código

| Caminho | Para quê |
|---|---|
| `schemes/v_S_01_03_00/` | XSD oficiais do leiaute (substituídos a cada pacote) |
| `schemes/comunicacao/v1_5_0/` | XSD/WSDL de envio e consulta de lotes |
| `jsonSchemes/v_S_01_03_00/` | validação da entrada (`stdClass`) antes de montar o XML |
| `src/Factories/Evt*.php` | um montador por evento |
| `src/Factories/Traits/TraitSxxxx.php` | o XML de cada evento; `toNodeS130()` é o leiaute atual |
| `src/Common/Soap/SoapCurl.php` | conexão com o governo (certificado pela memória) |
| `src/Tools.php` | envio/consulta de lotes e grupos de eventos |
| `examples/Fake/v_S_01_03_00/` | um exemplo por evento; base do `validate.php` |
| `tests/Unit/` | testes em Pest (`composer test`); helpers em `tests/Pest.php` |

## Regras

- Namespace `NFePHP\eSocial` e nomes de classe mantidos.
- Diffs mínimos: não reformate arquivos inteiros (inclusive JSON).
- Branch `master`; commits em português; push só com ok do responsável.
- Campo `minOccurs="0"` no XSD → `addChild(..., $valor ?? null, false)`. Já corrigidos:
  `codIncCPRP` (S-1010), `indenSD`/`indenAbono` (S-2500); `ideSeqProc` (S-2501) acrescentado.
- Certificado só pela memória (`CURLOPT_SSLCERT_BLOB`/`CURLOPT_SSLKEY_BLOB`); nunca grave PEM.
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
composer test                                                   # suíte Pest (vendor/bin/pest)
```
