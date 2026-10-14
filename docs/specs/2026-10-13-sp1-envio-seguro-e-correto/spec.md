---
titulo: SP1 envio seguro e correto
slug: 2026-10-13-sp1-envio-seguro-e-correto
criado: 2026-10-13
estado: concluido
---

# SP1 — envio seguro e correto

> Dossiê deste trabalho. `spec.md` é a fonte da verdade do design; `plan.md` é o passo a
> passo de execução; `referencias/` guarda o material de apoio.

Primeiro de quatro sub-projetos para zerar as pendências do fork (SP2 limpeza da API, SP3
atualizações do eSocial, SP4 documentação e versionamento). Este cobre o que afeta o envio em
produção.

## Objetivo & outcome

A biblioteca passa a: (1) aceitar lote de empregador com **CNPJ alfanumérico** (em produção no
eSocial desde 01/07/2026); (2) **verificar o certificado TLS** do webservice (hoje desligado:
exposto a MITM); (3) **não gerar Id de evento repetido** no mesmo processo (hoje o segundo
evento é tratado pelo governo como reenvio e descartado em silêncio); (4) agrupar os eventos
conforme o **manual oficial** e dar erros claros para grupo inválido.

Outcome verificável: os testes listados em cada componente passam com dados fictícios e
`SoapFake` (sem rede); a checagem opcional `network` completa o handshake verificado com os 4
hosts do eSocial.

## Contexto (fatos levantados)

- Consumidor: `enviaesocial/api` usa a lib via `dev-master` (lock em `5c42bed`), PHP 8.3 em
  produção (`php:8.3-fpm-alpine`), `serviceVersion '1.5.0'`, `grupo` sempre `int`, `sequencial`
  sempre informado (quase sempre 1). Não usa `SoapFake`, `loadSoapClass`, `XsdSeeker`,
  `FactoryId` nem S-50xx.
- O lote é validado em `src/Tools.php` contra `EnvioLoteEventos-*.xsd` do pacote de
  comunicação antes do envio. No 1.5.0, `ideEmpregador/nrInsc` e `ideTransmissor/nrInsc` são
  `\d{8,15}`; o XML do evento vai em `xs:any processContents="skip"`, então só esses dois campos
  barram o CNPJ alfanumérico.
- Pacote 1.6-Alfa (gov.br, `pacote-de-comunicacao-esocial-v1-6-alfa.zip`): mesmos arquivos e
  versões por operação do 1.5.0; `targetNamespace` idênticos em todos os XSD de requisição
  (`referencias/namespaces-1.5.0-vs-1.6-alfa.txt`); muda `RetornoEvento` v1_2_1 → v1_3_0 (não
  usado em `src`) e os WSDL de Eventos trazem o schema inline (sub-namespace `/Imports` some;
  a lib monta o envelope à mão e não usa os WSDL). Patterns novos: empregador
  `[A-Z0-9]{8}|\d{11}|[A-Z0-9]{12}\d{2}`, transmissor `\d{11}|[A-Z0-9]{12}\d{2}`
  (`referencias/controle-alteracoes-comunicacao-1.6-alfa.txt`). O MOD v1.16 permite usar a
  Alfa também com CNPJ numérico. O retorno real da Produção Restrita (13/10/2026) já vem em
  `retornoEvento/v1_3_0`.
- TLS: `SoapCurl::send()` define `VERIFYPEER=0` sempre e só sobe `VERIFYHOST=2`;
  `SoapCurl::wsdl()` zera os dois. Os 4 hosts (`webservices.{producaorestrita,envio,consulta,
  download}.esocial.gov.br`) apresentam cadeia folha → "AC SERPRO AR46 OV TLS CA 2025" →
  **GlobalSign Root R46** (pública, no store do sistema); `curl` com verificação padrão e sem
  certificado de cliente completa o handshake nos 4. `console/*.crt` são a cadeia ICP-Brasil
  de certificado **de cliente** e não validam o servidor.
- Id do evento: `Factory::init()` chama `FactoryId::build()` uma única vez por evento (só com
  `$this->dom` vazio). Id = `ID` + tpInsc + nrInsc (completada à direita até 14) +
  `AAAAMMDDHHMMSS` + sequencial(5). Com sequencial fixo, mesmo empregador e mesmo segundo →
  mesmo Id (100%); sem sequencial, `rand(0,9)` + 4 dígitos do microtime.
- Grupos: MOD 7.5.7 define 1 = Tabelas, 2 = Não Periódicos, 3 = Periódicos (erro 406 se o
  evento não pertence ao grupo). Composição pelo MOS S-1.3, seção 10. S-3000 e S-3500 foram
  aceitos no grupo 2 na Produção Restrita (`enviaesocial/docs/esocial-tests/README.md`).
  S-5xxx são retornos do governo; S-8200/S-8299 são exclusivos do Judiciário.

## Escopo

### 1. Pacote de Comunicação 1.6-Alfa

- Copiar XSD/WSDL do pacote oficial para `schemes/comunicacao/v1_6_0/` (achatado, como as
  pastas existentes; mesmo conjunto de 17 arquivos). `serviceVersion` "1.6.0" já gera
  `v1_6_0` via `Common\Tools::stringfyVersions`.
- `Tools` e `XsdSeeker`: sem mudança de código para o 1.6 (versões por operação iguais).
- `v1_5_0` permanece (remoção no SP2).
- Padrão do projeto vira 1.6.0: `tests/ESocialTestCase.php`, **só** `examples/Fake/v_S_01_03_00/*`
  (`serviceVersion`; as pastas dos leiautes antigos não mudam), `docs/Config.md` (a linha 10,
  hoje `'serviceVersion' => '1.1.0'`; reescrita do doc é SP4) e `ESOCIAL-VERSION.json`
  (`communication: "1.6.0-Alfa"`).
- `.claude/skills/update-esocial/scripts/check.py`: reconhecer a versão Alfa publicada
  ("v1.6-Alfa") e compará-la com o estado. A Alfa é o superconjunto mais novo da mesma versão
  (changelog de 2026-08 contra 2025-04 da base): com estado "1.6.0-Alfa", a 1.6 base listada
  na mesma página **não** é novidade; uma versão maior (1.7…) é. Hoje o regex descarta a Alfa
  e `[\d.]+` captura "1.6." mesmo assim.

### 2. TLS no `SoapCurl`

- Novo `SoapCurl::securityOptions(): array` (público, para teste) com as opções TLS:
  - padrão: `CURLOPT_SSL_VERIFYPEER => true`, `CURLOPT_SSL_VERIFYHOST => 2`;
  - com `loadCA($arquivo)`: o mesmo + `CURLOPT_CAINFO => $arquivo`. **`CAINFO` substitui o
    store do sistema** (não soma): documentar no docblock do `loadCA` e no `AGENTS.md`;
  - com `disableSecurity(true)`: `VERIFYPEER => false`, `VERIFYHOST => 0` (opt-out explícito,
    documentado como inseguro).
- `send()` e `wsdl()` aplicam `securityOptions()` com `curl_setopt_array`; as linhas que fixam
  `VERIFYPEER`/`VERIFYHOST` em 0 saem.
- `SoapBase::loadCA()` com arquivo inexistente lança `InvalidArgumentException` (hoje ignora
  em silêncio).
- `console/*.crt` não mudam neste sub-projeto (decisão de remoção no SP2).

### 3. Id único no processo (`FactoryId`)

- Registro estático dos Ids emitidos: chave = base (`ID` + tpInsc + nrInsc completado +
  `AAAAMMDDHHMMSS`), valor = sequenciais usados.
- Sequencial inicial: o informado; se vazio, `random_int(1, 99999)`.
- Se base+sequencial já foi usado no processo, avança para o próximo livre (99999 → 1); se os
  99999 estiverem usados, `RuntimeException`.
- Registro limitado a 1000 bases: ao passar, descarta as mais antigas (ordem de inserção).
- `FactoryId::reset()` limpa o registro; `ESocialTestCase::setUp()` chama.
- Documentar (docblock do `FactoryId`, `AGENTS.md`): `$evento->evtid` é a fonte da verdade do
  Id; entre processos a lib não garante unicidade — quem roda vários workers para o mesmo
  empregador aloca o `sequencial`.

### 4. Grupos, lote de XML e `XsdSeeker`

- `Common\Tools::$grupos` (S-1.3, MOS seção 10):
  - 1: S-1000, S-1005, S-1010, S-1020, S-1070;
  - 2: S-2190, S-2200, S-2205, S-2206, S-2210, S-2220, S-2221, S-2230, S-2231, S-2240, S-2298,
    S-2299, S-2300, S-2306, S-2399, S-2400, S-2405, S-2410, S-2416, S-2418, S-2420, S-2500,
    S-2501, S-2555, S-3000, S-3500;
  - 3: S-1200, S-1202, S-1207, S-1210, S-1260, S-1270, S-1280, S-1298, S-1299.
  - Saem: S-1030, S-1035, S-1040, S-1050, S-1060, S-1080, S-1250, S-1295, S-1300, S-2245,
    S-2250, S-2260, S-4000 e todos os S-5xxx.
- `$eventGroup[1]` = "EVENTOS DE TABELAS".
- `enviarLoteEventos` e `enviarLoteXmlEventos` normalizam o grupo **antes de qualquer
  comparação**: aceitam `int` ou string numérica (`"1"`) e convertem para `int`; fora de
  {1, 2, 3} lançam `InvalidArgumentException('Grupo inválido [x]: use 1 (tabelas),
  2 (não periódicos) ou 3 (periódicos).')`.
- `enviarLoteXmlEventos`: evento cujo código não tem grupo → `RuntimeException('O evento
  S-xxxx não pode ser enviado em lote pelo empregador.')` (hoje: warning de índice indefinido
  e mensagem enganosa); corrigir "diferir" + "do grupo" (espaço).
- `XsdSeeker::seek()`: cada chamada parte de uma lista limpa (versão/nome vazios); o
  `XsdSeeker::$list` público passa a refletir o resultado da última chamada.

## Testes (Pest, AAA estrito, dados fictícios, sem rede)

- **Comunicação**: lote `enviarLoteEventos` de um S-1000 com empregador `nrInsc` alfanumérico
  `A1B2C3D4` e `serviceVersion` 1.6.0 → envelope gerado (SoapFake) com o `nrInsc`; o mesmo com
  1.5.0 → exceção de validação do XSD de comunicação; numérico aceito no 1.6.0;
  `XsdSeeker::seek(v1_6_0)` acha `EnvioLoteEventos-v1_1_1.xsd` e `RetornoEvento-v1_3_0.xsd`;
  os testes de `Tools`/`Factory` existentes passam com o padrão 1.6.0.
- **check.py** (`unittest`, ver D6): página com a 1.6 base e a 1.6-Alfa e estado "1.6.0-Alfa"
  → sem novidade; mesma página e estado "1.5.0" → novidade; página com 1.7 e estado
  "1.6.0-Alfa" → novidade.
- **TLS**: `securityOptions()` padrão = peer+host; com `loadCA` = + `CAINFO` (e nada que some
  ao store); `loadCA('inexistente')` lança; `disableSecurity(true)` desliga; `wsdl()` usa as
  mesmas opções (provado por um método de montagem compartilhado, sem rede).
- **TLS `network`** (grupo separado; `composer test` passa a rodar com `--exclude-group=network`;
  execução manual com `vendor/bin/pest --group=network`):
  para cada um dos 4 hosts, `curl` com `securityOptions()` padrão, sem certificado de cliente,
  sem corpo: passa se `curl_errno` = 0 (qualquer código HTTP).
- **FactoryId**: repetição de base+sequencial → `00002`; sequenciais distintos não interferem;
  sem sequencial → 5 dígitos e duas chamadas diferem; 99999 ocupado → 00001; 99999 esgotados →
  exceção; `reset()` limpa; dois S-1000 idênticos no mesmo processo → `evtid` diferentes e lote
  com os dois válido.
- **Grupos/lote**: todo código de `Event::$codes` exceto S-8200/S-8299 está em exatamente um
  grupo, e todo código dos grupos está em `Event::$codes`; `enviarLoteXmlEventos("1", [...])`
  funciona; grupos 0, 4 e "x" recusados com a mensagem; XML de **S-8200** (código em
  `Event::$codes` sem grupo) recusado com a mensagem de "não pode ser enviado em lote" (XML de
  código fora de `Event::$codes` continua parando em "Falha na localização do ID").
- **XsdSeeker**: `seek(v1_5_0)` e depois `seek(v1_1_0)` → `ConsultaIdentificadoresEventos*`
  vazios no segundo; pasta inexistente → lista com versões vazias (reescreve o teste atual).

## Não-objetivos / fora de escopo

- S-50xx com erro fatal, fábricas de eventos extintos, S-8200/S-8299, alias `s3500`,
  deprecations PHP 8.4, `require.php`, código morto, remoção de `v1_5_0` e de `console/*.crt`
  → **SP2**.
- Pacotes XSD 2026-11-23/12-14/2027-01-18, correção do `compare_xsd.py`/chave de mensagens do
  `check.py`, Mensagens do Sistema 2.5 → **SP3**.
- Reescrita de `docs/*.md`, README "Forma de uso", tags semver → **SP4**.
- Bundle de CAs combinado (store + extra); verificação de certificado de cliente; parser de
  `RetornoEvento` v1_3_0 (`rubricas`, `cpfs`).
- Mudanças no enviaesocial (ver Rollout).

## Restrição de simplicidade

Sem novas classes além do necessário: `securityOptions()` é um método no `SoapCurl`; o registro
de Ids vive no `FactoryId`; grupos continuam um array. Sem configuração nova no `config` JSON.

## Appetite

Médio-pequeno: um plano de ~8 tasks, executável numa sessão.

## MVP vs MLP

MVP: corrigir o comportamento e provar com testes. Sem refactor de `Tools`/`SoapCurl` além dos
pontos citados.

## Decisões (ADR)

**D1 — Pacote 1.6-Alfa em `v1_6_0`.** Contexto: base 1.6 barra CNPJ alfanumérico. Decisão: Alfa,
superconjunto compatível com numérico (MOD v1.16). Alternativa descartada: 1.6 base.
Consequência: `communication` = "1.6.0-Alfa"; check.py precisa entender o sufixo.

**D2 — `v1_5_0` mantido por ora.** Quem passa `serviceVersion '1.5.0'` (o consumidor hoje)
não quebra; mas continua sem CNPJ alfanumérico até trocar. Remoção no SP2.

**D3 — TLS ligado por padrão com opt-out.** Alternativa descartada: sem opt-out (proxy que
intercepta TLS ficaria sem saída). `loadCA` substitui o store (comportamento do cURL);
documentado, sem bundle combinado.

**D4 — `securityOptions()` como ponto de teste.** Alternativas descartadas: wrapper de cURL
injetável (mais código), servidor HTTPS local (lento/frágil).

**D5 — Id único no processo + `random_int`.** Alternativas descartadas: só aleatório (colisão
com seq=1 continua), não mexer (deixa o bug). Consequência: montar o mesmo evento duas vezes
no mesmo processo gera Ids diferentes; reenvio idempotente deve reusar o XML assinado (como o
cenário 11 do enviaesocial já faz).

**D6 — Teste do check.py.** O check.py é Python e não é coberto pela suíte Pest. Decisão: teste
em Python com `unittest` (stdlib) em `.claude/skills/update-esocial/scripts/test_check.py`,
rodado no plano com `python3 -m unittest`; a lógica de versão/regex é extraída em função pura
para o teste não depender de rede.

## Restrições verificáveis

1. `grep -n "curl_setopt(.*SSL_VERIFY" src/Common/Soap/SoapCurl.php` → vazio (as opções de
   verificação só existem dentro de `securityOptions()`).
2. `composer test` verde; `vendor/bin/pest --group=network` verde com rede.
3. Teste "todo código de `Event::$codes` (exceto S-8200/S-8299) em exatamente um grupo" verde.
4. `php .claude/skills/update-esocial/scripts/validate.php` → 40/40.
5. `git diff --stat` não toca `src/Factories/`, `jsonSchemes/` nem `schemes/v_S_*`.

## Suposições

| Suposição | Risco se for falsa | Como validar |
|---|---|---|
| O webservice aceita lote validado pelo XSD 1.6-Alfa com o mesmo namespace `v1_1_1` | lote recusado no servidor após a troca | namespaces idênticos (`referencias/`); enviaesocial reenvia um cenário na Produção Restrita com 1.6.0 |
| O store de CAs das imagens de produção tem GlobalSign Root R46 | envio para de funcionar com TLS ligado | `vendor/bin/pest --group=network` dentro da imagem `php:8.3-fpm-alpine` do enviaesocial |
| Nenhum consumidor depende de Id igual ao reconstruir o mesmo evento no mesmo processo | reconciliação por Id quebra | enviaesocial: cenário 11 reenvia XML assinado (não reconstrói) |

## Rollout e reversibilidade

- Biblioteca: commits no `master` do fork (consumidor está em `dev-master`; ver SP4 para tags).
- **Dependências no enviaesocial (fora deste repo), em ordem:**
  1. `composer update systemweb-dev/esocial`;
  2. trocar `serviceVersion` para `'1.6.0'` (sem isso o CNPJ alfanumérico continua barrado);
  3. garantir `ca-certificates` atualizado na imagem (`apk add --no-cache ca-certificates`)
     e rodar a checagem `network` dentro dela;
  4. alocar `sequencial` por empregador e segundo quando houver workers concorrentes;
  5. atualizar o mock do frontend (`retornoEvento/v1_2_1` → `v1_3_0`);
  6. reexecutar na Produção Restrita os cenários de lote (grupos 1, 2, 3) e um com CNPJ
     alfanumérico, se houver empregador de teste.
- Volta: reverter os commits; TLS pode ser desligado sem reverter via `disableSecurity(true)`.

## Decisões da execução

- **`wsdl()` provado pela restrição 1, não por teste.** O PHP não permite ler de volta as opções
  de um handle cURL; a garantia de que `send()` e `wsdl()` usam `securityOptions()` é o
  `grep -n "curl_setopt(.*SSL_VERIFY" src/Common/Soap/SoapCurl.php` vazio.
- **Grupos testados no sentido observável.** Cada evento de `Event::$codes` (exceto S-8200/S-8299)
  é aceito em exatamente um grupo (`tests/Unit/GruposTest.php`); a ausência de códigos
  inexistentes na tabela não é observável pela API e não tem teste próprio.
- **Mensagens de teste mais estritas** (juízes): a recusa do CNPJ alfanumérico no 1.5.0 exige a
  mensagem do pattern do `nrInsc`; as asserções de envio miram o cabeçalho do lote; o helper de
  grupos relança `ValidatorException` para não mascarar falha de validação.
- **`FactoryId::reserve()` com `unset($used)`**: sem isso o registro é O(n²) por copy-on-write
  (teste dos 99999 sequenciais em 26 s → 0,1 s).
- **`normalizeGrupo()`** mostra o tipo quando o grupo não é escalar.
- **Para o SP2:** alias `EVT_TABELAS` para `EVT_INICIAIS`; recusar `sequencial` > 99999 no
  `FactoryId` (hoje gera Id de 37 caracteres; o JSON schema já barra na entrada).
- Entregue nos commits do `master` listados no `plan.md`.
