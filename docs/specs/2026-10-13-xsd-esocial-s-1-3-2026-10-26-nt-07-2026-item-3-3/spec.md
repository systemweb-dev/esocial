---
titulo: XSD eSocial S-1.3 2026-10-26 (NT 07/2026 item 3.3)
slug: 2026-10-13-xsd-esocial-s-1-3-2026-10-26-nt-07-2026-item-3-3
criado: 2026-10-13
estado: concluido
---

# XSD eSocial S-1.3 2026-10-26 (NT 07/2026 item 3.3)

> Dossiê deste trabalho. `spec.md` é a fonte da verdade do design; `plan.md` é o passo a
> passo de execução; `referencias/` guarda o material de apoio (prints, PDFs, diagramas).

## Objetivo & outcome

Aplicar no fork o pacote oficial de XSD do leiaute S-1.3 de **2026-10-26** (item 3.3 da NT
07/2026 rev. 24/09/2026; produção em 26/10/2026). Outcome: os XMLs que o fork gera para o
S-1.3 continuam válidos no XSD novo, e os campos novos de S-2500, S-2299, S-2399 e S-2420
podem ser informados pelo JSON de entrada.

- Pacote: https://www.gov.br/esocial/pt-br/documentacao-tecnica/manuais/2026-10-26_esquemas_xsd_v_s_01_03_00-1.zip
- Diff completo do pacote contra o fork: `referencias/compare_xsd-2026-10-26.txt`
  (17 de 52 arquivos diferem).
- Fonte de verdade: o XSD do pacote. O upstream NFePHP é só consulta.

## Escopo

### 1. XSDs
Copiar os 52 `.xsd` do pacote para `schemes/v_S_01_03_00/`, sobrescrevendo.

### 2. S-2500 — `TraitS2500::toNodeS130` + `jsonSchemes/v_S_01_03_00/evtProcTrab.schema`

Novos elementos, todos `minOccurs="0"` (gerar com `?? null` e `false`):

| Caminho XML | Posição | Chave JSON | Regra no JSON schema |
|---|---|---|---|
| `ideTrab/nmSoc` | entre `dtNascto` e `ideSeqTrab` | `nmsoc` (raiz, como `nmtrab`) | `["string","null"]`, 2–70 (TS_nome) |
| `infoCompl/nmCargo` | antes de `codCBO` | `infocompl.nmcargo` | `["string","null"]`, 1–100 |
| `infoCompl/nmFuncao` | depois de `codCBO` | `infocompl.nmfuncao` | `["string","null"]`, 1–100 |
| `infoCompl/CBOFuncao` | depois de `nmFuncao`, antes de `natAtividade` | `infocompl.cbofuncao` | `["string","null"]`, `^[0-9]{6}$` |
| `infoCompl/localTrabalho` | último filho de `infoCompl`, depois de `infoTerm` | `infocompl.localtrabalho` (objeto) | `["object","null"]` |
| `.../localTrabalho/localTrabGeral` | 1º filho | `infocompl.localtrabalho.localtrabgeral` | ver abaixo |
| `.../localTrabalho/localTempDom` | 2º filho | `infocompl.localtrabalho.localtempdom` | ver abaixo |

Ordem final de `infoCompl` no XSD: `nmCargo, codCBO, nmFuncao, CBOFuncao, natAtividade,
remuneracao, infoVinc, infoTerm, localTrabalho`.

`localTrabGeral` (`T_localTrabGeral`): `tpInsc` obrigatório, enum **[1, 3, 4]**
(`TS_tpInsc_1_3_4`); `nrInsc` obrigatório, 12 ou 14 posições com CNPJ alfanumérico
(`TS_nrInsc_12_14`); `descComp` opcional, 1–80.

No schema, `nrinsc` usa `^([0-9]{12}|[A-Z0-9]{12}[0-9]{2})$` (o `TS_nrInsc_12_14` do XSD).

`localTempDom` (`T_endereco_brasil`): obrigatórios `dscLograd` (1–100), `nrLograd` (1–10),
`cep` (`^[0-9]{8}$`), `codMunic` (7 dígitos), `uf` (enum das UFs); opcionais `tpLograd`
(1–4), `complemento` (1–30, `TS_codigo_esocial`), `bairro` (1–90). Faixas do `tipos.xsd`
do pacote, não do schema do S-2206 (lá `dsclograd` está com 80).

Mudança de tipo: `infoVinc/tpRegTrab` passa a aceitar 1, 2, 3 (`TS_tpRegTrab_1_2_3`) →
no schema, `maximum` de 2 para 3. Montador não muda.

### 3. `indCPFDesvinc` — S-2299, S-2399, S-2420

Elemento opcional, tipo `TS_indCPFDesvinc` (enum **[9]**). JSON schema:
`"indcpfdesvinc": {"required": false, "type": ["integer","null"], "enum": [9]}` (incluir
`null` no enum se o validador exigir).

| Evento | Chave JSON | XML (só S-1.3) | Arquivo |
|---|---|---|---|
| S-2299 | `mudancacpf.indcpfdesvinc` | `mudancaCPF/indCPFDesvinc`, após `novoCPF` | `TraitS2299::toNodeS130`, `evtDeslig.schema` |
| S-2399 | `indcpfdesvinc` (raiz, como `novocpf`) | `mudancaCPF/indCPFDesvinc`, após `novoCPF`, dentro do bloco que só existe com `novocpf` | `TraitS2399::toNodeS130`, `evtTSVTermino.schema` |
| S-2420 | `indcpfdesvinc` (raiz) | `infoBenTermino/indCPFDesvinc`, após `novoCPF` | `TraitS2420::toNodeS130` (novo, próprio), `evtCdBenTerm.schema` |

### 4. Exemplos e testes
- Incluir os campos novos em `examples/Fake/v_S_01_03_00/` nos Fake de S-2500, S-2299,
  S-2399 e S-2420, com valores válidos; `validate.php` deve passar.
- PHPUnit em `tests/Factories/`, no padrão de `EvtTSVInicioTest` (config S.1.3.0, certificado
  de teste, `toXML()` assina e valida no XSD):
  - S-2500: com todos os campos novos (XML válido, elementos presentes na ordem do XSD); sem
    nenhum (nenhum elemento novo no XML); `localtrabgeral.tpinsc = 2` rejeitado pelo schema.
  - S-2299, S-2399, S-2420: com `indcpfdesvinc = 9` (elemento presente); sem o campo
    (ausente); `indcpfdesvinc = 1` rejeitado pelo schema.

## Não-objetivos / fora de escopo
- Montador do S-8200 no S-1.3 (o XSD ganhou `nmCargo`, mas o fork não monta esse evento no S-1.3).
- Regra de data mínima 1890-01-01 (`TS_dt1890`) no JSON schema: o XSD já barra.
- Tipos renomeados sem mudança de valores (`tpJornada`, `horNoturno`, `dscJorn`,
  `observacao`, `T_horContratual_2206`, `infoMesmoMtv`) e mudanças só em texto de validação.
- Pacotes de 2026-11-23, 2026-12-14 e 2027-01-18 (um por vez), Comunicação 1.6, Mensagens 2.5.
- Refatorar o S-2206 ou criar helper compartilhado para `localTrabalho`.
- Achados da análise geral do projeto (TLS, FactoryId, grupos etc.).
- Leiautes S-1.0 a S-1.2: nenhum método `toNodeS100/S110/S120` muda.

## Restrição de simplicidade
Diff mínimo: só os quatro traits, quatro schemas, quatro exemplos, testes novos e os XSDs.
Sem reformatar arquivos, sem helper novo, sem abstração.

## Appetite
Pequeno: uma sessão de trabalho.

## MVP vs MLP
MVP: suportar o que o XSD novo exige/permite, validado; nada além.

## Decisões (ADR)

**D1 — JSON do `localTrabalho` no S-2500 é aninhado.**
Contexto: o S-2206 recebe `localtrabgeral`/`localtempdom` planos. Decisão: no S-2500, usar
`infocompl.localtrabalho.{localtrabgeral,localtempdom}`, espelhando o XML. Alternativa
descartada: plano como no S-2206. Consequência: formatos diferentes entre os dois eventos,
mas o do S-2500 mapeia 1:1 para o XSD.

**D2 — Montagem do `localTrabalho` inline no `TraitS2500`.**
Contexto: o S-2206 já tem o bloco inline. Decisão: copiar e ajustar ao `T_localTrabGeral` /
`T_endereco_brasil` do S-2500 (`tpLograd` opcional, gerado com `false`; no S-2206 é
obrigatório). Alternativas descartadas: helper novo só para o S-2500; helper + refatorar o
S-2206. Consequência: ~60 linhas duplicadas; nenhum risco ao S-2206.

**D3 — S-2420 ganha `toNodeS130` próprio.**
Contexto: hoje S110/S120/S130 delegam ao S100. Decisão: copiar o S100 para o S130 e incluir
`indCPFDesvinc`. Alternativa descartada: condicional por leiaute no S100. Consequência:
~90 linhas duplicadas; leiautes antigos intocados, padrão igual ao de S-2299/S-2399.

**D4 — `indcpfdesvinc` sem `novocpf` é ignorado (S-2299/S-2399).**
O grupo `mudancaCPF` só existe com `novoCPF` (obrigatório nele); não há validação extra.

## Restrições verificáveis
1. `php .claude/skills/update-esocial/scripts/validate.php` passa com todos os exemplos.
2. `php -d xdebug.mode=off vendor/bin/phpunit` passa, incluindo os testes novos.
3. `git diff --stat` não mostra mudança em métodos `toNodeS100`/`toNodeS110`/`toNodeS120`
   nem em arquivos fora da lista do escopo (exceto `ESOCIAL-VERSION.json`/`CHANGELOG.md`
   após o ok do commit).
4. `compare_xsd.py schemes/v_S_01_03_00 <pacote>` após a cópia: 0 arquivos diferentes.

## Suposições

| Suposição | Risco se for falsa | Como validar |
|---|---|---|
| O validador JSON (justinrainbow, draft-03 com `required` booleano) aceita `enum: [9]` com tipo `["integer","null"]` quando o campo vem `null` | schema rejeita `null`/ausente e quebra eventos sem o campo | teste "sem o campo" do PHPUnit |
| Os Fake atuais do S-2500/S-2299/S-2399/S-2420 já validam no XSD novo antes da mudança | falha no validate.php não relacionada aos campos novos | rodar `validate.php` logo após copiar os XSDs |
| `TS_codigo_esocial` (base de `complemento`) tem 1–30 posições | schema aceita/rejeita complemento diferente do XSD | conferir no `tipos.xsd` ao escrever o schema; o XSD valida de qualquer forma |

## Rollout e reversibilidade
Biblioteca: entra no `master` do fork após ok do usuário; consumidores atualizam com
`composer update systemweb-dev/esocial` (api do enviaesocial) e rodam os cenários de
`enviaesocial/docs/esocial-tests` dos eventos afetados na Produção Restrita. Volta: reverter o
commit. Campos novos são opcionais, então JSON antigo continua válido.

## Ao final (skill update-esocial, passo 7)
Com o ok do usuário: atualizar `ESOCIAL-VERSION.json` (`xsd_package: 2026-10-26`,
`updated_at`; `technical_note` vai para `07/2026` — o pacote implementa itens da NT 07/2026,
e os pacotes seguintes continuam sendo detectados pela data de `xsd_package`) e `CHANGELOG.md`; commit em português no `master`; push só com ok explícito.

## Decisões da execução

- **`indcpfdesvinc` usa `minimum`/`maximum` 9, não `enum: [9]`.** O validador do projeto
  (justinrainbow, draft-03) rejeita `null` com `enum: [9]`; `["integer","null"]` com
  `minimum: 9, maximum: 9` aceita ausente e `null` e rejeita 1. É também o padrão dos schemas
  S-1.3, que não usam `enum`. O `enum: [1, 3, 4]` de `localtrabgeral.tpinsc` foi mantido,
  porque lá o campo é obrigatório.
- **S-2420 `toNodeS130`:** `cnpjOrgaoSuc` e `novoCPF` mantêm o `!empty(...) ? ... : null`
  do S100, porque `DOMImproved::addChild` só omite campo opcional quando o valor é `null`
  (string vazia geraria tag vazia). O campo novo usa `?? null`.
- **S-2500 sem `ideSeqTrab`:** o `toNodeS130` não gera `ideSeqTrab`; `nmSoc` entrou logo após
  `dtNascto`. A ausência de `ideSeqTrab` continua fora do escopo.
- **Teste do S-2500:** `?array $infoCompl = null` em vez de `array $infoCompl = null`, para não
  gerar deprecation no PHP 8.4.
- Entregue no commit `c38c3cd` (master), sem o dossiê `docs/specs/`.
