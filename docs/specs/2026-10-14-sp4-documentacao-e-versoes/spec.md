---
titulo: SP4 documentacao e versoes
slug: 2026-10-14-sp4-documentacao-e-versoes
criado: 2026-10-14
estado: concluido
---

# SP4 — documentação e versões

> Dossiê deste trabalho. `spec.md` é a fonte da verdade do design; `plan.md` é o passo a
> passo de execução; `referencias/` guarda o material de apoio.

Último de quatro sub-projetos (SP1 envio seguro, SP2 limpeza da API e SP3 atualizações do
eSocial — entregues).

## Objetivo & outcome

1. A documentação por evento passa a ser **gerada** dos JSON schemas e do XSD oficial, para não
   desatualizar a cada nota técnica: `docs/eventos/`, uma página por evento que a lib monta.
2. O README ganha a **Forma de uso** completa e a seção **Atualização com o gov.br**;
   `docs/Tools.md` e `docs/Config.md` descrevem a API e o config atuais.
3. O fork ganha versão: tag **v2.0.0** no `master` e semver daqui em diante.

## Estado atual (levantamento de 2026-10-14)

- `docs/`: 54 arquivos. 17 docs de eventos que a lib não monta mais (SP2), 8 eventos sem doc
  (`EvtCdBenefAlt`, `EvtCdBenefIn`, `EvtCdBenTerm`, `EvtCessao`, `EvtConsolidContProc`,
  `EvtContProc`, `EvtExcProcTrab`, `EvtReativBen`), `docs/EvtFGTS` sem extensão (10 bytes),
  `EventosvS_1.0.md` do leiaute S-1.0. As docs que restam descrevem campos do leiaute antigo
  (ex.: `nmRazao` no S-1000).
- README: "Forma de uso — Em breve ....". `docs/Tools.md` (18 linhas) só mostra o construtor;
  `docs/Config.md` traz `eventoVersion '2.2.2'`.
- Nenhuma tag no fork; o upstream parou em `v1.0.15` (2023-11-19). O enviaesocial usa
  `systemweb-dev/esocial: dev-master` via repositório VCS.
- `CHANGELOG.md` `## Unreleased` tem `Added` e `Fixed`; as remoções e mudanças incompatíveis do
  SP1/SP2 não estão registradas.

## Decisões (do usuário)

| Decisão | Escolha |
|---|---|
| Docs por evento | geradas dos schemas (script na skill `update-esocial`, com teste) |
| Versões | `v2.0.0` agora + semver: pacote XSD/campo novo = minor, correção = patch, remoção = major |
| README | Forma de uso completa, Atualização com o gov.br, `Tools.md` e `Config.md` |
| Fora | seção de instalação do README (não escolhida: fica como está) |

## 1. Gerador das docs por evento

`.claude/skills/update-esocial/scripts/gen_docs.py`, Python stdlib.

- **Entrada:** `jsonSchemes/v_S_01_03_00/<evt>.schema` (com os `$ref` de
  `jsonSchemes/definitions.schema` resolvidos), `schemes/v_S_01_03_00/<evt>.xsd` + `tipos.xsd`,
  os aliases `'sNNNN' => 'evtxxx'` de `src/Event.php`, os grupos de `src/Common/Tools.php`
  (`$grupos`) e `xsd_package` do `ESOCIAL-VERSION.json`.
- **Saída:** `docs/eventos/S-NNNN.md` por evento montado (os de `Event::$available`) e
  `docs/eventos/README.md` (índice: código, nome, classe, `Event::sNNNN()`, grupo, link para o
  exemplo em `examples/Fake/v_S_01_03_00/`).
- **Página do evento:** cabeçalho (código, nome do XSD, classe, grupo do lote, pacote XSD da
  geração, exemplo); tabela **Campo** (caminho no `stdClass`, ex. `infocadastro/indpertirrf`) ·
  **Tipo** · **Obrigatório** · **Regra** (pattern, mínimo/máximo, tamanho, itens) ·
  **Descrição** (primeira `xs:documentation` do XSD + a linha `Validação:` quando houver).
- **Casamento campo → XSD:** pelo nome em minúsculo entre `xs:element` **e** `xs:attribute`
  (o S-2501 usa atributos); havendo vários, o que tem como pai o grupo do JSON (ou `T_<grupo>`).
  Protótipo contra os 40 eventos: 1722 campos, 1678 com descrição; os 44 sem par são campos da
  lib (`sequencial`, `modo`, envelopes do S-2306) ou divergências do JSON schema.
- **Campos sem par no XSD** vão numa seção "Campos sem correspondência no XSD" da página (não
  somem em silêncio). As divergências encontradas (ex.: `fatorrbr` e `detoper` no S-1200,
  `remumperant`/`itensremum` no S-1202, `cargoduncao` no S-2300) **não são corrigidas no SP4**:
  viram a lista de entrada de um próximo trabalho.
- **`--check`:** regenera em memória e sai com código 1 se algum arquivo de `docs/eventos/`
  difere (para a skill e a verificação). A skill ganha o passo "regenerar as docs" junto da
  aplicação de pacote.
- **Remove** `docs/Evt*.md`, `docs/EvtFGTS` e `docs/EventosvS_1.0.md` (o histórico fica no git).

## 2. README, `docs/Tools.md`, `docs/Config.md`

- **Forma de uso** (README): config, certificado lido do PFX em memória, montar evento
  (`Event::s1000(...)` / `Event::evtInfoEmpregador(...)`), enviar lote (`Tools::EVT_TABELAS`),
  consultar lote pelo protocolo, ler o retorno e buscar a ação com `SystemMessages::get()`,
  e a verificação TLS (padrão, `loadCA`, `disableSecurity`). Aponta para `docs/eventos/`.
- **Atualização com o gov.br** (README): `ESOCIAL-VERSION.json`, skill `update-esocial`,
  branches `xsd/<data>` e as datas de merge, política de versões.
- **`docs/Tools.md`:** todos os métodos públicos do `Tools` (construtor, `loadSoapClass`,
  `consultarLoteEventos`, `consultarEventosEmpregador`, `consultarEventosTabela`,
  `consultarEventosTrabalhador`, `downloadEventosPorId`, `downloadEventosPorNrRecibo`,
  `enviarLoteEventos`, `enviarLoteXmlEventos`), as constantes de grupo, `lastRequest`/
  `lastResponse`, limite de 50 eventos e os erros que lançam.
- **`docs/Config.md`:** as chaves lidas (`tpAmb`, `verProc`, `eventoVersion 'S.1.3.0'`,
  `serviceVersion '1.6.0'`, `empregador.tpInsc/nrInsc/nmRazao`, `transmissor.tpInsc/nrInsc`),
  o que recusa (leiaute abaixo de 1.3) e o `sequencial` do Id.
- **Teste das docs** (`test_docs.py`, `unittest`): todo `$tools->metodo(`, `Tools::CONST`,
  `Event::metodo(` e `SystemMessages::metodo(` citado em `README.md`, `docs/Tools.md` e
  `docs/Config.md` existe no código; todo link relativo aponta para arquivo existente.

## 3. Versões

- `CHANGELOG.md`: o `## Unreleased` vira `## 2.0.0 - <data da tag>` e ganha `### Changed` e
  `### Removed` com o que o SP1/SP2 mudou de forma incompatível (TLS verificado por padrão,
  `loadCA` substitui o store, só leiaute S-1.3, eventos e classes removidos, PHP >= 8.1,
  `EVT_INICIAIS` obsoleto, `sequencial` 1..99999). Um `## Unreleased` vazio fica no topo.
- Tag anotada `v2.0.0` no commit do `master` que fecha o SP4; push da tag com o ok do usuário.
- Branches `xsd/*`: rebase sobre o novo `master`, com o item de cada pacote movido para o
  `## Unreleased`. No merge de cada data: `## 2.N.0`, tag `v2.N.0` (2.1.0 em 09/11, 2.2.0 em
  07/12, 2.3.0 em 18/01). A skill (passo 10) e o `AGENTS.md` registram a política.
- enviaesocial (fora deste repo, aviso ao usuário): trocar `dev-master` por `^2.0`.

## Testes

- `unittest` para `gen_docs.py` com JSON schema e XSD mínimos em pasta temporária: `$ref`
  resolvido, campo em array, atributo do XSD, desempate pelo pai, campo sem par listado, nome e
  grupo do evento a partir de um `Event.php`/`Tools.php` de fixture, `--check` acusando arquivo
  desatualizado.
- `test_docs.py` (seção 2).
- Suíte Pest e `validate.php` inalterados e verdes (o SP4 não muda código da lib).

## Restrições verificáveis

| Restrição | Como checar |
|---|---|
| Uma página por evento de `Event::$available` (40) e índice | `ls docs/eventos/S-*.md \| wc -l` = 40 |
| Docs em dia | `gen_docs.py --check` sai 0 |
| Nenhuma doc antiga | `ls docs/Evt* docs/EventosvS_1.0.md` sem resultado |
| README/Tools/Config só citam API existente | `test_docs.py` verde |
| Suíte | `composer test` e `unittest` verdes; `validate.php` 40/40 |
| Versão | `git describe --tags` = `v2.0.0` no `master`; `CHANGELOG.md` com `## 2.0.0` |

## Suposições

| Suposição | Risco | Como validar |
|---|---|---|
| O casamento por nome dá a descrição certa | médio: nome repetido em grupos diferentes | protótipo: 146 casos com mais de um candidato; desempate pelo pai; conferir 10 páginas à mão |
| Docs geradas são legíveis no GitHub | baixo | abrir 2 páginas grandes (S-2200, S-1200) |
| Os branches `xsd/*` rebaseiam sem conflito difícil | baixo: só o CHANGELOG | rebase local, suíte em cada branch |
