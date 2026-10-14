#!/usr/bin/env python3
"""Compara dois conjuntos de XSD do eSocial e mostra o que mudou, por arquivo e por elemento.

Uso: python3 compare_xsd.py <pasta_atual> <pasta_nova>
Saída 0 = idênticos; 10 = há mudanças.
"""
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

XS = '{http://www.w3.org/2001/XMLSchema}'


def norm(path: Path) -> str:
    return path.read_bytes().decode('utf-8-sig').replace('\r', '')


def elements(path: Path) -> dict:
    """Mapa caminho -> atributos e regras de cada xs:element do arquivo."""
    root = ET.fromstring(norm(path).encode())
    out = {}

    def walk(node, prefix):
        for child in node:
            p = prefix
            if child.tag in (f'{XS}complexType', f'{XS}simpleType') and child.get('name'):
                p = f"#{child.get('name')}"
            if child.tag == f'{XS}element' and (child.get('name') or child.get('ref')):
                p = f"{prefix}/{child.get('name') or child.get('ref')}"
                # só a anotação e o simpleType do próprio elemento (não dos filhos)
                ann = child.find(f'{XS}annotation')
                docs = [d.text.strip() for d in (ann.findall(f'{XS}documentation') if ann is not None else [])
                        if d.text and d.text.strip().startswith('Validação')]
                st = child.find(f'{XS}simpleType')
                enums = sorted(e.get('value') for e in (st.iter(f'{XS}enumeration') if st is not None else []))
                out[p] = {
                    'type': child.get('type') or '',
                    'min': child.get('minOccurs', '1'),
                    'max': child.get('maxOccurs', '1'),
                    'enum': enums,
                    'valid': docs[:1],
                }
            walk(child, p)

    walk(root, '')
    return out


FACETS = ('length', 'minLength', 'maxLength', 'pattern', 'totalDigits', 'fractionDigits',
          'minInclusive', 'maxInclusive', 'minExclusive', 'maxExclusive')


def simple_types(path: Path) -> dict:
    """Mapa nome -> enumerações e facetas de cada xs:simpleType nomeado do arquivo."""
    root = ET.fromstring(norm(path).encode())
    out = {}
    for st in root.iter(f'{XS}simpleType'):
        name = st.get('name')
        if not name:
            continue
        restriction = st.find(f'{XS}restriction')
        facets = {'base': restriction.get('base', '') if restriction is not None else ''}
        for facet in FACETS:
            node = restriction.find(f'{XS}{facet}') if restriction is not None else None
            if node is not None:
                facets[facet] = node.get('value')
        out[name] = {'enum': sorted(e.get('value') for e in st.iter(f'{XS}enumeration')), 'facets': facets}
    return out


def compare(old: Path, new: Path) -> list:
    a, b = elements(old), elements(new)
    lines = []
    for p in sorted(set(b) - set(a)):
        lines.append(f'  + novo elemento {p} (min={b[p]["min"]})')
    for p in sorted(set(a) - set(b)):
        lines.append(f'  - removido {p}')
    for p in sorted(set(a) & set(b)):
        x, y = a[p], b[p]
        if x['min'] != y['min']:
            what = 'virou obrigatório' if y['min'] != '0' and x['min'] == '0' else 'virou opcional' if y['min'] == '0' else 'minOccurs mudou'
            lines.append(f'  * {p}: {what} ({x["min"]} -> {y["min"]})')
        if x['max'] != y['max']:
            lines.append(f'  * {p}: maxOccurs {x["max"]} -> {y["max"]}')
        if x['type'] != y['type']:
            lines.append(f'  * {p}: tipo {x["type"] or "(local)"} -> {y["type"] or "(local)"}')
        if x['enum'] != y['enum']:
            add = sorted(set(y['enum']) - set(x['enum']))
            rem = sorted(set(x['enum']) - set(y['enum']))
            lines.append(f'  * {p}: valores +{add} -{rem}')
        if x['valid'] != y['valid']:
            lines.append(f'  * {p}: regra de validação alterada: {(y["valid"] or [""])[0][:180]}')
    ta, tb = simple_types(old), simple_types(new)
    for name in sorted(set(tb) - set(ta)):
        lines.append(f'  + novo tipo {name}')
    for name in sorted(set(ta) - set(tb)):
        lines.append(f'  - tipo removido {name}')
    for name in sorted(set(ta) & set(tb)):
        x, y = ta[name], tb[name]
        if x['enum'] != y['enum']:
            add = sorted(set(y['enum']) - set(x['enum']))
            rem = sorted(set(x['enum']) - set(y['enum']))
            lines.append(f'  * {name}: valores +{add} -{rem}')
        for facet in sorted(set(x['facets']) | set(y['facets'])):
            if x['facets'].get(facet) != y['facets'].get(facet):
                lines.append(f'  * {name}: {facet} {x["facets"].get(facet)} -> {y["facets"].get(facet)}')
    return lines


def main() -> int:
    if len(sys.argv) != 3:
        print(__doc__)
        return 2
    old_dir, new_dir = Path(sys.argv[1]), Path(sys.argv[2])
    olds = {p.name: p for p in old_dir.rglob('*.xsd')}
    news = {p.name: p for p in new_dir.rglob('*.xsd')}
    changed = 0
    for name in sorted(set(olds) | set(news)):
        if name not in olds:
            print(f'{name}: ARQUIVO NOVO'); changed += 1; continue
        if name not in news:
            print(f'{name}: ARQUIVO REMOVIDO'); changed += 1; continue
        if norm(olds[name]) == norm(news[name]):
            continue
        changed += 1
        detail = compare(olds[name], news[name])
        print(f'{name}:')
        print('\n'.join(detail) if detail else '  (mudança só em documentação/formatação)')
    print(f'\n{changed} arquivo(s) diferente(s) de {len(news)}')
    return 10 if changed else 0


if __name__ == '__main__':
    sys.exit(main())
