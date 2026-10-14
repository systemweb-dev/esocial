---
titulo: SP2 limpeza da API e compatibilidade
slug: 2026-10-14-sp2-limpeza-da-api-e-compatibilidade
criado: 2026-10-14
estado: concluido
---

# SP2 — limpeza da API e compatibilidade

> Dossiê deste trabalho. `spec.md` é a fonte da verdade do design; `plan.md` é o passo a
> passo de execução; `referencias/` guarda o material de apoio.

Segundo de quatro sub-projetos (SP1 envio seguro — entregue; SP3 atualizações do eSocial; SP4
documentação e versionamento).

## Objetivo & outcome

A biblioteca passa a expor só o que funciona no eSocial de hoje (leiaute **S-1.3**), com erro
claro para o resto, e roda sem avisos no PHP 8.4:

- nenhum `Event::xxx()` derruba o processo com erro fatal (hoje os S-50xx derrubam);
- evento que a lib não monta responde com o **motivo** (retorno do governo, extinto, Judiciário);
- leiaute encerrado (S-1.0, S-1.1, S-1.2) é recusado com mensagem clara;
- **0 deprecated** na suíte (hoje 42, todos de parâmetro anulável implícito);
- sai o código morto (inclusive o `eval` do `ParamsStandardize`).

Outcome verificável: `composer test` verde com **0 deprecated**; grep sem referência às classes
removidas; `validate.php` 40/40.

## Contexto (fatos levantados)

- Consumidor (`enviaesocial/api`): `dev-master`, PHP 8.3, `eventoVersion 'S.1.3.0'`,
  `serviceVersion '1.5.0'`; usa `Event::evtXxx($config, $std, $cert)` de 28 eventos (todos fora
  da lista de remoção), `Tools` (lote, consultas, downloads). Não usa S-50xx, `Standardize`,
  `XsdSeeker`, `FactoryId`, `loadCA`, `EVT_INICIAIS`, `setEncriptPrivateKey`, `waitingTime`, nem
  leiautes S-1.0/S-1.1/S-1.2. **Nada do que sai é usado por ele.**
- Leiautes: S-1.3 em produção desde 02/12/2024; **"02/02/2025 — último dia de convivência da
  versão S-1.2 com a S-1.3"** (Agenda do eSocial). S-1.0 e S-1.1 encerrados antes.
- Os métodos `toNodeS100/S110/S120` dos traits **não podem sair**: vários `toNodeS130` delegam
  para eles.
- `EventsException::wrongArgument()` faz `new static($msg)` sem código: hoje `getCode()` = 0.
- Deprecations atuais (42): todas de `Certificate $certificate = null` /
  `LoggerInterface $logger = null` (nenhuma do vendor).
- `examples/Fake/v_S_01_03_00/FaketestEnvia_1.php` é um exemplo de lote só com o S-2245
  (extinto); `FaketestEnvia.php` já demonstra o envio de lote.

## Escopo

### 1. Eventos que a lib não monta

- **Remover 22 fábricas** (`src/Factories/<Classe>.php`) e **13 traits**
  (`src/Factories/Traits/TraitSxxxx.php`):

  | Grupo | Código → nome (classe) | Trait |
  |---|---|---|
  | Extintos no S-1.x | S-1030 evtTabCargo (EvtTabCargo), S-1035 evtTabCarreira, S-1040 evtTabFuncao, S-1050 evtTabHorTur, S-1060 evtTabAmbiente, S-1080 evtTabOperPort, S-1250 evtAqProd, S-1295 evtTotConting, S-1300 evtContrSindPatr, S-2245 evtTreiCap, S-2250 evtAvPrevio, S-2260 evtConvInterm | TraitS1030, 1035, 1040, 1050, 1060, 1080, 1250, 1295, 1300, 2245, 2250, 2260 |
  | Extintos sem trait | S-1065 evtTabEquipamento, S-2241 evtInsApo | — |
  | Retornos do eSocial | S-5001 evtBasesTrab, S-5002 evtIrrfBenef, S-5003 evtBasesFGTS, S-5011 evtCS, S-5012 evtIrrf, S-5013 evtFGTS | — (as traits nunca existiram: hoje dão fatal) |
  | Judiciário | S-8200 evtAnotJud (TraitS8200), S-8299 evtBaixa (sem trait) | TraitS8200 |

  Os nomes saem de `Event::$available`, os códigos de `Event::$aliases`. `Event::$codes`
  **mantém** S-8200/S-8299 (usado por `Tools::getIdFromXml` para recusar em lote).
- **`Event::$unavailable`**: mapa `chave → [código canônico, nome, motivo]` (o nome com a grafia do
  XSD/`Event::$codes`, ex.: `evtBasesTrab`, `evtCS`; é essa grafia que aparece no motivo), com chaves em
  minúsculo para o nome (`evtbasestrab`) **e** para o código sem hífen (`s5001`). Inclui os 22
  acima **e** os retornos S-5501 evtTribProcTrab e S-5503 evtFGTSProcTrab. Motivos:
  - retorno: `"{código} ({nome}) é um evento de retorno do eSocial: o empregador não envia; leia o totalizador no retorno do processamento do lote."`
  - extinto: `"{código} ({nome}) foi extinto e não existe no leiaute S-1.3."`
  - Judiciário: `"{código} ({nome}) é enviado só pelo Poder Judiciário (procEmi 8): o empregador não envia."`
- `Event::__callStatic`: normaliza o nome (como hoje: minúsculo, sem hífen) e consulta
  `$unavailable` **primeiro** — antes dos aliases e das checagens de config (1001) e dados
  (1002) —, lançando `EventsException::wrongArgument(1003, motivo)`.
- `EventsException`: `$list[1003] = '{{msg}}'`; remover 1004 e 1005; `wrongArgument()` passa a
  repassar o código (`new static($msg, $code)`) — `getCode()` passa a ser 1000/1001/1002/1003.
- Novo alias `s3500 → evtexcproctrab`.
- Remover `examples/Fake/v_S_01_03_00/FaketestEnvia_1.php`.

### 2. Só o leiaute S-1.3

- `Factory::__construct`: depois de ler `eventoVersion` e **antes** de verificar o XSD, se
  `version_compare(<números de eventoVersion>, '1.3.0', '<')` lançar
  `InvalidArgumentException("Leiaute {eventoVersion} encerrado no eSocial: use S.1.3.0.")`.
  Compara só maior.menor: os dois primeiros números de `eventoVersion` (ex.: `S.1.2.0` → `1.2`,
  `S.1.3` → `1.3`) contra `1.3` — assim `S.1.2` é recusado como encerrado e `S.1.3` não leva a
  mensagem enganosa (falha depois por XSD não localizado, como hoje).
- Remover `schemes/v_S_01_00_00`, `v_S_01_01_00`, `v_S_01_02_00`; os mesmos em `jsonSchemes/`,
  `examples/Fake/` e `examples/schemes/`.
- Remover os JSON schemas órfãos do S-1.3: `jsonSchemes/v_S_01_03_00/evtTotConting.schema`
  (S-1295 extinto) e `evtCdBenPrRP.schema` (sem fábrica nem XSD).
- `toNodeS100/S110/S120` ficam.
- `tests/Unit/BuildersTest.php`: dataset volta a ser só `v_S_01_03_00` (40 exemplos).

### 3. PHP 8.4 e ajustes pequenos

- Todo parâmetro `Certificate $certificate = null` e `LoggerInterface $logger = null` em `src/`
  vira `?Certificate` / `?LoggerInterface` (nas fábricas que ficam, `Factory`, `SoapBase`,
  `SoapCurl`, `SoapFake`). Troca mecânica, sem outra mudança.
- `composer.json`: `"php": ">=8.1"`.
- `Common\Tools::EVT_TABELAS = 1`; `EVT_INICIAIS` fica com `@deprecated` (alias); os testes
  passam a usar `EVT_TABELAS`.
- `FactoryId::build`: `sequencial` informado que não seja inteiro entre 1 e 99999 (inclusive
  texto não numérico e negativo) → `InvalidArgumentException`. Vazio, `null` e `0` continuam
  "gerar aleatório".

### 4. Código morto e `console/`

- Remover: `src/Common/ParamsStandardize.php`; `Factory::standardizeProperties()` e a linha
  comentada que o chamava; `src/Common/Sanitize.php`; `src/Common/Soap/SoapCode.php` e
  `src/Common/Soap/httpcodes.json`; `SoapCurl::getFaultString()`.
- `SoapBase`: remover `saveTemporarilyKeyFiles`, `removeTemporarilyFiles`, `__destruct` (só
  chama `removeTemporarilyFiles`), `setEncriptPrivateKey`, `makeEnvelopeSoap`, `setSoapPrefix`
  e as propriedades usadas só por eles (`prifile`, `pubfile`, `certfile`, `certsdir`,
  `temppass`, `encriptPrivateKey`, `prefixes`, `waitingTime`) e o `use` que ficar sem uso.
  **Ficam** `filesystem`, `tempdir`, `debugdir` (usados por `setDebugMode`/`saveDebugFiles`).
  Cada remoção é conferida por grep no plano.
- Remover o diretório `console/` (3 `.crt` da cadeia ICP de certificado de cliente, sem uso);
  `SoapCurlTest` passa a usar um bundle temporário criado no teste.
- `FakePretty` fica (usado pelos `Faketest*`).
- `AGENTS.md`: regras novas (só S-1.3; eventos indisponíveis e motivo; PHP >= 8.1;
  `EVT_TABELAS`) e corrigir o mapa ("`v1_5_0` mantido até o SP2" → todos os pacotes de
  comunicação ficam; padrão 1.6.0).

## Testes (Pest, AAA estrito, dados fictícios, sem rede)

- **Indisponíveis** (dataset dos 24): chamar pelo nome e pelo código (`s5001`, `S-5001`) lança
  `EventsException` com o motivo e `getCode() === 1003`, também com config vazio
  (`Event::s5001('', $std)` → motivo, não 1001).
- **Consistência do `Event`**: todo valor de `$aliases` existe em `$available`; nenhuma chave de
  `$unavailable` está em `$available` ou `$aliases`; toda classe de `$available` existe e tem
  XSD em `schemes/v_S_01_03_00`; `Event::s3500(...)` monta `EvtExcProcTrab`.
- **Códigos da exceção**: 1000 (nome desconhecido), 1001 (sem config), 1002 (sem dados) com
  `getCode()` correspondente.
- **Leiautes**: `S.1.0.0`, `S.1.1.0`, `S.1.2.0` e `S.1.2` recusados com a mensagem; `S.1.3.0`
  monta; `S.1.3` não recebe a mensagem de encerrado.
- **FactoryId**: `100000`, `-1` e `'abc'` recusados; `99999` aceito; `0`/`null` aleatório (o teste
  existente).
- **Grupos**: `EVT_TABELAS === 1` (e o `GruposTest`/`ToolsTest` usando a constante nova).
- **SoapCurl**: `loadCA` com bundle temporário (arquivo criado e apagado no teste).
- Suíte inteira com **0 deprecated**.

## Não-objetivos / fora de escopo

- Pacotes de comunicação: **todos ficam** (v1_1_0 a v1_6_0), por decisão do usuário.
- Pacotes XSD do eSocial, Mensagens do Sistema, correção do `compare_xsd.py` → **SP3**.
- `docs/*.md` (inclusive dos eventos removidos), README, tags semver → **SP4**.
- Remover `toNodeS100/S110/S120` (delegação do S-1.3) ou reescrever traits.
- Leitura dos totalizadores S-50xx (parser de retorno).
- Mudança no enviaesocial (nenhuma necessária).

## Restrição de simplicidade

Remoção e troca mecânica; a única estrutura nova é o array `Event::$unavailable`. Sem classe
nova, sem camada de compatibilidade além da constante `EVT_INICIAIS` mantida.

## Appetite

Médio: ~8 tasks, uma sessão.

## MVP vs MLP

MVP: remover, recusar com mensagem, zerar deprecations, provar com testes.

## Decisões (ADR)

**D1 — Remover em vez de deprecar.** Contexto: tudo que sai nunca funcionou (fatal, "Schemas não
localizados", exceção no construtor) ou não é chamado. Alternativas descartadas: `@deprecated`
+ aviso; só corrigir o fatal. Consequência: quebra de API só no nome; vira versão major quando o
SP4 criar as tags.

**D2 — Tabela de indisponíveis com motivo.** Alternativas descartadas: remover e deixar a mensagem
genérica "não foi encontrado"; manter classes que lançam. Consequência: um array a mais no
`Event`, consultado antes dos aliases.

**D3 — Só S-1.3.** S-1.2 encerrado em 02/02/2025 (Agenda). Alternativas descartadas: recusar só
S-1.0/S-1.1; corrigir o `indAprend` e manter. Consequência: ~5 MB de schema/exemplo a menos;
`toNodeS1x0` antigos ficam por delegação.

**D4 — Pacotes de comunicação mantidos.** Não atrapalham (~320 KB, carregados só se pedidos);
o consumidor usa 1.5.0.

**D5 — `getCode()` da `EventsException` passa a ser o código da lista.** Hoje é 0; ninguém
depende disso (consumidor não captura por código).

## Restrições verificáveis

1. `composer test` → a linha de resumo do Pest (`Tests: …`) não contém `deprecated` nem `failed`.
   Complemento: `grep -rnE '[^?]Certificate \$certificate = null|[^?]LoggerInterface \$logger = null' src` → vazio.
2. `grep -rnE "ParamsStandardize|Sanitize|SoapCode|getFaultString|saveTemporarilyKeyFiles|setEncriptPrivateKey|makeEnvelopeSoap|Factories\\\\Evt(TreiCap|BasesTrab|AnotJud|Baixa)|TraitS(2245|8200)|v_S_01_0[012]_00" src tests examples AGENTS.md composer.json` → vazio
   (os nomes dos eventos, como `evtTreiCap`, continuam em `Event::$unavailable` e nos testes de propósito).
3. `php .claude/skills/update-esocial/scripts/validate.php` → `40/40`.
4. `ls schemes jsonSchemes examples/Fake examples/schemes` → só `v_S_01_03_00` (mais `comunicacao`
   em `schemes` e o arquivo `definitions.schema` em `jsonSchemes`).

## Suposições

| Suposição | Risco se for falsa | Como validar |
|---|---|---|
| Nenhum `toNodeS130` delega para método de trait removido | evento do S-1.3 quebra | traits removidos só pertencem às 22 fábricas (revisor conferiu); `BuildersTest` com os 40 exemplos |
| Nenhum consumidor usa leiaute < S-1.3 | integração antiga quebra | enviaesocial usa só S.1.3.0 (conferido); o governo não aceita outro |

## Rollout e reversibilidade

Commits no `master`; o enviaesocial não precisa mudar nada (não usa nada do que sai). Versão
major quando o SP4 criar as tags. Volta: reverter os commits.

## Decisões da execução

- **`Event::$unavailable` como lista `código → [nome, tipo]`**, com os textos em
  `$unavailableReasons`, em vez do mapa com chaves duplas (nome e código). O `__callStatic`
  compara o nome normalizado com o nome em minúsculo e com o código sem hífen — mesmo
  comportamento observável, sem duplicar cada evento.
- **Mensagem por dataset** (motivo de cada indisponível): capturada com `caughtException()` no
  Act e conferida no Assert, já que `->throws()` só aceita mensagem fixa.
- **Vermelho da Task 1 com PHP Fatal**: o `class_exists` do teste "tem classe e XSD" carregava a
  `EvtCS` (trait inexistente) — o próprio bug corrigido; depois da remoção a suíte fechou com o
  mesmo número da execução do revisor na cópia.
- **`toNodeS100/S110/S120` mantidos**: um juiz sugeriu removê-los como código morto; não é,
  porque vários `toNodeS130` delegam para eles (ex.: S-1005 → `toNodeS110`).
- **Ajustes dos juízes**: teste de código da exceção confere primeiro o tipo; linha longa do
  motivo `retorno` quebrada; linha em branco dupla do `Factory` removida; regex da restrição 1
  com `[^?]`; `AGENTS.md` chama o 1.6.0 de "recomendado" (o `serviceVersion` vem sempre do
  config).
- Entregue nos commits `e97afb0`, `03d035e`, `0952768` e nos deste fechamento.
