---
titulo: SP3 atualizacoes do eSocial
slug: 2026-10-14-sp3-atualizacoes-do-esocial
criado: 2026-10-14
estado: concluido
---

# SP3 — atualizações do eSocial

> Dossiê deste trabalho. `spec.md` é a fonte da verdade do design; `plan.md` é o passo a
> passo de execução; `referencias/` guarda o material de apoio.

Terceiro de quatro sub-projetos (SP1 envio seguro e SP2 limpeza da API — entregues; SP4
documentação e versionamento).

## Objetivo & outcome

1. As ferramentas da skill `update-esocial` param de enganar: o `check.py` não acusa sempre
   novidade nas Mensagens e o `compare_xsd.py` passa a enxergar mudança de enumeração em tipo
   nomeado de `tipos.xsd`.
2. O S-1000 aceita o `indPertIRRF` (já no XSD atual, ausente no montador desde o import).
3. A lib traz o catálogo oficial das Mensagens do Sistema (v2.5) com a "Ação Sugerida", que o
   retorno do lote não traz: `Common\SystemMessages::get()`.
4. Os três pacotes de XSD pendentes ficam **prontos em branches**, cada um com montador, schema e
   testes, para o merge no `master` perto da data, como manda a regra do fork.

Outcome verificável: `composer test` e `python3 -m unittest` verdes no `master`; cada branch com
`validate.php` 40/40 e suíte verde; `check.py` mostra os pacotes prontos em branch com a data.

## Contexto (fatos levantados)

- `ESOCIAL-VERSION.json` usa a chave `system_messages`; `check.py` compara com
  `state.get('mensagens_sistema')` (sempre `None`) → sempre "novidade".
- `compare_xsd.py` comparou `tipos.xsd` do pacote 2026-11-23 como "só documentação", mas
  `TS_indOrgReemb` ganhou o valor `3`: o script não compara `simpleType` nomeado.
- S-1000 `infoCadastro`, ordem do XSD atual: `classTrib, indCoop, indConstr, indDesFolha,
  indOpcCP, indPorte, indOptRegEletron, cnpjEFR, dtTrans11096, indTribFolhaPisPasep,
  indPertIRRF, dadosIsencao, …`. `indPertIRRF`: `minOccurs="0"`, enumeração `S`, "preenchimento
  exclusivo para natureza jurídica 126-0, 127-9, 129-5, 130-9" (regra do servidor).
- Mensagens do Sistema v2.5: PDF oficial
  `https://www.gov.br/esocial/pt-br/documentacao-tecnica/manuais/998566-mensagensdosistema-v2-5.pdf`
  (129 páginas). Texto extraído com `pdftotext` (sem `-layout`), conferido pelo revisor do spec:
  - entradas `MSnnnn - texto`, **coladas** (sem linha em branco entre elas); ~1032 códigos-base;
  - 33 entradas com sufixo, ex.: `MS0174_000000`, `MS0266_V01_00_00`, `MS0846_V01_03_00`
    (`MS0266` e `MS0886` só existem com sufixo);
  - marcador de ação: `Ação Sugerida:` (495), `Ação sugerida:` (122) e `Ações Sugeridas:` (11);
    na maioria o texto da ação vem **na mesma linha**; em 49 casos o marcador fica sozinho e é
    seguido de parágrafos sem `- `; só o plural usa itens `- `; `Ação sugerida para evitar o
    erro:` aparece no **meio** de alguns textos (MS1089 a MS1092) e é texto, não marcador;
  - sem cabeçalho/rodapé repetido: o único ruído é o **número da página** (linha só com dígitos,
    entre linhas em branco, antes do `\f`; na última página sai partido em `12` e `9`);
  - antes do `MS0001` há histórico, índice e introdução (ignorar).
  O `<ocorrencia><codigo>N</codigo>` do retorno corresponde a `MS` + N com 4 dígitos (conferido
  em XMLs reais do enviaesocial).
- Pacotes de XSD (já baixados e extraídos no scratchpad da sessão, `analise-xsd/<data>/x/`,
  52 `.xsd` cada; URLs `https://www.gov.br/esocial/pt-br/documentacao-tecnica/manuais/<data>_esquemas_xsd_v_s_01_03_00-1.zip`):

  | Pacote | Item NT 07/2026 | Produção Restrita | Produção | Mudança estrutural |
  |---|---|---|---|---|
  | 2026-11-23 | 3.4 | 09/11/2026 | 23/11/2026 | S-1000 `indOptRegEletron` vira `minOccurs="0"` (servidor recusa se informado com `iniValid >= 2026-02`); `TS_indOrgReemb` (S-1210) ganha `3` |
  | 2026-12-14 | 3.5 | 07/12/2026 | 14/12/2026 | S-1000 novo `infoCadastro/indSiafi` (opcional, `S`) logo após `indPertIRRF`; demais só retorno/documentação |
  | 2027-01-18 | 3.6 | — | 18/01/2027 | S-8200 ganha `observacoes` (lib não monta S-8200: só copiar) |

  Os 40 exemplos atuais validam nos três XSD novos (nenhum pacote invalida XML de hoje).

## Escopo

### 1. Ferramentas da `update-esocial` (master)

- `check.py`: Mensagens comparadas com `state.get('system_messages')` usando a mesma ordenação de
  versão (`2.5 == 2.5` → sem novidade; `null` → novidade; `2.6` publicada → novidade). A lógica
  vai para uma função pura testável (`msgs_is_news(published, applied)`).
- `check.py`: lê `pending_packages` do `ESOCIAL-VERSION.json` (ver Seção 4). Um pacote novo
  publicado que esteja lá aparece como `pronto no branch <branch> (merge em <data>)` e **continua
  contando como novidade** (saída 10) até o merge, porque o `master` ainda não o tem; a linha
  "Próximo passo" passa a dizer `fazer o merge de <branch> em <data>` para esse pacote.
- `compare_xsd.py`: além dos elementos, compara cada `xs:simpleType name="…"` (enumerações e
  facetas `pattern`, `minLength`, `maxLength`, `totalDigits`, `fractionDigits`, `minInclusive`,
  `maxInclusive`) e relata `"<tipo>: valores +[...] -[...]"` / `"<tipo>: <faceta> a → b"`; um arquivo
  com essas diferenças conta como estrutural. O caminho dos elementos passa a levar o nome do tipo
  nomeado em que estão (`#T_horContratual_2206/qtdHrsSem`), porque hoje elementos de tipos
  diferentes colidem na mesma chave (28 colisões em `tipos.xsd`) e só o último é comparado.
- Testes `unittest` em `.claude/skills/update-esocial/scripts/` (ao lado do `test_check.py`):
  `check.py` (mensagens, pacotes pendentes) e `compare_xsd.py` (dois `tipos.xsd` mínimos de
  fixture criados no teste em pasta temporária: `TS_indOrgReemb` com `1,2` e com `1,2,3`).

### 2. `indPertIRRF` no S-1000 (master)

- `TraitS1000::toNodeS130`: `addChild($infoCadastro, "indPertIRRF", $cad->indpertirrf ?? null,
  false)` logo depois de `indTribFolhaPisPasep`, no **único** bloco que monta o `infoCadastro`
  (ele é anexado depois na inclusão e na alteração; não editar dois lugares).
- `jsonSchemes/v_S_01_03_00/evtInfoEmpregador.schema`: `indpertirrf` opcional,
  `["string","null"]`, `pattern ^S$`.
- Testes em `tests/Unit/Factories/EvtInfoEmpregadorTest.php`.

### 3. Catálogo das Mensagens do Sistema (master)

- `.claude/skills/update-esocial/scripts/extract_mensagens.py <pdf> [saida.json]`: roda
  `pdftotext` **sem** `-layout` (o texto corrido já separa as entradas, como no texto extraído na
  análise) e interpreta:
  - ignora tudo antes da primeira linha `MS0001`;
  - remove os números de página: linhas só com dígitos (`^\d+$`) isoladas por linhas em branco
    ou no fim de página (antes do `\f`), inclusive o par `12`/`9` da última página;
  - uma entrada começa em linha `^(MS\d{4})(_[A-Z0-9_]+)? - ` e termina na próxima linha que
    abre outra entrada ou no fim do arquivo (as entradas são coladas);
  - marcador de ação **só no início da linha**: `^(Ações Sugeridas|Ação [Ss]ugerida):`. O que vem
    depois dos dois-pontos na mesma linha é uma ação; itens `- ` são ações separadas (podem
    continuar na linha seguinte); parágrafos sem `- ` depois do marcador viram **uma** ação, com
    as linhas unidas por espaço. `Ação sugerida para evitar o erro:` no meio do texto continua
    no texto;
  - linhas quebradas do texto são unidas com espaço; mantém `{0}`, `{1}`… como estão.
- Saída `data/mensagens-sistema.json` (versionada):
  `{"versao": "2.5", "fonte": "<url>", "mensagens": {"MS0002": {"texto": "...", "acoes": []}, "MS0266_V01_00_00": {...}, ...}}`
  — variantes com sufixo guardadas com a chave completa; chaves em ordem, `ensure_ascii=False`,
  indentação 2.
- `src/Common/SystemMessages.php` (`NFePHP\eSocial\Common\SystemMessages`):
  - `get($codigo): ?array` — aceita `191`, `"191"`, `"0191"`, `"MS0191"` e o código com sufixo
    (`"MS0266_V01_00_00"`, que devolve só aquela variante). Pelo número, devolve
    `['codigo' => 'MS0191', 'texto' => ..., 'acoes' => [...], 'variantes' => [...]]`: a
    mensagem-base (se existir) e, em `variantes`, as entradas com sufixo do mesmo número (cada uma
    com `codigo`, `texto`, `acoes`). Se só houver variantes (ex.: `get(266)`), `texto` e `acoes`
    são `null` e `variantes` traz todas. Código inexistente → `null`;
  - `version(): string` — versão do catálogo;
  - lê `data/mensagens-sistema.json` uma vez por processo (cache estático).
- `ESOCIAL-VERSION.json`: `system_messages: "2.5"`.
- `SKILL.md` da `update-esocial`: passo para nova versão das Mensagens (baixar o PDF numa pasta
  vazia, rodar o script, conferir o diff do JSON, atualizar `system_messages`).
- Testes: parser (`unittest`, texto fixo com: entrada simples; entrada multilinha; `Ações
  Sugeridas:` com 2 itens `- `; `Ação Sugerida:` com o texto na mesma linha; marcador sozinho
  seguido de parágrafo; `Ação sugerida para evitar o erro:` no meio do texto; variante com
  sufixo; número de página entre entradas) e helper (Pest).

### 4. Pacotes de XSD em branches

- `pending_packages` no `ESOCIAL-VERSION.json` (master), com descrição em `_comments`:
  `[{"xsd_package": "2026-11-23", "branch": "xsd/2026-11-23", "merge_on": "2026-11-09"},
  {"xsd_package": "2026-12-14", "branch": "xsd/2026-12-14", "merge_on": "2026-12-07"},
  {"xsd_package": "2027-01-18", "branch": "xsd/2027-01-18", "merge_on": "2027-01-18"}]`.
  No branch de cada pacote, o item correspondente sai da lista e `xsd_package` avança.
- `xsd/2026-11-23` (do `master` com as Seções 1-3):
  - copia os 52 XSD do pacote para `schemes/v_S_01_03_00/`;
  - `TraitS1000::toNodeS130`: `indOptRegEletron` passa a `?? null` com `false`;
    `evtInfoEmpregador.schema`: `indoptregeletron` com `required: false` e `["integer","null"]`;
  - `evtPgtos.schema`: `indorgreemb` pattern `^(1|2|3)$`;
  - exemplo `Fake_s1000_EvtInfoEmpregador.php`: comentário com a regra (não informar se
    `iniValid >= 2026-02`);
  - `ESOCIAL-VERSION.json` (`xsd_package`, `updated_at`, `pending_packages`) e `CHANGELOG.md`.
- `xsd/2026-12-14` (do `xsd/2026-11-23`): XSD; `indSiafi` (`?? null`, `false`) logo após
  `indPertIRRF` no `toNodeS130`; schema `indsiafi` `^S$`; `ESOCIAL-VERSION`, `CHANGELOG`.
- `xsd/2027-01-18` (do `xsd/2026-12-14`): só XSD; `ESOCIAL-VERSION`, `CHANGELOG`.
- Os três branches vão para o `origin` (não afetam o `master` nem quem usa `dev-master`).
- `AGENTS.md` (master), em "Manter atualizado": os pacotes prontos ficam em `xsd/<data>`; na data
  de `merge_on`, rodar `check.py`, fazer o merge do branch no `master`, rodar a suíte e o
  `validate.php`, push; avisar o enviaesocial (`composer update` + reteste na Produção Restrita).

## Testes (Pest e unittest, AAA, dados fictícios, sem rede)

- `check.py`: mensagens 2.5/2.5 sem novidade, `null` com novidade, 2.6 com novidade; pacote
  publicado presente em `pending_packages` sai como "pronto no branch … (merge em …)".
- `compare_xsd.py`: enum `+['3']` em `TS_indOrgReemb` detectado e arquivo marcado estrutural;
  `tipos.xsd` idênticos → nada.
- `extract_mensagens.py`: texto fixo → estrutura esperada (os 8 casos da Seção 3: texto
  multilinha unido, ações nos 3 formatos, marcador no meio do texto mantido, variante com chave
  completa, número de página removido, `{0}` preservado).
- `SystemMessages` (Pest): `get(191)` → texto e as 2 ações do PDF; `191`/`"191"`/`"0191"`/
  `"MS0191"` iguais; `get(266)` → `texto` `null` e as variantes de MS0266; `get("MS0266_V01_00_00")`
  → só a variante; desconhecido → `null`; `version()` = `"2.5"`.
- `indPertIRRF`: informado → sai logo após `indTribFolhaPisPasep`; omitido → não sai; `"N"`
  recusado pelo schema.
- Branch 2026-11-23: S-1000 sem `indOptRegEletron` válido no XSD novo e sem a tag; S-1210 com
  `indOrgReemb = 3` aceito; `compare_xsd.py` (já corrigido) contra o pacote lista o enum.
- Branch 2026-12-14: `indSiafi` logo após `indPertIRRF`; omitido não sai; `"N"` recusado.
- Todo branch: `validate.php` 40/40 e `composer test` verde.

## Não-objetivos / fora de escopo

- Aplicar os pacotes no `master` agora (só na data de cada `merge_on`).
- Regras de negócio do servidor na lib (omitir/recusar `indOptRegEletron` por data, natureza
  jurídica do `indPertIRRF`, `observacao` obrigatória se `tpRubr = 4`).
- Montador do S-8200 e parser dos retornos S-5001/S-5011.
- Pacote de Comunicação (resolvido no SP1).
- Usar o catálogo dentro do `Standardize` ou do `Tools` (o consumidor decide onde usar).
- Documentação geral e tags (SP4).

## Restrição de simplicidade

Uma classe nova (`SystemMessages`) e um arquivo de dados; o resto são ajustes pontuais em
scripts e montadores. Sem dependência PHP nova; o `pdftotext` só é usado pelo script da skill,
nunca pela lib.

## Appetite

Médio: um plano de ~8 tasks no master + 3 tasks de branch.

## MVP vs MLP

MVP: ferramentas confiáveis, catálogo consultável, pacotes prontos e testados.

## Decisões (ADR)

**D1 — Pacotes em branches, merge na data.** Alternativas: só consertar ferramentas (deixa o
trabalho para a véspera); aplicar tudo no master (lib aceitaria campos que o governo ainda
recusa). Consequência: três branches encadeados para manter até a data; a data fica registrada em
`pending_packages`. O merge acontece na abertura da Produção Restrita (09/11 e 07/12): entre essa
data e a de produção (23/11 e 14/12), a **Produção** ainda recusa `indOrgReemb = 3` e o
`indSiafi` — quem chama só deve enviá-los em produção a partir da data de produção.

**D2 — `indOptRegEletron` só opcional.** Alternativas: omitir pela data (some com o dado em
silêncio); recusar na validação (regra do servidor dentro da lib). Consequência: quem chama
decide; a regra fica documentada.

**D3 — Catálogo na lib, gerado por script e versionado.** Alternativas: só registrar a versão;
catálogo só no enviaesocial; parser de PDF em PHP. Consequência: `data/` novo no pacote
(~200-300 KB de JSON); diff legível entre versões.

**D4 — `indPertIRRF` corrigido agora no master.** Já está no XSD atual; o `indSiafi` do pacote
3.5 entra logo depois dele.

## Restrições verificáveis

1. `master`: `composer test` e `python3 -m unittest discover -s .claude/skills/update-esocial/scripts -p 'test_*.py'` verdes; resumo do Pest sem `deprecated`/`failed`.
2. `python3 .claude/skills/update-esocial/scripts/compare_xsd.py schemes/v_S_01_03_00 <pacote 2026-11-23>` lista `TS_indOrgReemb` com `+['3']`.
3. `data/mensagens-sistema.json` tem entre 1000 e 1200 mensagens, contém MS0002, MS0191, MS1018 e
   MS0266_V01_00_00, e nenhum `texto` casa `(Ação [Ss]ugerida|Ações Sugeridas):` (marcador que
   devia ter virado ação); conferido por um comando `python3 -c` no plano.
4. Cada branch `xsd/<data>`: `validate.php` 40/40, `composer test` verde e `ESOCIAL-VERSION.json`
   com `xsd_package = <data>`; `git log master..xsd/2026-12-14` contém os commits do
   `xsd/2026-11-23`.

## Suposições

| Suposição | Risco se for falsa | Como validar |
|---|---|---|
| O texto do `pdftotext` separa cada mensagem de forma estável (cabeçalho/rodapé removíveis) | catálogo com texto truncado ou misturado | spike na task 1: contar entradas, conferir 3 códigos e 2 entradas com ações contra o PDF |
| Os XSD dos pacotes não mudam até a data (o gov.br pode republicar) | branch com XSD defasado | na data, `check.py` + `compare_xsd.py` contra o zip do dia antes do merge |

## Rollout e reversibilidade

- `master`: commits normais (nada muda para o enviaesocial além do helper novo e do
  `indPertIRRF`, ambos opcionais).
- Branches: merge na data registrada; reverter = `git revert` do merge.
- enviaesocial, depois de cada merge: `composer update systemweb-dev/esocial` e reteste na
  Produção Restrita dos eventos afetados (S-1000, S-1210).

## Revisões do spec

- **2026-10-14** — revisor do spec testou o parser no PDF real: marcadores de ação em 3 formas
  (texto na mesma linha, parágrafo, itens), entradas coladas, 33 códigos com sufixo, ruído só de
  número de página. Seção 3 reescrita; decisão do usuário: `get(número)` devolve a base e as
  variantes. Também: caminho do `compare_xsd.py` com o nome do tipo nomeado; pacote pronto em
  branch continua como novidade até o merge; `infoCadastro` montado num bloco só; nota sobre a
  janela entre Produção Restrita e produção.
