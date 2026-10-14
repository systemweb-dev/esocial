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
