#!/usr/bin/env python3
"""Extrai o catálogo das Mensagens do Sistema do eSocial (PDF do gov.br) para JSON.

Uso: python3 extract_mensagens.py <mensagens.pdf> <versao> <url-do-pdf> [saida.json]
Saída padrão: data/mensagens-sistema.json na raiz do repositório. Precisa do pdftotext
(pacote poppler-utils).

O <ocorrencia><codigo>N</codigo> do retorno do eSocial corresponde a MS + N com 4 dígitos.
"""
import json
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[4]
ENTRY = re.compile(r'^(MS\d{4})(_[A-Z0-9_]+)? - (.*)$')
MARK = re.compile(r'^(?:Ações Sugeridas|Ação [Ss]ugerida):\s*(.*)$')
INLINE = re.compile(r'\s(?:Ações Sugeridas|Ação [Ss]ugerida):\s*')
PAGE = re.compile(r'^\d+$')


def page_lines(text: str) -> list:
    """Linhas do texto sem os números de página (sozinhos entre linhas em branco ou no fim da página).

    A virada de página não vira linha em branco: um parágrafo que atravessa a página continua.
    """
    out = []
    for page in text.split('\f'):
        lines = page.split('\n')
        while lines and (not lines[-1].strip() or PAGE.match(lines[-1].strip())):
            lines.pop()
        for i, line in enumerate(lines):
            prev = lines[i - 1].strip() if i > 0 else ''
            nxt = lines[i + 1].strip() if i + 1 < len(lines) else ''
            if PAGE.match(line.strip()) and not prev and not nxt:
                continue
            out.append(line)
    return out


def parse(text: str) -> dict:
    """Código (MSnnnn ou MSnnnn_SUFIXO) -> {'texto': str, 'acoes': [str, ...]}, em ordem de código."""
    lines = page_lines(text)
    start = next(i for i, line in enumerate(lines) if line.startswith('MS0001 - '))
    entries = {}
    cur = None
    for raw in lines[start:]:
        line = raw.strip()
        entry = ENTRY.match(line)
        if entry:
            cur = {'texto': [], 'acoes': [], 'in_acoes': False}
            entries[entry.group(1) + (entry.group(2) or '')] = cur
            line = entry.group(3)
        if cur is None:
            continue
        if not line:
            if cur['in_acoes'] and cur['acoes'] and cur['acoes'][-1]:
                cur['acoes'].append('')
            continue
        if not cur['in_acoes'] and not MARK.match(line):
            parts = INLINE.split(line, maxsplit=1)
            if len(parts) == 2:
                if parts[0]:
                    cur['texto'].append(parts[0])
                line = 'Ação Sugerida: ' + parts[1]
        mark = MARK.match(line)
        if mark:
            cur['in_acoes'] = True
            rest = mark.group(1)
            while MARK.match(rest):
                rest = MARK.match(rest).group(1)
            if rest:
                cur['acoes'].append(rest)
            continue
        if not cur['in_acoes']:
            cur['texto'].append(line)
        elif line.startswith('- '):
            cur['acoes'].append(line[2:])
        elif cur['acoes'] and cur['acoes'][-1]:
            cur['acoes'][-1] += ' ' + line
        elif cur['acoes']:
            cur['acoes'][-1] = line
        else:
            cur['acoes'].append(line)
    return {code: {'texto': ' '.join(e['texto']).strip(), 'acoes': [a for a in e['acoes'] if a]}
            for code, e in sorted(entries.items())}


def main() -> int:
    if len(sys.argv) not in (4, 5):
        print(__doc__)
        return 2
    pdf, versao, fonte = sys.argv[1], sys.argv[2], sys.argv[3]
    out = Path(sys.argv[4]) if len(sys.argv) == 5 else ROOT / 'data' / 'mensagens-sistema.json'
    text = subprocess.run(['pdftotext', pdf, '-'], capture_output=True, text=True, check=True).stdout
    mensagens = parse(text)
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps({'versao': versao, 'fonte': fonte, 'mensagens': mensagens},
                              ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(f'{len(mensagens)} mensagens gravadas em {out}')
    return 0


if __name__ == '__main__':
    sys.exit(main())
