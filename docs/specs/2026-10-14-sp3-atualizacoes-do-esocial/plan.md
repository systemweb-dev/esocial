# Plano de implementação — SP3 atualizações do eSocial

[spec.md](spec.md)

> **Execução:** implementar task por task. Os steps usam checkbox (`- [ ]`) para acompanhar.
> Os dois modos de execução estão na seção "Execution Handoff" da skill `sw-plan`.

**Objetivo:** ferramentas da `update-esocial` confiáveis, `indPertIRRF` no S-1000, catálogo das
Mensagens do Sistema na lib e os 3 pacotes de XSD prontos em branches para o merge na data.

**Arquitetura:** Tasks 1-6 no `master` (scripts Python da skill com `unittest`; classe nova
`Common\SystemMessages` lendo `data/mensagens-sistema.json`; ajuste pontual no `TraitS1000`).
Tasks 7-9 criam `xsd/2026-11-23` → `xsd/2026-12-14` → `xsd/2027-01-18`, um a partir do outro.

**Stack:** PHP 8.4 (dev), Pest 5.3, Python 3 stdlib (`unittest`), `pdftotext` (poppler-utils, só
para o script da skill).

**Restrições verificáveis (do spec):**
| Restrição | Como o plano checa |
|---|---|
| `master`: `composer test` e `python3 -m unittest …` verdes; resumo sem `deprecated`/`failed` | Task 6, step 4 |
| `compare_xsd.py` contra o pacote 2026-11-23 lista `TS_indOrgReemb` com `+['3']` | Task 4, step 5 |
| `data/mensagens-sistema.json`: 1000-1200 mensagens, MS0002/MS0191/MS1018/MS0266_V01_00_00, nenhum marcador no `texto` | Task 1, step 6 |
| Cada branch: `validate.php` 40/40, suíte verde, `xsd_package = <data>`; `xsd/2026-12-14` contém os commits do `xsd/2026-11-23` | Tasks 7-9, último step |

**Convenções para quem executa:**
- Raiz `/var/www/esocial`. PHP: `composer test` ou `php -d xdebug.mode=off vendor/bin/pest <arquivo>`.
  Python: `python3 -m unittest discover -s .claude/skills/update-esocial/scripts -p 'test_*.py' -v`.
- Pest em AAA estrito; `unittest` também em AAA (setUp/fixture → uma chamada → asserts).
- Pacotes já extraídos: `P=/tmp/claude-1000/-var-www-esocial/b3c667c3-32b6-440c-9034-e207a90f4558/scratchpad/analise-xsd`
  (`$P/2026-11-23/x`, `$P/2026-12-14/x`, `$P/2027-01-18/x`, 52 `.xsd` cada). PDF das Mensagens:
  `/tmp/claude-1000/-var-www-esocial/b3c667c3-32b6-440c-9034-e207a90f4558/scratchpad/mensagens/msg.pdf`.
  Se sumirem, baixe cada um numa pasta **vazia** (URLs no spec).
- Antes de cada commit: `git diff --cached --quiet || { echo "há arquivos já preparados"; git diff --cached --name-only; }`
  e **pare** se houver algo que não é do checkpoint. Não encadeie `git add` e `git commit` sem
  checar o resultado do `git add`.
- Commit só no checkpoint, com o "sim" do usuário. Nas Tasks 7-9 o checkpoint é por task, porque
  cada branch precisa do commit do anterior.

**Ordem pelo risco:** a Task 1 ataca a suposição de maior risco do spec (o texto do PDF dá um
catálogo limpo). O parser já foi prototipado no planejamento contra o PDF real: 1133 entradas, 624
com ações, 34 variantes, 0 marcadores sobrando no texto.

---

### Task 1: Parser das Mensagens do Sistema

**Arquivos:**
- Criar: `.claude/skills/update-esocial/scripts/test_extract_mensagens.py`
- Criar: `.claude/skills/update-esocial/scripts/extract_mensagens.py`
- Criar: `data/mensagens-sistema.json` (gerado)

**Depende de:** nada

**Contrato que esta task publica:** `extract_mensagens.parse(text: str) -> dict` (código →
`{'texto': str, 'acoes': list}`); arquivo `data/mensagens-sistema.json` com
`{"versao", "fonte", "mensagens"}`.

- [x] **Step 1: escrever os testes que falham**

Criar `.claude/skills/update-esocial/scripts/test_extract_mensagens.py`:

```python
"""Testes do parser das Mensagens do Sistema com um texto fixo no formato do pdftotext."""
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import extract_mensagens  # noqa: E402

TEXT = (
    'Mensagens do Sistema\n'
    'Histórico de versões\n'
    '\n'
    'MS0001 - Mensagem simples.\n'
    'MS0002 - O campo não pode ser\n'
    'preenchido: {0}.\n'
    'MS0003 - Com plural.\n'
    'Ações Sugeridas:\n'
    '- Primeira ação\n'
    '- Segunda ação que\n'
    'continua na linha seguinte.\n'
    '\n'
    '7\n'
    '\n'
    '\f'
    'MS0004 - Ação na mesma linha.\n'
    'Ação Sugerida: Fazer tal coisa.\n'
    'MS0005 - Marcador sozinho.\n'
    'Ação sugerida:\n'
    'Parágrafo da ação\n'
    'em duas linhas.\n'
    'MS0006 - Texto com Ação sugerida para evitar o erro: não é marcador.\n'
    'MS0007_V01_00_00 - Variante com sufixo. Ação Sugerida: Ação inline.\n'
    'MS0008 - Marcador repetido.\n'
    'Ação Sugerida: Ação Sugerida: Sem repetição.\n'
    '\n'
    '12\n'
    '9\n'
)


class ParseTest(unittest.TestCase):
    def setUp(self):
        self.messages = extract_mensagens.parse(TEXT)

    def test_ignora_o_que_vem_antes_do_ms0001(self):
        codes = list(self.messages)

        self.assertEqual(codes[0], 'MS0001')

    def test_mensagem_simples(self):
        message = self.messages['MS0001']

        self.assertEqual(message, {'texto': 'Mensagem simples.', 'acoes': []})

    def test_une_as_linhas_do_texto_e_mantem_o_marcador_de_parametro(self):
        message = self.messages['MS0002']

        self.assertEqual(message['texto'], 'O campo não pode ser preenchido: {0}.')

    def test_acoes_em_itens_com_continuacao_e_sem_numero_de_pagina(self):
        message = self.messages['MS0003']

        self.assertEqual(message['acoes'], ['Primeira ação', 'Segunda ação que continua na linha seguinte.'])

    def test_acao_na_mesma_linha_do_marcador(self):
        message = self.messages['MS0004']

        self.assertEqual(message['acoes'], ['Fazer tal coisa.'])

    def test_paragrafo_depois_do_marcador_vira_uma_acao(self):
        message = self.messages['MS0005']

        self.assertEqual(message['acoes'], ['Parágrafo da ação em duas linhas.'])

    def test_acao_sugerida_no_meio_da_frase_fica_no_texto(self):
        message = self.messages['MS0006']

        self.assertEqual(message, {'texto': 'Texto com Ação sugerida para evitar o erro: não é marcador.', 'acoes': []})

    def test_variante_com_sufixo_e_marcador_no_meio_da_linha(self):
        message = self.messages['MS0007_V01_00_00']

        self.assertEqual(message, {'texto': 'Variante com sufixo.', 'acoes': ['Ação inline.']})

    def test_marcador_repetido_e_numero_de_pagina_partido_no_fim(self):
        message = self.messages['MS0008']

        self.assertEqual(message, {'texto': 'Marcador repetido.', 'acoes': ['Sem repetição.']})


if __name__ == '__main__':
    unittest.main()
```

- [x] **Step 2: rodar e confirmar que falham**

Rode: `python3 -m unittest discover -s .claude/skills/update-esocial/scripts -p 'test_extract*.py' -v`
Esperado: ERRO `ModuleNotFoundError: No module named 'extract_mensagens'`.

- [x] **Step 3: implementar o parser**

Criar `.claude/skills/update-esocial/scripts/extract_mensagens.py`:

```python
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
    """Linhas do texto sem os números de página (sozinhos entre linhas em branco ou no fim da página)."""
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
        out.append('')
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
```

- [x] **Step 4: rodar e confirmar que passam**

Rode: `python3 -m unittest discover -s .claude/skills/update-esocial/scripts -p 'test_extract*.py' -v`
Esperado: 9 testes `ok`.

- [x] **Step 5: gerar o catálogo do PDF real**

```bash
python3 .claude/skills/update-esocial/scripts/extract_mensagens.py \
  /tmp/claude-1000/-var-www-esocial/b3c667c3-32b6-440c-9034-e207a90f4558/scratchpad/mensagens/msg.pdf \
  2.5 https://www.gov.br/esocial/pt-br/documentacao-tecnica/manuais/998566-mensagensdosistema-v2-5.pdf
```

Esperado: `1133 mensagens gravadas em /var/www/esocial/data/mensagens-sistema.json`.

- [x] **Step 6: conferir o catálogo (restrição 3)**

Rode:

```bash
python3 -c "import json,re; m=json.load(open('data/mensagens-sistema.json'))['mensagens']; print(len(m), all(k in m for k in ['MS0002','MS0191','MS1018','MS0266_V01_00_00']), [k for k,v in m.items() if re.search(r'(Ação [Ss]ugerida|Ações Sugeridas):', v['texto'])], len(m['MS0191']['acoes']))"
```

Esperado: `1133 True [] 2`. Abra o PDF nas entradas MS0191 e MS0611 e confira o texto e as ações
do JSON contra o original.

---

### Task 2: `SystemMessages` na lib

**Arquivos:**
- Criar: `tests/Unit/SystemMessagesTest.php`
- Criar: `src/Common/SystemMessages.php`

**Depende de:** Task 1 (`data/mensagens-sistema.json`)

**Contrato que esta task publica:** `SystemMessages::get($codigo): ?array`
(`['codigo', 'texto', 'acoes', 'variantes']` pelo número; `['codigo', 'texto', 'acoes']` pela
variante); `SystemMessages::version(): string`.

- [x] **Step 1: escrever os testes que falham**

Criar `tests/Unit/SystemMessagesTest.php`:

```php
<?php

use NFePHP\eSocial\Common\SystemMessages;

/*
 * Catálogo oficial das Mensagens do Sistema (data/mensagens-sistema.json). O retorno do lote traz
 * <ocorrencia><codigo>191</codigo>; a mensagem correspondente é MS0191.
 */

it('devolve o texto e as ações sugeridas de uma mensagem', function () {
    $codigo = 191;

    $message = SystemMessages::get($codigo);

    expect($message['codigo'])->toBe('MS0191')
        ->and($message['texto'])->toBe('Não é permitido o desligamento do trabalhador.')
        ->and($message['acoes'])->toHaveCount(2)
        ->and($message['variantes'])->toBe([]);
});

it('aceita o código como número, texto ou com o prefixo MS', function ($codigo) {
    $expected = SystemMessages::get(191);

    $message = SystemMessages::get($codigo);

    expect($message)->toBe($expected);
})->with(['191', '0191', 'MS0191', 'ms0191']);

it('devolve a mensagem-base com as variantes de sufixo do mesmo número', function () {
    $codigo = 266;

    $message = SystemMessages::get($codigo);

    expect($message['codigo'])->toBe('MS0266')
        ->and($message['texto'])->toBe("Rubrica '{0}' incompatível com Contribuinte Individual.")
        ->and(array_column($message['variantes'], 'codigo'))
        ->toBe(['MS0266_V01_00_00', 'MS0266_V01_02_00', 'MS0266_V02_05_00']);
});

it('devolve só a variante pedida pelo código completo', function () {
    $codigo = 'MS0266_V01_00_00';

    $message = SystemMessages::get($codigo);

    expect($message['codigo'])->toBe('MS0266_V01_00_00')
        ->and($message['texto'])->toBe("Rubrica '{0}' incompatível com Contribuinte Individual.")
        ->and($message)->not->toHaveKey('variantes');
});

it('devolve null para código que não existe', function ($codigo) {
    $message = SystemMessages::get($codigo);

    expect($message)->toBeNull();
})->with([9999, 'abc', 'MS0001_INEXISTENTE']);

it('informa a versão do catálogo', function () {
    $version = SystemMessages::version();

    expect($version)->toBe('2.5');
});
```

- [x] **Step 2: rodar e confirmar que falham**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/SystemMessagesTest.php`
Esperado: FALHA com `Class "NFePHP\eSocial\Common\SystemMessages" not found`.

- [x] **Step 3: implementar**

Criar `src/Common/SystemMessages.php`:

```php
<?php

namespace NFePHP\eSocial\Common;

/**
 * Catálogo oficial das Mensagens do Sistema do eSocial.
 *
 * Fonte: data/mensagens-sistema.json, gerado pelo script extract_mensagens.py da skill
 * update-esocial a partir do PDF publicado no gov.br. O <ocorrencia><codigo>N</codigo> do retorno
 * do lote corresponde a MS + N com 4 dígitos. O retorno traz a descrição, mas não a ação sugerida.
 */
class SystemMessages
{
    /**
     * @var array|null
     */
    private static $catalog;

    /**
     * Mensagem pelo número (191, "191", "0191", "MS0191") ou pela variante ("MS0266_V01_00_00").
     * Pelo número, devolve a mensagem-base e, em "variantes", as entradas com sufixo do mesmo
     * número; se só houver variantes, "texto" e "acoes" são null.
     *
     * @param int|string $codigo
     * @return array|null
     */
    public static function get($codigo): ?array
    {
        $messages = self::catalog()['mensagens'];
        $codigo = strtoupper(trim((string) $codigo));
        if (preg_match('/^MS\d{4}_[A-Z0-9_]+$/', $codigo)) {
            return isset($messages[$codigo]) ? self::entry($codigo, $messages[$codigo]) : null;
        }
        if (!preg_match('/^(?:MS)?(\d{1,4})$/', $codigo, $match)) {
            return null;
        }
        $base = 'MS' . str_pad($match[1], 4, '0', STR_PAD_LEFT);
        $variantes = [];
        foreach ($messages as $key => $message) {
            if (strpos($key, $base . '_') === 0) {
                $variantes[] = self::entry($key, $message);
            }
        }
        if (!isset($messages[$base]) && empty($variantes)) {
            return null;
        }
        $entry = isset($messages[$base])
            ? self::entry($base, $messages[$base])
            : ['codigo' => $base, 'texto' => null, 'acoes' => null];
        $entry['variantes'] = $variantes;

        return $entry;
    }

    /**
     * Versão do catálogo (a das Mensagens do Sistema publicadas no gov.br).
     *
     * @return string
     */
    public static function version(): string
    {
        return self::catalog()['versao'];
    }

    private static function entry(string $codigo, array $message): array
    {
        return ['codigo' => $codigo, 'texto' => $message['texto'], 'acoes' => $message['acoes']];
    }

    private static function catalog(): array
    {
        if (self::$catalog === null) {
            $file = dirname(__DIR__, 2) . '/data/mensagens-sistema.json';
            self::$catalog = json_decode(file_get_contents($file), true);
        }

        return self::$catalog;
    }
}
```

- [x] **Step 4: rodar e confirmar que passam**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/SystemMessagesTest.php`
Esperado: PASSA (13 casos).

---

### Task 3: `check.py` — Mensagens e pacotes prontos em branch

**Arquivos:**
- Alterar: `.claude/skills/update-esocial/scripts/check.py` (`main`, funções novas)
- Alterar: `.claude/skills/update-esocial/scripts/test_check.py` (acrescentar classes)

**Depende de:** nada

**Contrato que esta task publica:** `check.msgs_is_news(published: list, applied) -> bool`;
`check.pending_branch(state: dict, package: str) -> dict | None`.

- [x] **Step 1: escrever os testes que falham**

Acrescentar em `test_check.py`, antes do `if __name__ == '__main__':`:

```python
class MensagensTest(unittest.TestCase):
    def test_versao_aplicada_igual_a_publicada_nao_e_novidade(self):
        published = ['2.5']

        news = check.msgs_is_news(published, '2.5')

        self.assertFalse(news)

    def test_nunca_revisada_e_novidade(self):
        published = ['2.5']

        news = check.msgs_is_news(published, None)

        self.assertTrue(news)

    def test_versao_maior_publicada_e_novidade(self):
        published = ['2.5', '2.6']

        news = check.msgs_is_news(published, '2.5')

        self.assertTrue(news)


class PendentesTest(unittest.TestCase):
    STATE = {'pending_packages': [
        {'xsd_package': '2026-11-23', 'branch': 'xsd/2026-11-23', 'merge_on': '2026-11-09'},
    ]}

    def test_pacote_pronto_em_branch(self):
        ready = check.pending_branch(self.STATE, '2026-11-23')

        self.assertEqual(ready, {'xsd_package': '2026-11-23', 'branch': 'xsd/2026-11-23', 'merge_on': '2026-11-09'})

    def test_pacote_sem_branch(self):
        ready = check.pending_branch(self.STATE, '2026-12-14')

        self.assertIsNone(ready)

    def test_estado_sem_a_lista(self):
        ready = check.pending_branch({}, '2026-11-23')

        self.assertIsNone(ready)
```

- [x] **Step 2: rodar e confirmar que falham**

Rode: `python3 -m unittest discover -s .claude/skills/update-esocial/scripts -p 'test_check.py' -v`
Esperado: os 6 novos dão ERRO `AttributeError: module 'check' has no attribute ...`; os 4
antigos `ok`.

- [x] **Step 3: implementar**

Em `check.py`, depois de `def comm_is_news(...)` (e do seu `return`), acrescentar:

```python
def msgs_is_news(published: list, applied) -> bool:
    """Mensagens do Sistema publicadas acima da versão revisada (None = nunca revisada)."""
    if not published:
        return False
    return applied is None or max(ver(v) for v in published) > ver(applied)


def pending_branch(state: dict, package: str):
    """Pacote de XSD já preparado num branch (pending_packages do ESOCIAL-VERSION.json) ou None."""
    return next((p for p in state.get('pending_packages', []) if p.get('xsd_package') == package), None)
```

No `main`, substituir:

```python
    print(f'XSD S-1.3     aplicado {applied} | novos: {", ".join(pending) or "nenhum"}')
    has_news |= bool(pending)
```

por:

```python
    print(f'XSD S-1.3     aplicado {applied} | novos: {", ".join(pending) or "nenhum"}')
    for package in pending:
        ready = pending_branch(state, package)
        if ready:
            print(f'              {package}: pronto no branch {ready["branch"]} (merge em {ready["merge_on"]})')
    has_news |= bool(pending)
```

Substituir:

```python
    has_news |= msgs != '?' and msgs != state.get('mensagens_sistema')
```

por:

```python
    has_news |= msgs_is_news(found['msgs'], state.get('system_messages'))
```

E substituir:

```python
    if pending:
        print(f'\nPróximo passo: aplicar XSD {pending[0]} ({found["xsd"][pending[0]]})')
```

por:

```python
    if pending:
        ready = pending_branch(state, pending[0])
        if ready:
            print(f'\nPróximo passo: fazer o merge de {ready["branch"]} no master em {ready["merge_on"]}')
        else:
            print(f'\nPróximo passo: aplicar XSD {pending[0]} ({found["xsd"][pending[0]]})')
```

- [x] **Step 4: rodar e confirmar que passam**

Rode: `python3 -m unittest discover -s .claude/skills/update-esocial/scripts -p 'test_check.py' -v`
Esperado: 10 testes `ok`.

---

### Task 4: `compare_xsd.py` — tipos nomeados

**Arquivos:**
- Criar: `.claude/skills/update-esocial/scripts/test_compare_xsd.py`
- Alterar: `.claude/skills/update-esocial/scripts/compare_xsd.py` (`elements.walk`, `compare`, função nova)

**Depende de:** nada

- [x] **Step 1: escrever os testes que falham**

Criar `.claude/skills/update-esocial/scripts/test_compare_xsd.py`:

```python
"""Testes do compare_xsd.py com XSD mínimos gravados numa pasta temporária."""
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import compare_xsd  # noqa: E402

TIPOS = '''<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
  <xs:simpleType name="TS_indOrgReemb">
    <xs:restriction base="xs:byte">{enums}</xs:restriction>
  </xs:simpleType>
  <xs:complexType name="T_a"><xs:sequence><xs:element name="tpAmb" type="{tpa}"/></xs:sequence></xs:complexType>
  <xs:complexType name="T_b"><xs:sequence><xs:element name="tpAmb" type="xs:byte"/></xs:sequence></xs:complexType>
</xs:schema>
'''


def write_tipos(folder: Path, enums: list, tpa: str = 'xs:byte') -> Path:
    path = folder / 'tipos.xsd'
    values = ''.join(f'<xs:enumeration value="{v}"/>' for v in enums)
    path.write_text(TIPOS.format(enums=values, tpa=tpa), encoding='utf-8')
    return path


class CompareTest(unittest.TestCase):
    def setUp(self):
        tmp = tempfile.TemporaryDirectory()
        self.addCleanup(tmp.cleanup)
        self.old = Path(tmp.name, 'old')
        self.new = Path(tmp.name, 'new')
        self.old.mkdir()
        self.new.mkdir()

    def test_valor_novo_em_tipo_nomeado_aparece(self):
        old, new = write_tipos(self.old, ['1', '2']), write_tipos(self.new, ['1', '2', '3'])

        lines = compare_xsd.compare(old, new)

        self.assertIn("  * TS_indOrgReemb: valores +['3'] -[]", lines)

    def test_mudanca_num_tipo_complexo_nao_some_por_outro_tipo_com_o_mesmo_elemento(self):
        old, new = write_tipos(self.old, ['1']), write_tipos(self.new, ['1'], tpa='xs:string')

        lines = compare_xsd.compare(old, new)

        self.assertIn('  * #T_a/tpAmb: tipo xs:byte -> xs:string', lines)

    def test_tipos_iguais_nao_tem_diferenca(self):
        old, new = write_tipos(self.old, ['1', '2']), write_tipos(self.new, ['1', '2'])

        lines = compare_xsd.compare(old, new)

        self.assertEqual(lines, [])


if __name__ == '__main__':
    unittest.main()
```

- [x] **Step 2: rodar e confirmar que falham**

Rode: `python3 -m unittest discover -s .claude/skills/update-esocial/scripts -p 'test_compare*.py' -v`
Esperado: os 2 primeiros FALHAM (linha ausente); `tipos_iguais` `ok`.

- [x] **Step 3: caminho com o nome do tipo nomeado**

Em `compare_xsd.py`, dentro de `walk`, substituir:

```python
        for child in node:
            p = prefix
            if child.tag == f'{XS}element' and (child.get('name') or child.get('ref')):
```

por:

```python
        for child in node:
            p = prefix
            if child.tag in (f'{XS}complexType', f'{XS}simpleType') and child.get('name'):
                p = f"#{child.get('name')}"
            if child.tag == f'{XS}element' and (child.get('name') or child.get('ref')):
```

- [x] **Step 4: comparar os `simpleType` nomeados**

Acrescentar depois da função `elements`:

```python
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
```

E, em `compare`, logo antes do `return lines`, acrescentar:

```python
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
```

- [x] **Step 5: rodar os testes e conferir contra o pacote (restrição 2)**

Rode: `python3 -m unittest discover -s .claude/skills/update-esocial/scripts -p 'test_compare*.py' -v`
Esperado: 3 `ok`.

Rode: `python3 .claude/skills/update-esocial/scripts/compare_xsd.py schemes/v_S_01_03_00 $P/2026-11-23/x | grep -A3 '^tipos.xsd'`
Esperado: `tipos.xsd:` seguido de `  * TS_indOrgReemb: valores +['3'] -[]` (antes saía "mudança só
em documentação").

---

### Task 5: `indPertIRRF` no S-1000

**Arquivos:**
- Alterar: `tests/Unit/Factories/EvtInfoEmpregadorTest.php`
- Alterar: `src/Factories/Traits/TraitS1000.php` (`toNodeS130`, bloco do `infoCadastro`, ~l.753-759)
- Alterar: `jsonSchemes/v_S_01_03_00/evtInfoEmpregador.schema` (~l.86-90)

**Depende de:** nada

**Contrato que esta task publica:** helper de teste `infoEmpregadorCadastro(array $extra, array $without = []): stdClass`.

- [x] **Step 1: escrever os testes que falham**

Acrescentar em `tests/Unit/Factories/EvtInfoEmpregadorTest.php`:

```php
function infoEmpregadorCadastro(array $extra, array $without = []): stdClass
{
    $cadastro = array_merge([
        'classtrib' => '01',
        'indcoop' => 0,
        'indconstr' => 0,
        'inddesfolha' => 0,
        'indporte' => 'S',
        'indoptregeletron' => 1,
    ], $extra);
    foreach ($without as $key) {
        unset($cadastro[$key]);
    }

    return json_decode(json_encode([
        'sequencial' => 1,
        'modo' => 'INC',
        'ideperiodo' => ['inivalid' => '2026-01'],
        'infocadastro' => $cadastro,
    ]));
}

it('emite indPertIRRF logo depois de indTribFolhaPisPasep', function () {
    $std = infoEmpregadorCadastro(['indtribfolhapispasep' => 'S', 'indpertirrf' => 'S']);
    $event = new EvtInfoEmpregador($this->configJson, $std, $this->certificate, '2026-01-05 10:00:00');

    $document = eventDocument($event);

    $names = childElementNames($document->getElementsByTagName('infoCadastro')->item(0));
    expect(array_slice($names, -2))->toBe(['indTribFolhaPisPasep', 'indPertIRRF'])
        ->and($document->getElementsByTagName('indPertIRRF')->item(0)->textContent)->toBe('S');
});

it('não emite indPertIRRF quando ele não é informado', function () {
    $std = infoEmpregadorCadastro([]);
    $event = new EvtInfoEmpregador($this->configJson, $std, $this->certificate, '2026-01-05 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('indPertIRRF')->length)->toBe(0);
});

it('recusa indPertIRRF diferente de S', function () {
    $std = infoEmpregadorCadastro(['indpertirrf' => 'N']);

    new EvtInfoEmpregador($this->configJson, $std, $this->certificate, '2026-01-05 10:00:00');
})->throws(Exception::class, 'infocadastro.indpertirrf');
```

- [x] **Step 2: rodar e confirmar que falham**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/Factories/EvtInfoEmpregadorTest.php`
Esperado: "emite indPertIRRF" FALHA (último é `indTribFolhaPisPasep`); "recusa" FALHA (não
lança); "não emite" PASSA.

- [x] **Step 3: montador**

Em `src/Factories/Traits/TraitS1000.php` (o trecho só existe no `toNodeS130`), substituir:

```php
                "indTribFolhaPisPasep",
                !empty($cad->indtribfolhapispasep) ? $cad->indtribfolhapispasep : null,
                false
            );
        }
```

por:

```php
                "indTribFolhaPisPasep",
                !empty($cad->indtribfolhapispasep) ? $cad->indtribfolhapispasep : null,
                false
            );
            $this->dom->addChild(
                $infoCadastro,
                "indPertIRRF",
                $cad->indpertirrf ?? null,
                false
            );
        }
```

- [x] **Step 4: JSON schema**

Em `jsonSchemes/v_S_01_03_00/evtInfoEmpregador.schema`, substituir:

```json
                "indtribfolhapispasep": {
                    "required": false,
                    "type": ["string","null"],
                    "pattern": "^(S)$"
                }
```

por:

```json
                "indtribfolhapispasep": {
                    "required": false,
                    "type": ["string","null"],
                    "pattern": "^(S)$"
                },
                "indpertirrf": {
                    "required": false,
                    "type": ["string","null"],
                    "pattern": "^(S)$"
                }
```

- [x] **Step 5: rodar e confirmar que passam**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/Factories/EvtInfoEmpregadorTest.php`
Esperado: 4 PASSAM.

---

### Task 6: Estado, documentação e verificação do master

**Arquivos:**
- Alterar: `ESOCIAL-VERSION.json` (`system_messages`, `pending_packages`, `updated_at`)
- Alterar: `.claude/skills/update-esocial/SKILL.md` (seção nova)
- Alterar: `AGENTS.md` ("Manter atualizado")
- Alterar: `CHANGELOG.md`

**Depende de:** Tasks 1-5

- [x] **Step 1: ESOCIAL-VERSION.json**

Rode (edição de texto, preserva o formato):

```bash
python3 - <<'EOF'
p = 'ESOCIAL-VERSION.json'; s = open(p, encoding='utf-8').read()
reps = [
 ('"upstream_reviewed": "Último commit',
  '"pending_packages": "Pacotes de XSD já preparados e testados num branch xsd/<data>, com a data de merge no master (abertura da Produção Restrita, ou a produção quando não há Restrita). O check.py mostra o branch pronto; no branch de cada pacote o item sai da lista.",\n    "upstream_reviewed": "Último commit'),
 ('  "system_messages": null,', '  "system_messages": "2.5",'),
 ('\n  "upstream_reviewed":',
  '\n  "pending_packages": [\n    {"xsd_package": "2026-11-23", "branch": "xsd/2026-11-23", "merge_on": "2026-11-09"},\n    {"xsd_package": "2026-12-14", "branch": "xsd/2026-12-14", "merge_on": "2026-12-07"},\n    {"xsd_package": "2027-01-18", "branch": "xsd/2027-01-18", "merge_on": "2027-01-18"}\n  ],\n  "upstream_reviewed":'),
]
for o, n in reps:
    assert s.count(o) == 1, o; s = s.replace(o, n, 1)
import re, json
s, k = re.subn(r'^  "updated_at": "[^"]*"', '  "updated_at": "2026-10-14"', s, flags=re.M); assert k == 1
json.loads(s); open(p, 'w', encoding='utf-8').write(s); print('ok')
EOF
```

(Se `updated_at` já for `2026-10-14`, a troca não muda nada; tudo bem.)

- [x] **Step 2: SKILL.md e AGENTS.md**

Acrescentar no fim de `.claude/skills/update-esocial/SKILL.md`:

```markdown

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
```

Em `AGENTS.md`, na seção "## Manter atualizado", logo depois do parágrafo que começa com "Os
pacotes de XSD ficam em", acrescentar:

```markdown

Pacotes preparados antes da data ficam em branches `xsd/<data>` (lista e data de merge em
`pending_packages` do `ESOCIAL-VERSION.json`). Catálogo das Mensagens do Sistema:
`data/mensagens-sistema.json`, lido por `Common\SystemMessages::get($codigo)`.
```

- [x] **Step 3: CHANGELOG**

Em `CHANGELOG.md`, logo abaixo de `## Unreleased`, inserir:

```markdown

### Added
- `Common\SystemMessages`: catálogo oficial das Mensagens do Sistema v2.5 (`data/mensagens-sistema.json`), com texto e ação sugerida por código (`get(191)`, `get('MS0191')`, variantes com sufixo).
- S-1000 (S-1.3): `indPertIRRF` opcional (`S`), já previsto no XSD e ausente no montador.
```

Se já houver um `### Added` logo abaixo de `## Unreleased`, acrescente só os dois itens nele.

- [x] **Step 4: verificação do master (restrição 1)**

Rode:

```bash
composer test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep 'Tests:'
python3 -m unittest discover -s .claude/skills/update-esocial/scripts -p 'test_*.py'
php -d xdebug.mode=off .claude/skills/update-esocial/scripts/validate.php | tail -1
python3 .claude/skills/update-esocial/scripts/check.py | head -6
rm -rf .claude/skills/update-esocial/scripts/__pycache__
```

Esperado: resumo do Pest sem `deprecated`/`failed`; `OK` no unittest (22 testes); `40/40`; o
`check.py` mostra `Mensagens     lida 2.5 | publicada 2.5` e, abaixo da linha do XSD, uma linha
`pronto no branch xsd/<data> (merge em …)` para cada um dos 3 pacotes (o `check.py` lê só o
`ESOCIAL-VERSION.json`; os branches nascem nas Tasks 7-9).

---

### Task 7: Branch `xsd/2026-11-23` (item 3.4)

**Arquivos:**
- Alterar (no branch): `schemes/v_S_01_03_00/*.xsd`, `src/Factories/Traits/TraitS1000.php`
  (`toNodeS130`), `jsonSchemes/v_S_01_03_00/evtInfoEmpregador.schema`,
  `jsonSchemes/v_S_01_03_00/evtPgtos.schema`, `examples/Fake/v_S_01_03_00/Fake_s1000_EvtInfoEmpregador.php`,
  `ESOCIAL-VERSION.json`, `CHANGELOG.md`
- Alterar: `tests/Unit/Factories/EvtInfoEmpregadorTest.php`
- Criar: `tests/Unit/Factories/EvtPgtosTest.php`

**Depende de:** Tasks 1-6 commitadas no `master`

- [ ] **Step 1: criar o branch e copiar o pacote**

```bash
git switch master && git status --short    # vazio
git switch -c xsd/2026-11-23
ls $P/2026-11-23/x/*.xsd | wc -l    # 52
cp $P/2026-11-23/x/*.xsd schemes/v_S_01_03_00/
python3 .claude/skills/update-esocial/scripts/compare_xsd.py schemes/v_S_01_03_00 $P/2026-11-23/x | tail -1    # 0 arquivo(s) diferente(s)
```

- [ ] **Step 2: escrever os testes que falham**

Acrescentar em `tests/Unit/Factories/EvtInfoEmpregadorTest.php`:

```php
it('aceita o S-1000 sem indOptRegEletron e não emite a tag', function () {
    $std = infoEmpregadorCadastro([], ['indoptregeletron']);
    $event = new EvtInfoEmpregador($this->configJson, $std, $this->certificate, '2026-01-05 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('indOptRegEletron')->length)->toBe(0);
});
```

Criar `tests/Unit/Factories/EvtPgtosTest.php`:

```php
<?php

use NFePHP\eSocial\Factories\EvtPgtos;

function pgtosComReembolso(string $indOrgReemb): stdClass
{
    return json_decode(json_encode([
        'sequencial' => 1,
        'indretif' => 1,
        'perapur' => '2026-10',
        'cpfbenef' => '12345678901',
        'infopgto' => [[
            'dtpgto' => '2026-10-05',
            'tppgto' => 1,
            'perref' => '2026-09',
            'idedmdev' => 'DM1',
            'vrliq' => 1000.0,
        ]],
        'infoircomplem' => [[
            'inforeembmed' => [[
                'indorgreemb' => $indOrgReemb,
                'cnpjoper' => '12345678000195',
                'detreembtit' => [['tpinsc' => '1', 'nrinsc' => '12345678000195', 'vlrreemb' => 100.0]],
            ]],
        ]],
    ]));
}

it('aceita indOrgReemb 3 (pacote 2026-11-23)', function () {
    $std = pgtosComReembolso('3');
    $event = new EvtPgtos($this->configJson, $std, $this->certificate, '2026-10-14 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('indOrgReemb')->item(0)->textContent)->toBe('3');
});

it('recusa indOrgReemb fora de 1, 2 e 3', function () {
    $std = pgtosComReembolso('4');

    new EvtPgtos($this->configJson, $std, $this->certificate, '2026-10-14 10:00:00');
})->throws(Exception::class, 'indorgreemb');
```

- [ ] **Step 3: rodar e confirmar que falham**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/Factories/EvtInfoEmpregadorTest.php tests/Unit/Factories/EvtPgtosTest.php`
Esperado: "sem indOptRegEletron" FALHA (`[infocadastro.indoptregeletron] ... obrigatório`);
"indOrgReemb 3" FALHA (pattern); "fora de 1, 2 e 3" PASSA.

- [ ] **Step 4: montador do S-1000 (só no `toNodeS130`)**

```bash
python3 - <<'EOF'
p = 'src/Factories/Traits/TraitS1000.php'; s = open(p, encoding='utf-8').read()
k = s.index('protected function toNodeS130()')
old = '''                "indOptRegEletron",
                $cad->indoptregeletron,
                true
            );'''
new = '''                "indOptRegEletron",
                $cad->indoptregeletron ?? null,
                false
            );'''
body = s[k:]; assert body.count(old) == 1
open(p, 'w', encoding='utf-8').write(s[:k] + body.replace(old, new)); print('ok')
EOF
```

- [ ] **Step 5: JSON schemas**

Em `jsonSchemes/v_S_01_03_00/evtInfoEmpregador.schema`, substituir:

```json
                "indoptregeletron": {
                    "required": true,
                    "type": "integer",
                    "minimum": 0,
                    "maximum": 1
                },
```

por:

```json
                "indoptregeletron": {
                    "required": false,
                    "type": ["integer","null"],
                    "minimum": 0,
                    "maximum": 1
                },
```

Em `jsonSchemes/v_S_01_03_00/evtPgtos.schema`, trocar a linha `"pattern": "^(1|2)$"` do bloco
`indorgreemb` (única com esse pattern dentro de `indorgreemb`; confira com
`grep -n -A4 '"indorgreemb"'`) por:

```json
                                    "pattern": "^(1|2|3)$"
```

- [ ] **Step 6: exemplo, ESOCIAL-VERSION e CHANGELOG**

No `examples/Fake/v_S_01_03_00/Fake_s1000_EvtInfoEmpregador.php`, acrescentar logo acima da linha
`$std->infocadastro->indoptregeletron = 0;`:

```php
//indoptregeletron: opcional a partir do pacote de 2026-11-23; não informar se iniValid >= 2026-02
```

```bash
python3 - <<'EOF'
import json, re
p = 'ESOCIAL-VERSION.json'; s = open(p, encoding='utf-8').read()
s, k = re.subn(r'^  "xsd_package": "[^"]*"', '  "xsd_package": "2026-11-23"', s, flags=re.M); assert k == 1
o = '    {"xsd_package": "2026-11-23", "branch": "xsd/2026-11-23", "merge_on": "2026-11-09"},\n'
assert s.count(o) == 1; s = s.replace(o, '')
json.loads(s); open(p, 'w', encoding='utf-8').write(s); print('ok')
EOF
```

Em `CHANGELOG.md`, no `### Added` de `## Unreleased`, acrescentar:

```markdown
- Apply the S-1.3 XSD package of 2026-11-23 (NT 07/2026 rev., item 3.4): S-1000 `indOptRegEletron` optional (do not send when `iniValid >= 2026-02`); S-1210 `indOrgReemb` accepts 3.
```

- [ ] **Step 7: verificar o branch (restrição 4)**

```bash
php -d xdebug.mode=off vendor/bin/pest tests/Unit/Factories/EvtInfoEmpregadorTest.php tests/Unit/Factories/EvtPgtosTest.php
composer test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep 'Tests:'
php -d xdebug.mode=off .claude/skills/update-esocial/scripts/validate.php | tail -1
grep '"xsd_package": "2026-11-23"' ESOCIAL-VERSION.json
```

Esperado: tudo verde, `40/40`, a linha do `xsd_package`. **Checkpoint**: commit no branch (com o
"sim") antes da Task 8.

---

### Task 8: Branch `xsd/2026-12-14` (item 3.5)

**Arquivos:**
- Alterar (no branch): `schemes/v_S_01_03_00/*.xsd`, `src/Factories/Traits/TraitS1000.php`,
  `jsonSchemes/v_S_01_03_00/evtInfoEmpregador.schema`, `ESOCIAL-VERSION.json`, `CHANGELOG.md`
- Alterar: `tests/Unit/Factories/EvtInfoEmpregadorTest.php`

**Depende de:** Task 7 commitada

- [ ] **Step 1: branch e pacote**

```bash
git switch xsd/2026-11-23 && git status --short    # vazio
git switch -c xsd/2026-12-14
cp $P/2026-12-14/x/*.xsd schemes/v_S_01_03_00/
python3 .claude/skills/update-esocial/scripts/compare_xsd.py schemes/v_S_01_03_00 $P/2026-12-14/x | tail -1    # 0 arquivo(s) diferente(s)
```

- [ ] **Step 2: escrever os testes que falham**

Acrescentar em `tests/Unit/Factories/EvtInfoEmpregadorTest.php`:

```php
it('emite indSiafi logo depois de indPertIRRF (pacote 2026-12-14)', function () {
    $std = infoEmpregadorCadastro(['indpertirrf' => 'S', 'indsiafi' => 'S']);
    $event = new EvtInfoEmpregador($this->configJson, $std, $this->certificate, '2026-01-05 10:00:00');

    $document = eventDocument($event);

    $names = childElementNames($document->getElementsByTagName('infoCadastro')->item(0));
    expect(array_slice($names, -2))->toBe(['indPertIRRF', 'indSiafi']);
});

it('não emite indSiafi quando ele não é informado', function () {
    $std = infoEmpregadorCadastro([]);
    $event = new EvtInfoEmpregador($this->configJson, $std, $this->certificate, '2026-01-05 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName('indSiafi')->length)->toBe(0);
});

it('recusa indSiafi diferente de S', function () {
    $std = infoEmpregadorCadastro(['indsiafi' => 'N']);

    new EvtInfoEmpregador($this->configJson, $std, $this->certificate, '2026-01-05 10:00:00');
})->throws(Exception::class, 'infocadastro.indsiafi');
```

- [ ] **Step 3: rodar e confirmar que falham**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/Factories/EvtInfoEmpregadorTest.php`
Esperado: "emite indSiafi" e "recusa indSiafi" FALHAM; "não emite" PASSA.

- [ ] **Step 4: montador e schema**

Em `src/Factories/Traits/TraitS1000.php`, substituir:

```php
                "indPertIRRF",
                $cad->indpertirrf ?? null,
                false
            );
```

por:

```php
                "indPertIRRF",
                $cad->indpertirrf ?? null,
                false
            );
            $this->dom->addChild(
                $infoCadastro,
                "indSiafi",
                $cad->indsiafi ?? null,
                false
            );
```

Em `jsonSchemes/v_S_01_03_00/evtInfoEmpregador.schema`, substituir:

```json
                "indpertirrf": {
                    "required": false,
                    "type": ["string","null"],
                    "pattern": "^(S)$"
                }
```

por:

```json
                "indpertirrf": {
                    "required": false,
                    "type": ["string","null"],
                    "pattern": "^(S)$"
                },
                "indsiafi": {
                    "required": false,
                    "type": ["string","null"],
                    "pattern": "^(S)$"
                }
```

- [ ] **Step 5: ESOCIAL-VERSION e CHANGELOG**

```bash
python3 - <<'EOF'
import json, re
p = 'ESOCIAL-VERSION.json'; s = open(p, encoding='utf-8').read()
s, k = re.subn(r'^  "xsd_package": "[^"]*"', '  "xsd_package": "2026-12-14"', s, flags=re.M); assert k == 1
o = '    {"xsd_package": "2026-12-14", "branch": "xsd/2026-12-14", "merge_on": "2026-12-07"},\n'
assert s.count(o) == 1; s = s.replace(o, '')
json.loads(s); open(p, 'w', encoding='utf-8').write(s); print('ok')
EOF
```

Em `CHANGELOG.md`, no `### Added` de `## Unreleased`, acrescentar:

```markdown
- Apply the S-1.3 XSD package of 2026-12-14 (NT 07/2026 rev., item 3.5): S-1000 optional `indSiafi` (`S`).
```

- [ ] **Step 6: verificar o branch (restrição 4)**

```bash
composer test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep 'Tests:'
php -d xdebug.mode=off .claude/skills/update-esocial/scripts/validate.php | tail -1
grep '"xsd_package": "2026-12-14"' ESOCIAL-VERSION.json
git log --oneline master..xsd/2026-12-14 | head    # inclui o commit do xsd/2026-11-23
```

Esperado: verde, `40/40`, a linha, e o commit do branch anterior na lista. **Checkpoint**: commit.

---

### Task 9: Branch `xsd/2027-01-18` (item 3.6)

**Arquivos:**
- Alterar (no branch): `schemes/v_S_01_03_00/*.xsd` (só muda o `evtAnotJud.xsd`)
- Alterar: `ESOCIAL-VERSION.json`
- Alterar: `CHANGELOG.md`

**Depende de:** Task 8 commitada

- [ ] **Step 1: branch e pacote**

```bash
git switch xsd/2026-12-14 && git status --short    # vazio
git switch -c xsd/2027-01-18
cp $P/2027-01-18/x/*.xsd schemes/v_S_01_03_00/
git status --short schemes    # só evtAnotJud.xsd
```

- [ ] **Step 2: ESOCIAL-VERSION e CHANGELOG**

```bash
python3 - <<'EOF'
import json, re
p = 'ESOCIAL-VERSION.json'; s = open(p, encoding='utf-8').read()
s, k = re.subn(r'^  "xsd_package": "[^"]*"', '  "xsd_package": "2027-01-18"', s, flags=re.M); assert k == 1
s, k = re.subn(r'  "pending_packages": \[\n    \{"xsd_package": "2027-01-18"[^\n]*\n  \],', '  "pending_packages": [],', s); assert k == 1
json.loads(s); open(p, 'w', encoding='utf-8').write(s); print('ok')
EOF
```

Em `CHANGELOG.md`, no `### Added` de `## Unreleased`, acrescentar:

```markdown
- Apply the S-1.3 XSD package of 2027-01-18 (NT 07/2026 rev., item 3.6): S-8200 gets `observacoes` (the library does not build S-8200; only the XSD changes).
```

- [ ] **Step 3: verificar o branch (restrição 4)**

```bash
composer test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep 'Tests:'
php -d xdebug.mode=off .claude/skills/update-esocial/scripts/validate.php | tail -1
grep '"xsd_package": "2027-01-18"' ESOCIAL-VERSION.json
```

Esperado: verde, `40/40`, a linha. **Checkpoint**: commit; depois `git switch master`.
Push dos três branches só com o "sim" do usuário (`git push origin xsd/2026-11-23 xsd/2026-12-14 xsd/2027-01-18`).

---

## Ajustes durante a execução

- **2026-10-14 (planejamento)** — o parser foi prototipado contra o PDF real antes do plano. Dois
  casos que o spec não previa: "Ação Sugerida:" no meio da **primeira** linha da entrada, logo
  após o texto (MS0923, MS1111, MS1257, MS1755, MS1898, MS1937) — tratado como marcador; e o
  marcador repetido no próprio PDF ("Ação Sugerida: Ação Sugerida: …", MS0611) — a repetição é
  removida. O marcador só é reconhecido com os dois-pontos logo depois de "Sugerida(s)", o que
  deixa "Ação sugerida para evitar o erro:" (MS1089-MS1092) no texto.
- **2026-10-14 (planejamento)** — `get(266)` devolve `codigo = 'MS0266'` com `texto`/`acoes`
  `null` e as variantes (decisão do usuário: base + variantes).
- **2026-10-14 (Task 2)** — o catálogo v2.5 não tem número só com variantes (o MS0266 tem base e
  3 variantes). O teste passou a checar base + variantes do MS0266; o caso "só variantes"
  (`texto`/`acoes` null) fica no código para catálogos futuros, sem dado real para testar.
- **2026-10-14 (juiz do batch 1)** — a virada de página virava linha em branco e partia em duas
  uma ação em parágrafo que atravessa a página (12 mensagens: MS0473, MS0541, MS0609, MS0614,
  MS1290…). Corrigido no `page_lines` com teste novo (MS0009 do fixture); JSON regerado, as 12
  conferidas no PDF. `SystemMessages` lança `RuntimeException` se o JSON faltar ou for inválido.
  SKILL.md (Task 6) avisa para trocar a versão esperada no `SystemMessagesTest`.
- **2026-10-14 (Task 6)** — o `CHANGELOG.md` é em inglês e já tinha `### Added` no Unreleased: os
  itens entraram nele, em inglês (Tasks 7-9 também).
