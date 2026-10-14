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
