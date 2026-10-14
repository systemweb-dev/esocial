#!/usr/bin/env python3
"""Mostra o que o Portal do eSocial publicou além do que o fork tem aplicado (fonte que vale).

O upstream NFePHP é só consulta: os commits novos aparecem como informação, sem contar como
novidade a aplicar.

Fontes: https://www.gov.br/esocial/pt-br/documentacao-tecnica e
https://github.com/nfephp-org/sped-esocial (branch master). Estado: ESOCIAL-VERSION.json na raiz.
Saída 0 = nada has_news; 10 = há novidade; 2 = fonte ilegível.
"""
import html
import json
import re
import sys
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[4]
DOC_URL = 'https://www.gov.br/esocial/pt-br/documentacao-tecnica'
UPSTREAM = 'https://api.github.com/repos/nfephp-org/sped-esocial/commits?sha=master&per_page=50'


def get(url: str) -> str:
    req = urllib.request.Request(url, headers={'User-Agent': 'systemweb-esocial-check'})
    with urllib.request.urlopen(req, timeout=30) as r:
        return r.read().decode('utf-8', 'ignore')


def links(page: str) -> list:
    out = []
    for href, text in re.findall(r'<a[^>]+href="([^"]+)"[^>]*>(.*?)</a>', page, re.S):
        t = html.unescape(re.sub(r'<[^>]+>', '', text)).strip()
        if t:
            out.append((t, href))
    return out


def parse(page: str) -> dict:
    items = links(page)
    xsd = {}
    for t, href in items:
        m = re.search(r'(\d{4}-\d{2}-\d{2})_esquemas_xsd_v_s_01_03_00', href)
        if m:
            xsd[m.group(1)] = href
    def nt_key(t):
        m = re.search(r'(\d{2})/(\d{4})', t)
        return (int(m.group(2)), int(m.group(1)), 'rev' in t) if m else (0, 0, False)
    nts = sorted({re.sub(r'\s+', ' ', t) for t, _ in items if re.match(r'Nota T[ée]cnica v\. S-1\.3', t)}, key=nt_key)
    comm = sorted({m.group(1) + ('-Alfa' if m.group(2) else '')
                   for t, _ in items
                   for m in [re.search(r'Pacote de Comunica[çc][ãa]o eSocial v\.?\s*(\d+(?:\.\d+)*)(\s*-\s*Alfa)?', t)] if m},
                  key=lambda v: comm_key(v))
    msgs = sorted({m.group(1) for t, _ in items for m in [re.search(r'Mensagens do Sistema - v\.\s*([\d.]+)', t)] if m})
    return {'xsd': xsd, 'nts': nts, 'comm': comm, 'msgs': msgs}


def ver(v: str) -> tuple:
    return tuple(int(x) for x in v.split('.') if x.isdigit())


def comm_key(v: str) -> tuple:
    """Ordem do Pacote de Comunicação: 1.6 < 1.6-Alfa (superconjunto mais novo) < 1.7."""
    nums = [int(x) for x in re.findall(r'\d+', v.split('-')[0])]
    return tuple((nums + [0, 0, 0])[:3]) + (1 if 'alfa' in v.lower() else 0,)


def comm_is_news(published: list, applied: str) -> bool:
    """Há pacote publicado acima do aplicado (ver comm_key)."""
    return bool(published) and max(comm_key(v) for v in published) > comm_key(applied)


def msgs_is_news(published: list, applied) -> bool:
    """Mensagens do Sistema publicadas acima da versão revisada (None = nunca revisada)."""
    if not published:
        return False
    return applied is None or max(ver(v) for v in published) > ver(applied)


def pending_branch(state: dict, package: str):
    """Pacote de XSD já preparado num branch (pending_packages do ESOCIAL-VERSION.json) ou None."""
    return next((p for p in state.get('pending_packages', []) if p.get('xsd_package') == package), None)


def main() -> int:
    state = json.loads((ROOT / 'ESOCIAL-VERSION.json').read_text())
    try:
        found = parse(get(DOC_URL))
    except Exception as e:  # noqa: BLE001
        print(f'ERRO ao ler {DOC_URL}: {e}')
        return 2
    if not found['xsd']:
        print(f'ERRO: nenhum pacote de XSD encontrado em {DOC_URL} (o layout da página mudou?)')
        return 2

    has_news = False
    applied = state['xsd_package']
    pending = sorted(d for d in found['xsd'] if d > applied)
    print(f'XSD S-1.3     aplicado {applied} | novos: {", ".join(pending) or "nenhum"}')
    for package in pending:
        ready = pending_branch(state, package)
        if ready:
            print(f'              {package}: pronto no branch {ready["branch"]} (merge em {ready["merge_on"]})')
    has_news |= bool(pending)

    latest_nt = found['nts'][-1] if found['nts'] else '?'
    nt_applied = state['technical_note'] in latest_nt
    print(f'Nota técnica  aplicada {state["technical_note"]} | mais recente na página: {latest_nt}')
    has_news |= not nt_applied

    comm = max(found['comm'], key=comm_key) if found['comm'] else '?'
    print(f'Comunicação   aplicada {state["communication"]} | publicada {comm}')
    has_news |= comm_is_news(found['comm'], state['communication'])

    msgs = max(found['msgs'], key=ver) if found['msgs'] else '?'
    print(f'Mensagens     lida {state.get("system_messages") or "nunca"} | publicada {msgs}')
    has_news |= msgs_is_news(found['msgs'], state.get('system_messages'))

    try:
        commits = json.loads(get(UPSTREAM))
        shas = [c['sha'] for c in commits]
        base = state['upstream_reviewed']
        idx = next((i for i, s in enumerate(shas) if s.startswith(base)), None)
        new_commits = commits[:idx] if idx is not None else commits
        label = f'desde {base[:7]}' if idx is not None else f'(base {base[:7]} fora dos últimos 50)'
        print(f'Upstream      {len(new_commits)} commit(s) novo(s) {label} (só consulta)')
        for c in new_commits[:10]:
            print(f'              {c["sha"][:7]} {c["commit"]["committer"]["date"][:10]} {c["commit"]["message"].splitlines()[0][:80]}')
    except Exception as e:  # noqa: BLE001
        print(f'Upstream      não consultado ({e})')

    if pending:
        ready = pending_branch(state, pending[0])
        if ready:
            print(f'\nPróximo passo: fazer o merge de {ready["branch"]} no master em {ready["merge_on"]}')
        else:
            print(f'\nPróximo passo: aplicar XSD {pending[0]} ({found["xsd"][pending[0]]})')
    return 10 if has_news else 0


if __name__ == '__main__':
    sys.exit(main())
