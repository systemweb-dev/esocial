---
name: update-esocial
description: Verifica no Portal do eSocial (gov.br) se há pacote de XSD, nota técnica, pacote de comunicação ou mensagens novas (o upstream NFePHP é só consulta), e aplica a atualização no fork com diff e validação, parando antes do commit. Use quando pedirem para atualizar o eSocial, aplicar uma nota técnica ou checar se o fork está em dia.
---

# Atualizar o fork do eSocial

Estado aplicado: `ESOCIAL-VERSION.json` (raiz). Fontes: https://www.gov.br/esocial/pt-br/documentacao-tecnica
(principal), https://www.gov.br/esocial/pt-br/centrais-de-conteudo/agenda/agenda-1 (datas por ambiente)
e https://github.com/nfephp-org/sped-esocial (upstream, **só consulta**).

**O que vale é o eSocial.** Toda mudança no fork tem de vir do XSD, da nota técnica ou do leiaute
publicados no gov.br. O upstream serve para ver como a comunidade implementou algo; nada entra no fork
só porque está lá, e o que for aproveitado precisa bater com a documentação oficial.

## 1. Verificar
`python3 .claude/skills/update-esocial/scripts/check.py`
- Saída 0: nada novo, encerre dizendo isso.
- Saída 2: fonte ilegível; mostre o erro e pare.
- Saída 10: há novidade; mostre o relatório ao usuário.

## 2. Escolher
Aplique **um pacote de XSD por vez**: o de menor data de produção ainda não aplicado. Confira na Agenda
as datas da Produção Restrita e da produção e diga-as ao usuário. Comunicação e Mensagens do Sistema são
trabalhos separados: só relate.

## 3. Comparar
Baixe o zip para uma pasta temporária **vazia** (nunca dentro do repositório) e rode
`python3 .claude/skills/update-esocial/scripts/compare_xsd.py schemes/v_S_01_03_00 <pasta-extraida>`.

## 4. Desenhar (se estrutural)
Se houver elemento novo/removido, mudança de obrigatoriedade ou de tipo: use a skill `sw-brainstorming`
se estiver disponível para decidir as mudanças nos montadores e JSON schemas; sem ela, liste as mudanças
propostas e peça aprovação antes de editar. Mudança só de documentação/enumeração simples: siga direto.

## 5. Aplicar
- Copie os `.xsd` do pacote para `schemes/v_S_01_03_00/` (sobrescreve; mesma estrutura de pastas).
- Ajuste só os `src/Factories/Traits/TraitSxxxx.php` (método `toNodeS130`) e
  `jsonSchemes/v_S_01_03_00/*.schema` afetados. Diffs mínimos: não reformate arquivos inteiros.
- Campo opcional no XSD (`minOccurs="0"`) é gerado com `?? null` e `false` no `addChild`.
- Se ajudar, consulte o upstream para ver como implementaram a mesma mudança, mas implemente a
  partir do XSD/nota técnica oficial e confira contra eles.

## 6. Validar
- `php .claude/skills/update-esocial/scripts/validate.php` (todos os exemplos válidos no XSD novo)
- `composer test` (suíte Pest)
- `python3 .claude/skills/update-esocial/scripts/gen_docs.py` (regenera `docs/eventos/`; confira o
  diff e a seção "Campos sem correspondência no XSD" das páginas alteradas)
- `python3 .claude/skills/update-esocial/scripts/check_docs.py` (README, Tools.md e Config.md só
  citam API existente)

## 7. Parar antes do commit
Mostre: pacote aplicado, arquivos alterados, eventos afetados, resultado da validação. Com o ok do
usuário: atualize `ESOCIAL-VERSION.json` (`xsd_package`, `technical_note`, `upstream_reviewed`,
`updated_at`) e `CHANGELOG.md`, faça commit em português no `master`. Push só com ok explícito.

## 8. Avisar o enviaesocial
Lembre de rodar `composer update systemweb-dev/esocial` na api do enviaesocial e reexecutar, na
Produção Restrita, os cenários de `enviaesocial/docs/esocial-tests` dos eventos afetados.

## 9. Mensagens do Sistema (versão nova)
Baixe o PDF numa pasta temporária **vazia** e rode
`python3 .claude/skills/update-esocial/scripts/extract_mensagens.py <pdf> <versao> <url-do-pdf>`.
Confira o diff de `data/mensagens-sistema.json` (mensagens novas, removidas, ações alteradas),
rode `python3 -m unittest discover -s .claude/skills/update-esocial/scripts -p 'test_*.py'` e o
teste `tests/Unit/SystemMessagesTest.php` (troque nele a versão esperada em "informa a versão do
catálogo" e confira os textos fixos do MS0191/MS0266), e atualize `system_messages` no
`ESOCIAL-VERSION.json`.

## 10. Pacotes prontos em branch
Pacote preparado antes da data fica num branch `xsd/<data>` e em `pending_packages` do
`ESOCIAL-VERSION.json`. Na data de `merge_on`: `check.py` e `compare_xsd.py` contra o zip do dia
(o gov.br pode republicar), merge do branch no `master`, suíte e `validate.php`, push com ok, e
aviso ao enviaesocial.

O `compare_xsd.py` nomeia o caminho pelo tipo (`#T_infoCadastro/indOptRegEletron`). Tipo
renomeado aparece como todos os filhos removidos e novos (`/campo: tipo T_a -> T_b` denuncia):
nesse caso compare os filhos à mão.

Versões (semver): no merge de um pacote, `## Unreleased` do `CHANGELOG.md` vira `## 2.N.0 - <data>`
e o commit recebe a tag anotada `v2.N.0` (push da tag com ok). Correção = patch; remoção ou
mudança incompatível = major. Antes do merge, `gen_docs.py --check` no branch.
