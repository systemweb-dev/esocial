# Plano de implementação — SP4 documentação e versões

[spec.md](spec.md)

> **Execução:** implementar task por task. Os steps usam checkbox (`- [ ]`) para acompanhar.
> Os dois modos de execução estão na seção "Execution Handoff" da skill `sw-plan`.

**Objetivo:** docs por evento geradas dos schemas, README/Tools/Config atuais e conferidos por
teste, e a tag `v2.0.0` com semver.

**Arquitetura:** dois scripts Python stdlib na skill `update-esocial` (`gen_docs.py` gera
`docs/eventos/`; `check_docs.py` confere a API citada nas docs), cada um com `unittest` em
repositório mínimo temporário. Textos novos em `README.md`, `docs/Tools.md`, `docs/Config.md`.
Fecha com CHANGELOG 2.0.0, tag e rebase dos branches `xsd/*`.

**Stack:** Python 3 stdlib (`unittest`, `xml.etree`), PHP 8.4 só para a verificação.

**Restrições verificáveis (do spec):**
| Restrição | Como o plano checa |
|---|---|
| 40 páginas + índice em `docs/eventos/` | Task 1, step 5 |
| `gen_docs.py --check` sai 0 | Task 1, step 6; Task 5, step 4 |
| Nenhuma doc antiga | Task 1, step 6 |
| README/Tools/Config só citam API existente | Task 4, step 4 (`check_docs.py` sai 0) |
| Suíte verde, `validate.php` 40/40 | Task 5, step 4 |
| `git describe --tags` = `v2.0.0`; `## 2.0.0` no CHANGELOG | Task 6, step 2 |

**Convenções:** raiz `/var/www/esocial`. `SC=.claude/skills/update-esocial/scripts`.
Python: `python3 -m unittest discover -s $SC -p 'test_*.py'` e `rm -rf $SC/__pycache__` depois.
Antes de cada commit: área de staging vazia, `git add` checado antes do `git commit`. Commit só no
checkpoint com o "sim" do usuário; tag e push só com o ok. Texto com crases ou `$` nunca vai num
heredoc sem aspas (`<<'EOF'`): o shell executa o que está entre crases.

**Ordem pelo risco:** o casamento campo → descrição do XSD (Task 1) foi prototipado no
planejamento contra os 40 eventos: 1722 campos, 43 sem descrição (os sem par no XSD e grupos que o
XSD não documenta); desempate pelo grupo conferido em S-1200, S-1210 e S-2200.

---

### Task 1: Gerador `gen_docs.py` e `docs/eventos/`

**Arquivos:**
- Criar: `.claude/skills/update-esocial/scripts/test_gen_docs.py`
- Criar: `.claude/skills/update-esocial/scripts/gen_docs.py`
- Criar: `docs/eventos/` (gerado: `README.md` + 40 `S-NNNN.md`)
- Remover: `docs/Evt*.md`, `docs/EvtFGTS`, `docs/EventosvS_1.0.md`

**Depende de:** nada

**Contrato que esta task publica:** `gen_docs.generate(root) -> dict` (arquivo → conteúdo);
`gen_docs.outdated(root, files) -> list`; CLI `gen_docs.py [--check]`.

- [x] **Step 1: escrever os testes que falham**

Criar `.claude/skills/update-esocial/scripts/test_gen_docs.py`:

```python
"""Testes do gen_docs.py com um repositório mínimo gravado numa pasta temporária."""
import json
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import gen_docs  # noqa: E402

EVENT_PHP = '''<?php
    public static $available = [
        'evtteste' => Factories\\EvtTeste::class,
    ];
    public static $aliases = [
        's1999' => 'evtteste',
    ];
'''
TOOLS_PHP = '''<?php
    protected $grupos = [
        1 => [
            'S-1999',
        ],
        2 => [
            'S-2999',
        ],
    ];
'''
XSD = '''<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
  <xs:element name="eSocial"><xs:complexType><xs:sequence>
    <xs:element name="evtTeste">
      <xs:annotation>
        <xs:documentation>Evento Teste</xs:documentation>
        <xs:documentation>DESCRICAO_COMPLETA:Evento Teste da Lib.</xs:documentation>
        <xs:documentation>CHAVE_GRUPO: {Id}</xs:documentation>
      </xs:annotation>
      <xs:complexType><xs:sequence>
        <xs:element name="ideEvento" type="T_ideEvento_mensal"/>
        <xs:element name="ideEmpregador" type="T_ideEmpregador"/>
        <xs:element name="infoValor">
          <xs:complexType><xs:sequence>
            <xs:element name="tpInsc">
              <xs:annotation>
                <xs:documentation>Tipo de inscrição do estabelecimento.</xs:documentation>
                <xs:documentation>CONDICAO_GRUPO: O</xs:documentation>
                <xs:documentation>Validação: Deve ser 1 ou 4.</xs:documentation>
              </xs:annotation>
            </xs:element>
            <xs:element name="dtIni" type="TS_dtIni"/>
            <xs:element name="detItem" type="T_detItem"/>
            <xs:element name="infoOutro"><xs:complexType><xs:sequence>
              <xs:element name="tpReg"><xs:annotation><xs:documentation>Regime do outro.</xs:documentation></xs:annotation></xs:element>
            </xs:sequence></xs:complexType></xs:element>
            <xs:element name="infoCedido"><xs:complexType><xs:sequence>
              <xs:element name="tpReg"><xs:annotation><xs:documentation>Regime do cedido.</xs:documentation></xs:annotation></xs:element>
            </xs:sequence></xs:complexType></xs:element>
            <xs:element name="vlr"><xs:simpleType>
              <xs:annotation><xs:documentation>Valor do item.</xs:documentation></xs:annotation>
              <xs:restriction base="xs:decimal"/>
            </xs:simpleType></xs:element>
          </xs:sequence>
          <xs:attribute name="perRef"><xs:annotation><xs:documentation>Período de referência.</xs:documentation></xs:annotation></xs:attribute>
          </xs:complexType>
        </xs:element>
      </xs:sequence></xs:complexType>
    </xs:element>
  </xs:sequence></xs:complexType></xs:element>
</xs:schema>
'''
TIPOS = '''<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
  <xs:complexType name="T_ideEmpregador"><xs:sequence>
    <xs:element name="tpInsc"><xs:annotation><xs:documentation>Tipo de inscrição do empregador.</xs:documentation></xs:annotation></xs:element>
  </xs:sequence></xs:complexType>
  <xs:complexType name="T_ideEvento_folha"><xs:sequence>
    <xs:element name="perApur"><xs:annotation><xs:documentation>Período da folha.</xs:documentation></xs:annotation></xs:element>
  </xs:sequence></xs:complexType>
  <xs:complexType name="T_ideEvento_mensal"><xs:sequence>
    <xs:element name="perApur"><xs:annotation>
      <xs:documentation>Período mensal.</xs:documentation>
      <xs:documentation>Validação: Devem ser observadas as regras:</xs:documentation>
      <xs:documentation>a) ser um mês válido;</xs:documentation>
      <xs:documentation>REGRA:REGRA_X</xs:documentation>
    </xs:annotation></xs:element>
  </xs:sequence></xs:complexType>
  <xs:complexType name="T_detItem"><xs:sequence>
    <xs:element name="tpInsc"><xs:annotation><xs:documentation>Tipo de inscrição do prestador.</xs:documentation></xs:annotation></xs:element>
  </xs:sequence></xs:complexType>
  <xs:simpleType name="TS_dtIni">
    <xs:annotation><xs:documentation>Data de início.</xs:documentation></xs:annotation>
    <xs:restriction base="xs:date"/>
  </xs:simpleType>
</xs:schema>
'''
SCHEMA = {
    'title': 'evtTeste',
    'type': 'object',
    'properties': {
        'sequencial': {'required': False, 'type': ['integer', 'null'], 'minimum': 1, 'maximum': 99999},
        'perapur': {'required': True, 'type': 'string'},
        'infovalor': {
            'required': True,
            'type': 'array',
            'minItems': 1,
            'maxItems': 99,
            'items': {'type': 'object', 'properties': {
                'tpinsc': {'required': True, 'type': 'integer', 'minimum': 1, 'maximum': 4},
                'dtini': {'required': False, 'type': ['string', 'null'], '$ref': '#/definitions/data'},
                'vlr': {'required': True, 'type': 'number'},
                'cedido': {'required': False, 'type': ['object', 'null'], 'properties': {
                    'tpreg': {'required': True, 'type': 'string'},
                }},
                'grupoxyz': {'required': False, 'type': ['object', 'null'], 'properties': {
                    'tpreg': {'required': True, 'type': 'string'},
                }},
                'detitem': {'required': False, 'type': ['object', 'null'], 'properties': {
                    'tpinsc': {'required': True, 'type': 'string'},
                }},
                'perref': {'required': True, 'type': 'string'},
                'campoantigo': {'required': False, 'type': ['string', 'null']},
            }},
        },
    },
}


def make_repo(root: Path) -> None:
    files = {
        'src/Event.php': EVENT_PHP,
        'src/Common/Tools.php': TOOLS_PHP,
        'schemes/v_S_01_03_00/evtTeste.xsd': XSD,
        'schemes/v_S_01_03_00/tipos.xsd': TIPOS,
        'jsonSchemes/v_S_01_03_00/evtTeste.schema': json.dumps(SCHEMA),
        'jsonSchemes/definitions.schema': json.dumps({'definitions': {'data': {'pattern': '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'}}}),
        'ESOCIAL-VERSION.json': json.dumps({'xsd_package': '2026-10-26'}),
        'examples/Fake/v_S_01_03_00/Fake_s1999_EvtTeste.php': '<?php\n',
    }
    for name, text in files.items():
        (root / name).parent.mkdir(parents=True, exist_ok=True)
        (root / name).write_text(text, encoding='utf-8')


class GenerateTest(unittest.TestCase):
    def setUp(self):
        tmp = tempfile.TemporaryDirectory()
        self.addCleanup(tmp.cleanup)
        self.root = Path(tmp.name)
        make_repo(self.root)

    def row(self, files: dict, field: str) -> str:
        return next(line for line in files['docs/eventos/S-1999.md'].splitlines() if line.startswith(f'| `{field}` |'))

    def test_gera_o_indice_e_uma_pagina_por_evento(self):
        files = gen_docs.generate(self.root)

        self.assertEqual(sorted(files), ['docs/eventos/README.md', 'docs/eventos/S-1999.md'])

    def test_cabecalho_com_nome_classe_grupo_e_exemplo(self):
        files = gen_docs.generate(self.root)

        text = files['docs/eventos/S-1999.md']
        self.assertTrue(text.startswith('# S-1999 — Teste da Lib\n'))
        self.assertIn('| Classe | `NFePHP\\eSocial\\Factories\\EvtTeste` |', text)
        self.assertIn('| Montar | `Event::s1999($configJson, $std, $certificate)` |', text)
        self.assertIn('| Grupo do lote | 1 (`Tools::EVT_TABELAS`) |', text)
        self.assertIn('| Exemplo | [Fake_s1999_EvtTeste.php](../../examples/Fake/v_S_01_03_00/Fake_s1999_EvtTeste.php) |', text)
        self.assertIn('| Pacote XSD | 2026-10-26 |', text)

    def test_campo_em_array_com_desempate_pelo_grupo_e_linha_de_validacao(self):
        files = gen_docs.generate(self.root)

        self.assertEqual(self.row(files, 'infovalor/tpinsc'),
                         '| `infovalor/tpinsc` | integer | sim | 1 a 4 | '
                         'Tipo de inscrição do estabelecimento. Validação: Deve ser 1 ou 4. |')

    def test_ref_resolvido_e_descricao_do_tipo_nomeado(self):
        files = gen_docs.generate(self.root)

        self.assertEqual(self.row(files, 'infovalor/dtini'),
                         '| `infovalor/dtini` | string | não | `^[0-9]{4}-[0-9]{2}-[0-9]{2}$` | Data de início. |')

    def test_descricao_do_tipo_anonimo_e_do_atributo(self):
        files = gen_docs.generate(self.root)

        self.assertTrue(self.row(files, 'infovalor/vlr').endswith('| Valor do item. |'))
        self.assertTrue(self.row(files, 'infovalor/perref').endswith('| Período de referência. |'))

    def test_grupo_achatado_no_json_usa_o_tipo_que_o_evento_referencia(self):
        files = gen_docs.generate(self.root)

        self.assertEqual(self.row(files, 'perapur'),
                         '| `perapur` | string | sim |  | '
                         'Período mensal. Validação: Devem ser observadas as regras: a) ser um mês válido; |')

    def test_elemento_de_tipo_nomeado_usa_o_filho_do_tipo(self):
        files = gen_docs.generate(self.root)

        self.assertTrue(self.row(files, 'infovalor/detitem/tpinsc').endswith('| Tipo de inscrição do prestador. |'))

    def test_grupo_com_prefixo_info_no_xsd(self):
        files = gen_docs.generate(self.root)

        self.assertTrue(self.row(files, 'infovalor/cedido/tpreg').endswith('| Regime do cedido. |'))

    def test_campo_da_lib_tem_descricao_propria(self):
        files = gen_docs.generate(self.root)

        self.assertIn('Sequencial do Id do evento', self.row(files, 'sequencial'))

    def test_campo_sem_par_no_xsd_vai_para_a_secao_propria(self):
        files = gen_docs.generate(self.root)

        text = files['docs/eventos/S-1999.md']
        self.assertTrue(text.endswith('## Campos sem correspondência no XSD\n\n'
                                      'Aceitos pelo JSON schema da lib, sem elemento de mesmo nome no XSD ou com grupo de nome '
                                      'diferente e mais de um elemento possível:\n\n'
                                      '- `infovalor/cedido`\n'
                                      '- `infovalor/grupoxyz`\n'
                                      '- `infovalor/grupoxyz/tpreg`\n'
                                      '- `infovalor/campoantigo`\n'))

    def test_indice_lista_o_evento(self):
        files = gen_docs.generate(self.root)

        self.assertIn('| [S-1999](S-1999.md) | `evtTeste` | `EvtTeste` | 1 |', files['docs/eventos/README.md'])


class OutdatedTest(unittest.TestCase):
    def setUp(self):
        tmp = tempfile.TemporaryDirectory()
        self.addCleanup(tmp.cleanup)
        self.root = Path(tmp.name)
        make_repo(self.root)
        self.files = gen_docs.generate(self.root)
        for name, text in self.files.items():
            (self.root / name).parent.mkdir(parents=True, exist_ok=True)
            (self.root / name).write_text(text, encoding='utf-8')

    def test_em_dia(self):
        stale = gen_docs.outdated(self.root, self.files)

        self.assertEqual(stale, [])

    def test_pagina_alterada_e_pagina_que_sobrou(self):
        (self.root / 'docs/eventos/S-1999.md').write_text('editado à mão\n', encoding='utf-8')
        (self.root / 'docs/eventos/S-1030.md').write_text('evento extinto\n', encoding='utf-8')

        stale = gen_docs.outdated(self.root, self.files)

        self.assertEqual(stale, ['docs/eventos/S-1030.md', 'docs/eventos/S-1999.md'])


if __name__ == '__main__':
    unittest.main()
```

- [x] **Step 2: rodar e confirmar que falham**

Rode: `python3 -m unittest discover -s $SC -p 'test_gen*.py'`
Esperado: ERRO `ModuleNotFoundError: No module named 'gen_docs'`.

- [x] **Step 3: implementar**

Criar `.claude/skills/update-esocial/scripts/gen_docs.py` (e `chmod +x`):

```python
#!/usr/bin/env python3
"""Gera docs/eventos/ (uma página por evento que a lib monta) dos JSON schemas e do XSD oficial.

Uso: python3 gen_docs.py [--check]
Sem argumento grava docs/eventos/. Com --check não grava e sai com 1 se alguma página está
desatualizada (rode depois de aplicar pacote de XSD ou mexer em JSON schema).

Descrição de cada campo: a do elemento (ou atributo) de mesmo nome alcançável a partir do evento
no XSD (tipos nomeados expandidos), escolhido pelos grupos do caminho do JSON. Campo sem elemento de
mesmo nome vai para uma seção própria.
"""
import json
import re
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[4]
LAYOUT = 'v_S_01_03_00'
XS = '{http://www.w3.org/2001/XMLSchema}'
GROUPS = {1: 'EVT_TABELAS', 2: 'EVT_NAO_PERIODICOS', 3: 'EVT_PERIODICOS'}
LIB_FIELDS = {
    'sequencial': 'Sequencial do Id do evento (1 a 99999). Opcional: sem ele a lib sorteia um; '
                  'entre processos, quem chama deve alocar (ver docs/Config.md).',
    'modo': 'Operação na tabela: INC (inclusão), ALT (alteração) ou EXC (exclusão).',
}
TAG = re.compile(r'^[A-Z_]+:')
HEADER = '<!-- Gerado por .claude/skills/update-esocial/scripts/gen_docs.py. Não edite à mão. -->'


def events(root: Path) -> list:
    """Eventos montados pela lib: código, nome do XSD, classe, grupo e exemplo."""
    event_php = (root / 'src/Event.php').read_text(encoding='utf-8')
    tools_php = (root / 'src/Common/Tools.php').read_text(encoding='utf-8')
    classes = dict(re.findall(r"'(evt\w+)' => Factories\\(\w+)::class", event_php))
    aliases = re.findall(r"'s(\d{4})' => '(evt\w+)'", event_php)
    block = tools_php[tools_php.index('$grupos = ['):]
    block = block[:block.index('];')]
    groups = {code: int(g) for g, body in re.findall(r'(\d) => \[(.*?)\]', block, re.S)
              for code in re.findall(r"'(S-\d{4})'", body)}
    xsds = {p.stem.lower(): p.stem for p in (root / 'schemes' / LAYOUT).glob('evt*.xsd')}
    out = []
    for num, alias in sorted(aliases):
        code = f'S-{num}'
        examples = sorted((root / 'examples/Fake' / LAYOUT).glob(f'Fake_s{num}_*.php'))
        out.append({'codigo': code, 'evt': xsds[alias], 'classe': classes[alias],
                    'metodo': f's{num}', 'grupo': groups.get(code),
                    'exemplo': examples[0].name if examples else None})
    return out


def xsd_paths(paths: list, evt: str) -> list:
    """(caminho em minúsculo a partir do evento, documentação) de cada xs:element e xs:attribute
    alcançável a partir do elemento do evento, expandindo os tipos nomeados que ele usa."""
    trees = [ET.parse(path).getroot() for path in paths]
    named = {t.get('name'): t for root in trees for t in root
             if t.tag in (f'{XS}simpleType', f'{XS}complexType') and t.get('name')}
    start = next(e for root in trees for e in root.iter(f'{XS}element') if e.get('name') == evt)
    out = []

    def walk(node, path, seen):
        for child in node:
            if child.tag in (f'{XS}element', f'{XS}attribute') and child.get('name'):
                sub = path + [child.get('name').lower()]
                out.append((sub, documentation(child, named)))
                expand(child, sub, seen)
            elif child.tag != f'{XS}annotation':
                expand(child, path, seen)

    def expand(node, path, seen):
        walk(node, path, seen)
        for attr in ('type', 'base'):
            type_name = (node.get(attr) or '').split(':')[-1]
            if type_name in named and type_name not in seen:
                walk(named[type_name], path, seen | {type_name})

    out.append(([evt.lower()], documentation(start, named)))
    expand(start, [evt.lower()], frozenset())
    return out


def documentation(node, named: dict) -> list:
    """Documentação do elemento; senão a do tipo anônimo dentro dele; senão a do tipo nomeado."""
    def docs(n):
        return [d.text.strip() for d in n.findall(f'{XS}annotation/{XS}documentation') if d.text]

    found = docs(node)
    for inner in (node.find(f'{XS}simpleType'), node.find(f'{XS}complexType')):
        if not found and inner is not None:
            found = docs(inner)
    type_name = (node.get('type') or '').split(':')[-1]
    if not found and type_name in named:
        found = docs(named[type_name])
    return found


def text_of(docs: list) -> str:
    """DESCRICAO_COMPLETA (ou a primeira linha) e os parágrafos seguintes, sem as marcações
    CHAVE_GRUPO, CONDICAO_GRUPO, REGRA etc."""
    full = [d.split(':', 1)[1].strip() for d in docs if d.startswith('DESCRICAO_COMPLETA:')]
    plain = [d for d in docs if not TAG.match(d)]
    if full:
        return ' '.join(full[:1] + plain[1:])
    return ' '.join(plain)


def describe(index: list, path: list):
    """Descrição do campo ou None sem par no XSD. Entre os elementos de mesmo nome, vence o que
    contém mais grupos do caminho do JSON, na mesma ordem (o JSON achata alguns grupos e encurta
    nomes: `trabcedido` casa com `infoTrabCedido`); no empate, o de caminho mais curto. Se nenhum
    casa todos os grupos e os textos diferem, não chuta: devolve None."""
    candidates = [(p, docs) for p, docs in index if p[-1] == path[-1]]
    if not candidates:
        return None

    def score(xsd_path):
        matched, i = 0, len(xsd_path) - 2
        for group in reversed(path[:-1]):
            while i >= 0 and not xsd_path[i].endswith(group):
                i -= 1
            if i < 0:
                break
            matched, i = matched + 1, i - 1
        return (matched, -len(xsd_path))

    best = max(candidates, key=lambda c: score(c[0]))
    texts = {text_of(docs) for _, docs in candidates}
    if score(best[0])[0] < len(path) - 1 and len(texts) > 1:
        return None
    return text_of(best[1])


def fields(schema: dict, definitions: dict) -> list:
    """(caminho, tipos, obrigatório, regra) de cada propriedade, em profundidade."""
    rows = []

    def walk(props, path):
        for key, node in props.items():
            if '$ref' in node:
                ref = definitions[node['$ref'].split('/')[-1]]
                node = {**ref, **{k: v for k, v in node.items() if k != '$ref'}}
            types = node.get('type', [])
            types = [t for t in (types if isinstance(types, list) else [types]) if t != 'null']
            rows.append((path + [key], types, bool(node.get('required')), rule(node)))
            if 'properties' in node:
                walk(node['properties'], path + [key])
            items = node.get('items')
            if isinstance(items, dict) and 'properties' in items:
                walk(items['properties'], path + [key])

    walk(schema['properties'], [])
    return rows


def rule(node: dict) -> str:
    parts = []
    if 'pattern' in node:
        parts.append(f"`{node['pattern']}`")
    if 'minimum' in node or 'maximum' in node:
        parts.append(f"{node.get('minimum', '…')} a {node.get('maximum', '…')}")
    if 'minLength' in node or 'maxLength' in node:
        parts.append(f"tamanho {node.get('minLength', 0)}-{node.get('maxLength', '…')}")
    if 'minItems' in node or 'maxItems' in node:
        parts.append(f"{node.get('minItems', 0)}-{node.get('maxItems', '…')} itens")
    return '; '.join(parts)


def cell(text: str) -> str:
    return ' '.join(text.split()).replace('|', '\\|')


def page(event: dict, rows: list, index: dict, package: str) -> str:
    title = text_of(index[0][1]).split('. ')[0].removeprefix('Evento ').rstrip('.') or event['evt']
    group = event['grupo']
    lines = [
        f"# {event['codigo']} — {title}", '', HEADER, '',
        '| | |', '|---|---|',
        f"| Evento | `{event['evt']}` |",
        f"| Classe | `NFePHP\\eSocial\\Factories\\{event['classe']}` |",
        f"| Montar | `Event::{event['metodo']}($configJson, $std, $certificate)` |",
        f"| Grupo do lote | {group} (`Tools::{GROUPS[group]}`) |" if group else '| Grupo do lote | não vai em lote |',
    ]
    if event['exemplo']:
        lines.append(f"| Exemplo | [{event['exemplo']}](../../examples/Fake/{LAYOUT}/{event['exemplo']}) |")
    lines += [f'| Pacote XSD | {package} |', '',
              '## Campos do `stdClass`', '',
              'As chaves podem vir em qualquer caixa (a lib converte para minúsculo). Grupos com '
              'itens são arrays de objetos.', '',
              '| Campo | Tipo | Obrig. | Regra | Descrição |', '|---|---|---|---|---|']
    orphans = []
    for path, types, required, rule_text in rows:
        desc = LIB_FIELDS.get(path[-1]) if len(path) == 1 and path[-1] in LIB_FIELDS else describe(index, path)
        if desc is None:
            orphans.append(path)
            desc = ''
        lines.append(f"| `{'/'.join(path)}` | {', '.join(types)} | {'sim' if required else 'não'} | "
                     f"{cell(rule_text)} | {cell(desc)} |")
    if orphans:
        lines += ['', '## Campos sem correspondência no XSD', '',
                  'Aceitos pelo JSON schema da lib, sem elemento de mesmo nome no XSD ou com grupo de nome '
                  'diferente e mais de um elemento possível:', '']
        lines += [f"- `{'/'.join(p)}`" for p in orphans]
    return '\n'.join(lines) + '\n'


def index_page(evts: list, package: str) -> str:
    lines = ['# Eventos que a lib monta', '', HEADER, '',
             f'Leiaute S-1.3, pacote XSD {package}. Cada página lista os campos do `stdClass`.', '',
             '| Código | Evento | Classe | Grupo |', '|---|---|---|---|']
    for e in evts:
        lines.append(f"| [{e['codigo']}]({e['codigo']}.md) | `{e['evt']}` | `{e['classe']}` | {e['grupo'] or '—'} |")
    return '\n'.join(lines) + '\n'


def generate(root: Path) -> dict:
    """Arquivo (relativo à raiz) -> conteúdo."""
    package = json.loads((root / 'ESOCIAL-VERSION.json').read_text(encoding='utf-8'))['xsd_package']
    definitions = json.loads((root / 'jsonSchemes/definitions.schema').read_text(encoding='utf-8'))['definitions']
    evts = events(root)
    out = {'docs/eventos/README.md': index_page(evts, package)}
    for e in evts:
        schema = json.loads((root / 'jsonSchemes' / LAYOUT / f"{e['evt']}.schema").read_text(encoding='utf-8'))
        index = xsd_paths([root / 'schemes' / LAYOUT / f"{e['evt']}.xsd", root / 'schemes' / LAYOUT / 'tipos.xsd'], e['evt'])
        out[f"docs/eventos/{e['codigo']}.md"] = page(e, fields(schema, definitions), index, package)
    return out


def outdated(root: Path, files: dict) -> list:
    """Arquivos que diferem do gerado, faltam ou sobram em docs/eventos/."""
    current = {str(p.relative_to(root)) for p in (root / 'docs/eventos').glob('*.md')}
    diff = [f for f, text in files.items()
            if not (root / f).is_file() or (root / f).read_text(encoding='utf-8') != text]
    return sorted(diff + sorted(current - set(files)))


def main() -> int:
    files = generate(ROOT)
    if sys.argv[1:] == ['--check']:
        stale = outdated(ROOT, files)
        for f in stale:
            print(f'desatualizado: {f}')
        return 1 if stale else 0
    target = ROOT / 'docs/eventos'
    target.mkdir(parents=True, exist_ok=True)
    for old in target.glob('*.md'):
        if str(old.relative_to(ROOT)) not in files:
            old.unlink()
    for f, text in files.items():
        (ROOT / f).write_text(text, encoding='utf-8')
    print(f'{len(files)} arquivos em docs/eventos/')
    return 0


if __name__ == '__main__':
    sys.exit(main())
```

- [x] **Step 4: rodar e confirmar que passam**

Rode: `python3 -m unittest discover -s $SC -p 'test_gen*.py' -v`
Esperado: 13 testes `ok`.

- [x] **Step 5: gerar as páginas**

Rode: `python3 $SC/gen_docs.py && ls docs/eventos/S-*.md | wc -l`
Esperado: `41 arquivos em docs/eventos/` e `40`. Abra `docs/eventos/S-1210.md` e confira que
`infoircomplem/plansaude/cnpjoper` e `infoircomplem/inforeembmed/cnpjoper` têm descrições
diferentes (desempate pelo grupo) e que o S-1200 tem a seção "Campos sem correspondência no XSD".

- [x] **Step 6: remover as docs antigas e conferir**

```bash
git rm -q docs/Evt*.md docs/EvtFGTS docs/EventosvS_1.0.md
ls docs/Evt* docs/EventosvS_1.0.md 2>&1 | grep -c 'No such\|inexistente'    # 2
python3 $SC/gen_docs.py --check; echo $?                                     # 0
```

---

### Task 2: Verificador `check_docs.py`

**Arquivos:**
- Criar: `.claude/skills/update-esocial/scripts/test_check_docs.py`
- Criar: `.claude/skills/update-esocial/scripts/check_docs.py`

**Depende de:** nada

**Contrato que esta task publica:** `check_docs.problems(root, docs) -> list`; CLI
`check_docs.py` (sai 1 com a lista). Variáveis reconhecidas nas docs: `$tools`, `$soap`,
`$standardize`, `Event::`, `Tools::`, `SystemMessages::`.

- [x] **Step 1: escrever os testes que falham**

Criar `.claude/skills/update-esocial/scripts/test_check_docs.py`:

```python
"""Testes do check_docs.py com um repositório mínimo gravado numa pasta temporária."""
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import check_docs  # noqa: E402

SOURCES = {
    'src/Tools.php': '<?php class Tools { public function enviarLoteEventos($g, $e) {} }',
    'src/Common/Tools.php': '<?php class Tools { const EVT_TABELAS = 1; public function __construct() {} }',
    'src/Event.php': "<?php 'evtinfoempregador' => Factories\\EvtInfoEmpregador::class, 's1000' => 'evtinfoempregador',",
    'src/Common/SystemMessages.php': '<?php class SystemMessages { public static function get($c) {} }',
    'src/Common/Soap/SoapCurl.php': '<?php class SoapCurl { public function send() {} }',
    'src/Common/Soap/SoapBase.php': '<?php class SoapBase { public function loadCA($p) {} }',
    'src/Common/Standardize.php': '<?php class Standardize { public function toStd($x = null) {} }',
    'docs/eventos/README.md': '# Eventos\n',
}


class ProblemsTest(unittest.TestCase):
    def setUp(self):
        tmp = tempfile.TemporaryDirectory()
        self.addCleanup(tmp.cleanup)
        self.root = Path(tmp.name)
        for name, text in SOURCES.items():
            (self.root / name).parent.mkdir(parents=True, exist_ok=True)
            (self.root / name).write_text(text, encoding='utf-8')

    def write_readme(self, text: str) -> None:
        (self.root / 'README.md').write_text(text, encoding='utf-8')

    def test_doc_que_so_cita_api_existente(self):
        self.write_readme(
            "$tools->enviarLoteEventos(Tools::EVT_TABELAS, [$evento]);\n"
            "Event::evtInfoEmpregador($c, $std); Event::s1000($c, $std);\n"
            "SystemMessages::get(191); $soap->loadCA('/ca.pem'); $standardize->toStd($xml);\n"
            "[eventos](docs/eventos/README.md) [gov.br](https://www.gov.br/esocial) [topo](#uso)\n")

        found = check_docs.problems(self.root, ['README.md'])

        self.assertEqual(found, [])

    def test_acusa_metodo_constante_evento_e_link_inexistentes(self):
        self.write_readme(
            "$tools->enviarLote(1, []); Tools::EVT_INICIO; Event::s9999($c, $std);\n"
            "SystemMessages::all(); $soap->naoExiste(); [x](docs/Nada.md#a)\n")

        found = check_docs.problems(self.root, ['README.md'])

        self.assertEqual(found, [
            'README.md: Tools enviarLote não existe',
            'README.md: SoapCurl naoExiste não existe',
            'README.md: Tools:: EVT_INICIO não existe',
            'README.md: Event s9999 não existe',
            'README.md: SystemMessages all não existe',
            'README.md: link quebrado docs/Nada.md#a',
        ])

    def test_link_com_titulo_ou_entre_sinais_e_constante_com_digito_sao_conferidos(self):
        self.write_readme('[a](docs/Falta.md "titulo") [b](<docs/Outro.md>) Tools::EVT_V2\n')

        found = check_docs.problems(self.root, ['README.md'])

        self.assertEqual(found, [
            'README.md: Tools:: EVT_V2 não existe',
            'README.md: link quebrado docs/Falta.md',
            'README.md: link quebrado docs/Outro.md',
        ])

    def test_construtor_nao_conta_como_metodo_chamavel(self):
        self.write_readme('$tools->__construct();\n')

        found = check_docs.problems(self.root, ['README.md'])

        self.assertEqual(found, ['README.md: Tools __construct não existe'])


if __name__ == '__main__':
    unittest.main()
```

- [x] **Step 2: rodar e confirmar que falham**

Rode: `python3 -m unittest discover -s $SC -p 'test_check_docs.py'`
Esperado: ERRO `ModuleNotFoundError: No module named 'check_docs'`.

- [x] **Step 3: implementar**

Criar `.claude/skills/update-esocial/scripts/check_docs.py` (e `chmod +x`):

```python
#!/usr/bin/env python3
"""Confere se README.md, docs/Tools.md e docs/Config.md só citam API e arquivos que existem.

As chamadas são reconhecidas pelo nome da variável: $tools (Tools), $soap (SoapCurl),
$standardize (Standardize), Event:: e SystemMessages::. Escreva-as assim nas docs: `$tools ->x(`
ou `$this->tools->x(` não são conferidos.

Uso: python3 check_docs.py  (sai com 1 e lista os problemas)
"""
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[4]
DOCS = ('README.md', 'docs/Tools.md', 'docs/Config.md')
CALLS = {
    'Tools': re.compile(r'\$tools->(\w+)\('),
    'SoapCurl': re.compile(r'\$soap->(\w+)\('),
    'Standardize': re.compile(r'\$standardize->(\w+)\('),
    'Tools::': re.compile(r'\bTools::([A-Z][A-Z0-9_]*)\b'),
    'Event': re.compile(r'\bEvent::(\w+)\('),
    'SystemMessages': re.compile(r'\bSystemMessages::(\w+)\('),
}
LINK = re.compile(r'\]\(<?([^)\s>]+)>?(?:\s+"[^"]*")?\)')


def api(root: Path) -> dict:
    """Nomes válidos por tipo de chamada, lidos do código."""
    def methods(*files):
        return {m for f in files for m in re.findall(r'public (?:static )?function (\w+)',
                                                      (root / f).read_text(encoding='utf-8'))}
    tools = ('src/Tools.php', 'src/Common/Tools.php')
    event = (root / 'src/Event.php').read_text(encoding='utf-8')
    events = set(re.findall(r"'(evt\w+)' => Factories", event)) | set(re.findall(r"'(s\d{4})' => '", event))
    return {
        'Tools': methods(*tools) - {'__construct'},
        'Tools::': {c for f in tools for c in re.findall(r'const (\w+)', (root / f).read_text(encoding='utf-8'))},
        'Event': {e.lower() for e in events},
        'SystemMessages': methods('src/Common/SystemMessages.php'),
        'SoapCurl': methods('src/Common/Soap/SoapCurl.php', 'src/Common/Soap/SoapBase.php') - {'__construct'},
        'Standardize': methods('src/Common/Standardize.php') - {'__construct'},
    }


def problems(root: Path, docs=DOCS) -> list:
    valid = api(root)
    out = []
    for doc in docs:
        text = (root / doc).read_text(encoding='utf-8')
        for kind, pattern in CALLS.items():
            for name in pattern.findall(text):
                key = name.lower() if kind == 'Event' else name
                if key not in valid[kind]:
                    out.append(f'{doc}: {kind} {name} não existe')
        for target in LINK.findall(text):
            if re.match(r'^(https?:|mailto:|#)', target):
                continue
            path = (root / doc).parent / target.split('#')[0]
            if not path.exists():
                out.append(f'{doc}: link quebrado {target}')
    return out


def main() -> int:
    found = problems(ROOT)
    for p in found:
        print(p)
    return 1 if found else 0


if __name__ == '__main__':
    sys.exit(main())
```

- [x] **Step 4: rodar e confirmar que passam**

Rode: `python3 -m unittest discover -s $SC -p 'test_check_docs.py' -v`
Esperado: 4 testes `ok`. (`python3 $SC/check_docs.py` no repositório ainda passa: o `docs/Tools.md`
atual usa `$esoc`, que o verificador não reconhece; a Task 4 fecha com ele em 0 com os textos novos.)

---

### Task 3: `docs/Config.md` e `docs/Tools.md`

**Arquivos:**
- Alterar: `docs/Config.md` (reescrito)
- Alterar: `docs/Tools.md` (reescrito)

**Depende de:** Task 1 (`docs/eventos/README.md`, citado no Tools.md)

- [x] **Step 1: `docs/Config.md`** — substituir o conteúdo por:

````markdown
# Config

String JSON passada a todo evento (`Event::...`) e ao `Tools`. Identifica o ambiente, o
leiaute, o pacote de comunicação, o empregador e o transmissor.

```php
$configJson = json_encode([
    'tpAmb' => 2,                     // 1 = Produção; 2 = Produção Restrita
    'verProc' => 'MeuSistema_1.0',    // versão do aplicativo que gera o evento
    'eventoVersion' => 'S.1.3.0',     // leiaute dos eventos (só S-1.3)
    'serviceVersion' => '1.6.0',      // pacote de comunicação (1.6-Alfa: aceita CNPJ alfanumérico)
    'empregador' => [
        'tpInsc' => 1,                // 1 = CNPJ; 2 = CPF
        'nrInsc' => '12345678',       // CNPJ: raiz de 8 posições (14 para órgão público federal)
        'nmRazao' => 'Empresa Exemplo Ltda',
    ],
    'transmissor' => [
        'tpInsc' => 1,                // 1 = CNPJ; 2 = CPF
        'nrInsc' => '12345678000195', // número completo de quem assina e envia
    ],
]);
```

| Chave | Obrigatória | Uso |
|---|---|---|
| `tpAmb` | sim | Ambiente: `1` Produção, `2` Produção Restrita. Define o `tpAmb` do evento e o endereço do webservice. |
| `verProc` | sim | Versão do processo de emissão (`verProc` do evento), até 20 caracteres. |
| `eventoVersion` | sim | Leiaute dos eventos. Só `S.1.3.0`: abaixo de 1.3 o construtor do evento lança `InvalidArgumentException` (o S-1.2 foi encerrado em 02/02/2025). |
| `serviceVersion` | sim no `Tools` | Pacote de comunicação usado para envio e consulta de lotes (pasta `schemes/comunicacao/v1_6_0`). Com `1.5.0` o lote de CNPJ alfanumérico é recusado localmente. |
| `empregador.tpInsc` | sim | Tipo de inscrição do empregador (`ideEmpregador`). |
| `empregador.nrInsc` | sim | Inscrição do empregador. Entra também no Id do evento. |
| `empregador.nmRazao` | sim no `Tools` | Razão social. O `Tools` lê no construtor; não vai no XML do evento. |
| `transmissor.tpInsc` | sim no `Tools` | Tipo de inscrição do transmissor (`ideTransmissor` do lote). |
| `transmissor.nrInsc` | sim no `Tools` | Inscrição completa do transmissor. |

## Id do evento e `sequencial`

O Id (`$evento->evtid`) é `ID` + `tpInsc` + `nrInsc` (completado com zeros à direita até 14
posições) + data e hora (`AAAAMMDDhhmmss`) + sequencial de 5 dígitos. Sem `$std->sequencial` a lib
sorteia um de 1 a 99999; com ele (inteiro de 1 a 99999) usa o informado. Num mesmo processo a lib
troca o sequencial se o Id já foi usado. Entre processos (vários workers do mesmo empregador) quem
chama deve alocar o `sequencial` para não repetir Id no mesmo segundo.

## Certificado

O certificado A1 é lido em memória com `NFePHP\Common\Certificate::readPfx($conteudoPfx, $senha)`.
A lib não grava a chave em disco: a conexão usa `CURLOPT_SSLCERT_BLOB`/`CURLOPT_SSLKEY_BLOB`.
````

- [x] **Step 2: `docs/Tools.md`** — substituir o conteúdo por:

````markdown
# Tools

`NFePHP\eSocial\Tools` assina e envia lotes de eventos e faz as consultas e downloads nos
webservices do eSocial. Antes de enviar, valida a requisição no XSD do pacote de comunicação do
`serviceVersion` (ver [Config.md](Config.md)).

```php
use NFePHP\Common\Certificate;
use NFePHP\eSocial\Tools;

$certificate = Certificate::readPfx($conteudoPfx, $senha);
$tools = new Tools($configJson, $certificate);
```

Todos os métodos devolvem a resposta SOAP do governo como string (também em
`$tools->lastResponse`; o corpo enviado fica em `$tools->lastRequest`). Para ler a resposta, use
`NFePHP\eSocial\Common\Standardize` (exemplo no [README](../README.md#forma-de-uso)).

## Envio

| Método | O que faz |
|---|---|
| `enviarLoteEventos($grupo, array $eventos)` | Envia até 50 eventos montados pela lib (`Event::...`). Assina com o certificado do `Tools` os eventos criados sem certificado. Lista vazia não envia nada e devolve `''`. |
| `enviarLoteXmlEventos($grupo, array $xmls)` | Envia até 50 XML de eventos **já assinados** (strings). Lê o Id e o grupo de cada XML. |

`$grupo` é um dos três grupos do lote (Manual de Orientação S-1.3, seção 10):

| Constante | Valor | Eventos |
|---|---|---|
| `Tools::EVT_TABELAS` | 1 | S-1000, S-1005, S-1010, S-1020, S-1070 |
| `Tools::EVT_NAO_PERIODICOS` | 2 | S-2190 a S-2555, S-3000, S-3500 |
| `Tools::EVT_PERIODICOS` | 3 | S-1200 a S-1299 |

`EVT_INICIAIS` é alias obsoleto de `EVT_TABELAS`. A lista exata está em
[docs/eventos](eventos/README.md). Erros:

- `InvalidArgumentException`: grupo diferente de 1, 2 e 3; mais de 50 eventos.
- `RuntimeException`: evento de outro grupo; XML sem evento do eSocial identificável; eventos de
  grupos diferentes no mesmo lote; nenhum certificado no `Tools` nem no evento.

## Consultas

| Método | Serviço |
|---|---|
| `consultarLoteEventos($protocolo)` | Resultado do processamento de um lote, pelo `protocoloEnvio` do envio. |
| `consultarEventosEmpregador($tpEvt, $perApur)` | Ids dos eventos do empregador (`S-1200`, `'2026-09'`). |
| `consultarEventosTabela($tpEvt, $chEvt = null, $dtIni = null, $dtFim = null)` | Ids dos eventos de tabela (`S-1010`, chave e período opcionais). |
| `consultarEventosTrabalhador($cpfTrab, $dtIni, $dtFim)` | Ids dos eventos de um trabalhador entre duas datas (`'2026-01-01T00:00:00'`). |
| `downloadEventosPorId(array $ids)` | XML dos eventos pelos Ids. |
| `downloadEventosPorNrRecibo(array $nrRecs)` | XML dos eventos pelos números de recibo. |

As consultas de identificadores e os downloads vão assinados; a consulta de lote não.
`tpAmb` do config escolhe o endereço: Produção Restrita (`2`) ou Produção (`1`).

## Conexão (TLS) e certificado

Por padrão o `Tools` cria um `SoapCurl` com o certificado em memória e **verifica** o certificado
do servidor no store de CAs do sistema (os hosts do eSocial usam a raiz pública GlobalSign Root
R46; o pacote `ca-certificates` precisa estar instalado). Para mudar a conexão, monte o
`SoapCurl` e entregue ao `Tools`:

```php
use NFePHP\eSocial\Common\Soap\SoapCurl;

$soap = new SoapCurl($certificate);
$soap->timeout(60);
$soap->loadCA('/caminho/bundle.pem'); // SUBSTITUI o store do sistema; lança se o arquivo não existir
$tools->loadSoapClass($soap);
```

`$soap->disableSecurity(true)` desliga a verificação do servidor: só para ambiente controlado.
Nunca rode com Xdebug ou `display_errors` ligados onde há certificado: o stack trace pode
imprimir a chave.
````

- [x] **Step 3: conferir**

Rode: `python3 $SC/check_docs.py; echo $?` → saída vazia e `0`.

---

### Task 4: README — Forma de uso, Atualização com o gov.br e leiaute

**Arquivos:**
- Alterar: `README.md` (seção "Forma de uso" e os títulos de leiaute, ~l.46-52)
- Remover: `EVENTOS_S_1_2.md`, `EVENTOS_S_1_3.md` (substituídos por `docs/eventos/README.md`)

**Depende de:** Tasks 1-3

- [x] **Step 1: Forma de uso** — em `README.md`, substituir:

```markdown
## Forma de uso
Em breve ....
```

por:

````markdown
## Forma de uso

Fluxo completo: config, certificado, evento, envio do lote, consulta do processamento e leitura
das ocorrências. Detalhes em [docs/Config.md](docs/Config.md), [docs/Tools.md](docs/Tools.md) e,
campo a campo, em [docs/eventos](docs/eventos/README.md).

```php
use NFePHP\Common\Certificate;
use NFePHP\eSocial\Common\Standardize;
use NFePHP\eSocial\Common\SystemMessages;
use NFePHP\eSocial\Event;
use NFePHP\eSocial\Tools;

$configJson = json_encode([
    'tpAmb' => 2, // Produção Restrita
    'verProc' => 'MeuSistema_1.0',
    'eventoVersion' => 'S.1.3.0',
    'serviceVersion' => '1.6.0',
    'empregador' => ['tpInsc' => 1, 'nrInsc' => '12345678', 'nmRazao' => 'Empresa Exemplo Ltda'],
    'transmissor' => ['tpInsc' => 1, 'nrInsc' => '12345678000195'],
]);

// certificado A1 só em memória (não grave o PEM em disco)
$certificate = Certificate::readPfx(file_get_contents('/seguro/certificado.pfx'), $senha);

// 1. montar o evento: as chaves do stdClass seguem docs/eventos/S-1000.md
$std = new stdClass();
$std->sequencial = 1;
$std->modo = 'INC';
$std->ideperiodo = new stdClass();
$std->ideperiodo->inivalid = '2026-10';
$std->infocadastro = new stdClass();
$std->infocadastro->classtrib = '99';
$std->infocadastro->indcoop = 0;
$std->infocadastro->indconstr = 0;
$std->infocadastro->inddesfolha = 0;
$std->infocadastro->indporte = 'S';
$std->infocadastro->indoptregeletron = 1;

$evento = Event::s1000($configJson, $std, $certificate); // valida a entrada no JSON schema

// 2. enviar o lote (grupo 1 = tabelas); o XML é assinado e validado no XSD antes do envio
$tools = new Tools($configJson, $certificate);
$resposta = $tools->enviarLoteEventos(Tools::EVT_TABELAS, [$evento]);

$standardize = new Standardize();
$envio = $standardize->toStd($resposta);
$protocolo = $envio->dadosRecepcaoLote->protocoloEnvio; // cdResposta 201 = lote recebido

// 3. consultar o processamento (aguarde alguns segundos depois do envio)
$retorno = $tools->consultarLoteEventos($protocolo);

// 4. ocorrências: o retorno traz código e descrição; a ação sugerida vem do catálogo oficial
$dom = new DOMDocument();
$dom->loadXML($retorno);
foreach ($dom->getElementsByTagName('ocorrencia') as $ocorrencia) {
    $codigo = $ocorrencia->getElementsByTagName('codigo')->item(0)->textContent;
    $mensagem = SystemMessages::get($codigo); // ['codigo' => 'MS0191', 'texto' => ..., 'acoes' => [...]]
}
```

- **Id do evento:** `$evento->evtid`. Com vários processos montando eventos do mesmo empregador,
  aloque o `sequencial` (1 a 99999) para não repetir Id no mesmo segundo.
- **Eventos que a lib não monta** (retornos S-5xxx, extintos, S-8200/S-8299) respondem com
  `EventsException` código 1003 e o motivo.
- **TLS:** o certificado do servidor é verificado no store do sistema (instale
  `ca-certificates`). Opções em [docs/Tools.md](docs/Tools.md#conexão-tls-e-certificado).
- **Exemplos** de cada evento, com todos os campos: `examples/Fake/v_S_01_03_00/`.

## Atualização com o gov.br

O governo ajusta o leiaute S-1.3 por Nota Técnica várias vezes ao ano. O estado aplicado fica
em [ESOCIAL-VERSION.json](ESOCIAL-VERSION.json) (pacote de XSD, nota técnica, pacote de
comunicação, Mensagens do Sistema).

- A skill `update-esocial` (`.claude/skills/update-esocial/SKILL.md`) confere o
  [Portal do eSocial](https://www.gov.br/esocial/pt-br/documentacao-tecnica), aplica o pacote,
  compara os XSD, valida os exemplos e regenera [docs/eventos](docs/eventos/README.md).
- Pacote publicado antes da data fica pronto num branch `xsd/<data>` (lista e data de merge em
  `pending_packages`). O merge no `master` é feito na abertura da Produção Restrita.
- **Versões (semver):** pacote de XSD ou campo novo = *minor* (`2.1.0`); correção = *patch*;
  remoção ou mudança incompatível = *major*. Use `"systemweb-dev/esocial": "^2.0"` e acompanhe o
  [CHANGELOG](CHANGELOG.md).
````

- [x] **Step 2: títulos de leiaute** — substituir:

```markdown
## Layout S-1.0 (a partir de 10/05/2021)

## Layout S-1.1 (a partir de 19/03/2023)

## [Layout S-1.2 (a partir de 20/11/2023)](EVENTOS_S_1_2.md)

## [Layout S-1.3 (a partir de 02/12/2024)](EVENTOS_S_1_3.md)
```

por:

```markdown
## Leiaute

Só o leiaute **S-1.3** (o S-1.2 foi encerrado no eSocial em 02/02/2025). Eventos que a lib monta,
com os campos de cada um: [docs/eventos](docs/eventos/README.md).
```

E rode `git rm -q EVENTOS_S_1_2.md EVENTOS_S_1_3.md`.

- [x] **Step 3: o fluxo do README roda**

O fluxo da Forma de uso foi executado no planejamento com `SoapFake` e o certificado de teste
(`<scratchpad>/proto-docs/fluxo.php`: monta o S-1000, valida no XSD, envia ao fake, lê o protocolo
com `Standardize` e a ocorrência 191 com `SystemMessages`). Rode
`php -d xdebug.mode=off -d display_errors=0 <scratchpad>/proto-docs/fluxo.php`; esperado:
`evento ok`, `envio ok`, `protocolo: 1.2.202610…`, `ocorrencia MS0191 2 ações`.

- [x] **Step 4: conferir as docs**

Rode: `python3 $SC/check_docs.py; echo $?` → saída vazia e `0`.

---

### Task 5: Skill, AGENTS.md, CHANGELOG 2.0.0 e verificação

**Arquivos:**
- Alterar: `.claude/skills/update-esocial/SKILL.md` (seções 6 e 10)
- Alterar: `AGENTS.md` (mapa do código, comandos, regras)
- Alterar: `CHANGELOG.md` (`## Unreleased` → `## 2.0.0`)

**Depende de:** Tasks 1-4

- [x] **Step 1: SKILL.md** — na seção 6, substituir:

```markdown
- `php .claude/skills/update-esocial/scripts/validate.php` (todos os exemplos válidos no XSD novo)
- `php -d xdebug.mode=off vendor/bin/phpunit`
```

por:

```markdown
- `php .claude/skills/update-esocial/scripts/validate.php` (todos os exemplos válidos no XSD novo)
- `composer test` (suíte Pest)
- `python3 .claude/skills/update-esocial/scripts/gen_docs.py` (regenera `docs/eventos/`; confira o
  diff e a seção "Campos sem correspondência no XSD" das páginas alteradas)
- `python3 .claude/skills/update-esocial/scripts/check_docs.py` (README, Tools.md e Config.md só
  citam API existente)
```

E acrescentar no fim da seção 10:

```markdown

Versões (semver): no merge de um pacote, `## Unreleased` do `CHANGELOG.md` vira `## 2.N.0 - <data>`
e o commit recebe a tag anotada `v2.N.0` (push da tag com ok). Correção = patch; remoção ou
mudança incompatível = major. Antes do merge, `gen_docs.py --check` no branch.
```

- [x] **Step 2: AGENTS.md** — na tabela "Mapa do código", depois da linha de `examples/Fake`,
acrescentar:

```markdown
| `docs/eventos/` | uma página por evento, **gerada** por `gen_docs.py` (não edite à mão) |
| `docs/Tools.md`, `docs/Config.md` | API do `Tools` e chaves do config (conferidas por `check_docs.py`) |
```

No bloco de "Comandos", depois da linha do `validate.php`, acrescentar:

```bash
python3 .claude/skills/update-esocial/scripts/gen_docs.py --check  # docs/eventos em dia
python3 .claude/skills/update-esocial/scripts/check_docs.py        # docs só citam API existente
```

E em "Regras", depois da linha "- Branch `master`; commits em português; push só com ok do
responsável.", acrescentar:

```markdown
- Versões semver com tag anotada `vX.Y.Z` (desde `v2.0.0`): pacote XSD ou campo novo = minor,
  correção = patch, remoção = major. Registro no `CHANGELOG.md`.
```

- [x] **Step 3: CHANGELOG** — substituir a linha `## Unreleased` por:

```markdown
## Unreleased

## 2.0.0 - 2026-10-14

First release of the System Web fork. Breaking changes from the upstream 1.0.x API are listed
under Changed and Removed.

### Changed
- TLS of the eSocial webservices is verified by default (system CA store; the hosts use the public GlobalSign Root R46). `loadCA()` replaces the store and throws if the file does not exist; `disableSecurity(true)` is the explicit opt-out.
- The certificate and key go to cURL in memory (`CURLOPT_SSLCERT_BLOB`/`CURLOPT_SSLKEY_BLOB`); no PEM is written to disk.
- Only the S-1.3 layout: `eventoVersion` below 1.3 throws `InvalidArgumentException` in the event constructor.
- Batch groups follow the MOS S-1.3 (1 = tables, 2 = non-periodic, 3 = periodic); the group may be an int or a numeric string; an invalid group throws `InvalidArgumentException`.
- Event Ids no longer repeat in the same process; `sequencial` must be an integer from 1 to 99999.
- Communication package 1.6-Alfa (`serviceVersion '1.6.0'`, alphanumeric CNPJ) is the recommended one.
- PHP >= 8.1; optional typed parameters are explicitly nullable.
- `Tools::EVT_INICIAIS` is deprecated in favour of `Tools::EVT_TABELAS`.

### Removed
- Builders of events the library cannot send (extinct in S-1.x, eSocial returns S-5xxx, Judiciary S-8200/S-8299): calling them throws `EventsException` code 1003 with the reason.
- Layouts S-1.0, S-1.1 and S-1.2 (schemes, JSON schemas and examples).
- `ParamsStandardize`, `Sanitize`, `SoapCode`, the `console/` scripts and dead `Factory`/`SoapBase` methods.
- Per-event docs of the old layouts (`docs/Evt*.md`, `EVENTOS_S_1_*.md`), replaced by the generated `docs/eventos/`.
```

E, no `### Added` que agora é do 2.0.0, acrescentar:

```markdown
- Generated per-event docs in `docs/eventos/` (`gen_docs.py`) and the docs checker `check_docs.py`; README usage guide, `docs/Tools.md` and `docs/Config.md` rewritten.
```

- [x] **Step 4: verificação (restrições do spec)**

```bash
composer test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep 'Tests:'
python3 -m unittest discover -s $SC -p 'test_*.py' 2>&1 | tail -1
php -d xdebug.mode=off $SC/validate.php | tail -1
python3 $SC/gen_docs.py --check; echo "gen_docs $?"
python3 $SC/check_docs.py; echo "check_docs $?"
rm -rf $SC/__pycache__
```

Esperado: Pest verde sem deprecated; `OK` (42 testes); `40/40`; `gen_docs 0`; `check_docs 0`.
**Checkpoint:** commits (gerador + docs; verificador; textos; skill/AGENTS/CHANGELOG) com o "sim".

---

### Task 6: Tag v2.0.0 e branches `xsd/*`

**Arquivos:**
- Alterar: `CHANGELOG.md` (nos branches `xsd/*`, conflito do rebase)
- Alterar: `docs/eventos/README.md` (regenerado em cada branch, com as páginas)

**Depende de:** Task 5 commitada

- [x] **Step 1: tag** (com o ok do usuário)

```bash
git switch master && git status --short          # vazio
git tag -a v2.0.0 -m "v2.0.0: primeira versão do fork System Web (leiaute S-1.3, pacote XSD 2026-10-26)"
```

- [x] **Step 2: conferir**

Rode: `git describe --tags` → `v2.0.0`; `grep -c '^## 2.0.0' CHANGELOG.md` → `1`.

- [x] **Step 3: rebase dos branches**

```bash
git rebase --update-refs master xsd/2027-01-18
```

Em cada conflito do `CHANGELOG.md`: o item do pacote (`- Apply the S-1.3 XSD package of …`, e
no 12-14 a linha do `codIncCP`) vai para um `### Added` logo abaixo de `## Unreleased`; o
`## 2.0.0` fica igual ao do master. Depois `git add CHANGELOG.md && GIT_EDITOR=true git rebase --continue`.

- [x] **Step 4: docs regeneradas em cada branch**

Para cada branch, na ordem `xsd/2026-11-23`, `xsd/2026-12-14`, `xsd/2027-01-18`:

```bash
git switch <branch>
python3 $SC/gen_docs.py && git add docs/eventos && git commit -m "docs: regenera docs/eventos para o pacote <data>"
git rebase --update-refs <branch> xsd/2027-01-18   # só nos dois primeiros: leva o commit aos seguintes
```

(Conflito em `docs/eventos` no rebase: aceite o lado do branch e regenere no passo seguinte.)

- [x] **Step 5: verificar cada branch**

```bash
for b in xsd/2026-11-23 xsd/2026-12-14 xsd/2027-01-18; do git switch -q $b
  echo "$b: $(composer test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep 'Tests:' | xargs) | $(php -d xdebug.mode=off $SC/validate.php | tail -1 | cut -c1-5) | docs $(python3 $SC/gen_docs.py --check >/dev/null; echo $?)"
done; git switch -q master; rm -rf $SC/__pycache__
```

Esperado: verde, `40/40`, `docs 0` nos três. **Checkpoint:** push com o ok
(`git push origin master v2.0.0` e `git push --force-with-lease origin xsd/2026-11-23 xsd/2026-12-14 xsd/2027-01-18`,
porque os branches já estão no GitHub e foram rebaseados).

---

## Ajustes durante a execução

- **2026-10-14 (planejamento)** — o `README.md` citava `EVENTOS_S_1_2.md`/`EVENTOS_S_1_3.md`
  (listas antigas); entram na Task 4 (substituídos pelo índice gerado). A seção de instalação fica
  como está (decisão do usuário).
- **2026-10-14 (planejamento)** — o rascunho do `Config.md` dizia que sem `sequencial` a lib usa 1;
  o `FactoryId::build` sorteia de 1 a 99999. Corrigido no texto e no `LIB_FIELDS` do gerador.
- **2026-10-14 (juiz do batch 1)** — o casamento só pelo nome (com desempate pelo pai) errava em
  51 campos: o JSON achata grupos (`ideEvento`) e o índice olhava o `tipos.xsd` inteiro (S-1210
  `perapur` com o texto de `T_ideEvento_folha`; `detreembtit/tpinsc` com o do empregador; S-2306
  `matricula`). Agora `xsd_paths` percorre o XSD a partir do elemento do evento expandindo os
  tipos nomeados, e `describe` escolhe o candidato que contém mais grupos do caminho do JSON, na
  ordem (grupo do XSD pode ter prefixo: `infoTrabCedido` ↔ `trabcedido`); se nenhum casa tudo e os
  textos diferem, o campo vai para "sem correspondência" (5 casos: S-1005 `infopdc`, S-1260
  `estabelecimento`, S-2299 `procs`, S-2306 `mandelelt`). Título e descrição usam
  `DESCRICAO_COMPLETA` e todos os parágrafos (validação com lista `a)`, `b)`), sem as marcações
  `CHAVE_GRUPO`/`REGRA`. `check_docs`: links com título ou `<…>` e constantes com dígito. Testes
  novos: grupo achatado, tipo nomeado, prefixo `info`, ambíguo, cabeçalho com Classe/Montar.
- **2026-10-14 (juiz do batch 2)** — `Tools.md`: assinatura no envio só vale se o `toXML()` do
  evento não foi chamado antes; tirado o erro "nenhum certificado" (o construtor do `Tools` exige
  um); parâmetros sem `array` (o código não tipa); erros reais acrescentados (`serviceVersion` sem
  pacote, grupo declarado ≠ grupo dos XML, certificado vencido). README: o exemplo confere
  `cdResposta` 201 antes de ler o protocolo; o comentário do `get()` cita `variantes`. CHANGELOG
  2.0.0: rename do pacote, `EventsException::getCode()` (1004/1005 removidos) e o `XsdSeeker`.
- **2026-10-14 (Task 6)** — o conflito do CHANGELOG no rebase foi resolvido por script (lado do
  master + linhas novas do branch em `## Unreleased`); no commit do `codIncCP`, que **altera** a
  linha do pacote 12-14, o script duplicou a linha: corrigido com amend no `xsd/2026-12-14` e
  `rebase --skip` do commit antigo no `xsd/2027-01-18`. Cada branch ganhou um commit
  "docs: regenera docs/eventos" (o cabeçalho das páginas traz o pacote XSD).
