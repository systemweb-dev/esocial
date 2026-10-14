# Plano de implementação — SP2 limpeza da API e compatibilidade

[spec.md](spec.md)

> **Execução:** implementar task por task. Os steps usam checkbox (`- [ ]`) para acompanhar.
> Os dois modos de execução estão na seção "Execution Handoff" da skill `sw-plan`.

**Objetivo:** a lib expõe só o que funciona no eSocial (S-1.3), responde com motivo para o
resto, recusa leiautes encerrados, roda sem deprecations no PHP 8.4 e perde o código morto.

**Arquitetura:** `Event::$unavailable` (lista código → nome + tipo) consultada antes de tudo no
`__callStatic`; recusa de leiaute no construtor do `Factory`; troca mecânica de tipos anuláveis;
remoções conferidas por grep.

**Stack:** PHP 8.4 (dev), Pest 5.3 + PHPUnit 13.4, Python 3 para os scripts de remoção.

**Restrições verificáveis (do spec):**
| Restrição | Como o plano checa |
|---|---|
| Resumo do Pest sem `deprecated`/`failed`; nenhum `Certificate $certificate = null`/`LoggerInterface $logger = null` em `src` | Task 3, steps 3-4; Task 6, step 2 |
| grep do código removido vazio | Task 6, step 3 |
| `validate.php` → 40/40 | Task 6, step 4 |
| Só `v_S_01_03_00` (mais `comunicacao` e `definitions.schema`) | Task 2, step 6; Task 6, step 5 |

**Convenções para quem executa:**
- Raiz `/var/www/esocial`. Suíte: `composer test`. Arquivo: `php -d xdebug.mode=off vendor/bin/pest <arquivo>`.
- Testes em Pest, AAA estrito (um Act, exceção com mensagem fixa em `->throws()`; mensagem que
  depende do dataset é capturada pelo helper `caughtException()` no Act e conferida no Assert).
- Comandos com `$` em grep usam **aspas simples**; não encadeie com `&&` um `grep -c` que pode dar
  0 (sai com código 1 e interrompe a cadeia).
- Não commitar em task.

**Ordem pelo risco:** a Task 1 remove 22 fábricas e 13 traits — se algum `toNodeS130` que fica
dependesse de algo removido, a suíte (`BuildersTest`, 40 exemplos) acusa já nela.

---

### Task 1: Eventos indisponíveis, alias s3500 e códigos da exceção

**Arquivos:**
- Alterar: `src/Event.php` (`$available`, `$aliases`, novo `$unavailable`, `__callStatic`)
- Alterar: `src/Exception/EventsException.php`
- Alterar: `tests/Pest.php` (helper `caughtException`)
- Criar: `tests/Unit/EventIndisponivelTest.php`
- Alterar: `tests/Unit/EventTest.php` (acrescenta testes)
- Remover: 22 fábricas, 13 traits, `examples/Fake/v_S_01_03_00/FaketestEnvia_1.php`

**Depende de:** nada

**Contrato que esta task publica:** `Event::$unavailable` = `['S-5001' => ['evtBasesTrab', 'retorno'], ...]`
(tipos `retorno` | `extinto` | `judiciario`); `caughtException(callable $act): ?Throwable`.

- [x] **Step 1: helper de exceção capturada**

Acrescentar no fim de `tests/Pest.php`:

```php
/**
 * Executa o Act e devolve a exceção lançada (ou null). Para quando a mensagem esperada depende
 * do dataset e não cabe em ->throws().
 */
function caughtException(callable $act): ?Throwable
{
    try {
        $act();
    } catch (Throwable $e) {
        return $e;
    }

    return null;
}
```

- [x] **Step 2: escrever os testes que falham**

Criar `tests/Unit/EventIndisponivelTest.php`:

```php
<?php

use NFePHP\eSocial\Event;
use NFePHP\eSocial\Exception\EventsException;

/*
 * Eventos que a lib não monta: retornos do eSocial (S-5xxx), extintos no S-1.x e os do
 * Judiciário. Chamar pelo nome ou pelo código responde com o motivo, e não com erro fatal.
 */

dataset('eventos indisponíveis', function () {
    foreach (Event::$unavailable as $code => $info) {
        list($name, $kind) = $info;
        $digits = 's' . str_replace('S-', '', $code);
        foreach (['nome' => $name, 'código' => $code, 'código sem hífen' => $digits] as $how => $call) {
            yield "$code por $how" => [$call, $code, $name, $kind];
        }
    }
});

it('responde com o motivo quando o evento não é montado pela biblioteca', function (string $call, string $code, string $name, string $kind) {
    $std = new stdClass();
    $reasons = [
        'retorno' => 'é um evento de retorno do eSocial: o empregador não envia; leia o totalizador no retorno do processamento do lote.',
        'extinto' => 'foi extinto e não existe no leiaute S-1.3.',
        'judiciario' => 'é enviado só pelo Poder Judiciário (procEmi 8): o empregador não envia.',
    ];

    $error = caughtException(function () use ($call, $std) {
        Event::$call('', $std);
    });

    expect($error)->toBeInstanceOf(EventsException::class)
        ->and($error->getMessage())->toBe("$code ($name) {$reasons[$kind]}")
        ->and($error->getCode())->toBe(1003);
})->with('eventos indisponíveis');

it('lista os 24 eventos que a biblioteca não monta', function () {
    $codes = array_keys(Event::$unavailable);

    sort($codes);

    expect($codes)->toBe([
        'S-1030', 'S-1035', 'S-1040', 'S-1050', 'S-1060', 'S-1065', 'S-1080', 'S-1250',
        'S-1295', 'S-1300', 'S-2241', 'S-2245', 'S-2250', 'S-2260', 'S-5001', 'S-5002',
        'S-5003', 'S-5011', 'S-5012', 'S-5013', 'S-5501', 'S-5503', 'S-8200', 'S-8299',
    ]);
});
```

Acrescentar em `tests/Unit/EventTest.php`:

```php
it('monta o S-3500 pelo código', function () {
    $std = json_decode(json_encode([
        'sequencial' => 1,
        'infoexclusao' => ['tpevento' => 'S-2500', 'nrrecevt' => '1.9.1234567890123456789'],
        'ideproctrab' => ['nrproctrab' => '12345678901234567890', 'cpftrab' => '12345678901', 'perapurpgto' => '2023-10'],
    ]));

    $event = Event::s3500($this->configJson, $std, $this->certificate, '2023-11-01 10:00:00');

    expect($event)->toBeInstanceOf(\NFePHP\eSocial\Factories\EvtExcProcTrab::class);
});

it('aponta cada alias para um evento que existe', function () {
    $targets = array_values(Event::$aliases);

    $missing = array_diff($targets, array_keys(Event::$available));

    expect($missing)->toBe([]);
});

it('não oferece nome nem código de evento indisponível', function () {
    $unavailableNames = array_map(function ($info) {
        return strtolower($info[0]);
    }, Event::$unavailable);
    $unavailableCodes = array_map(function ($code) {
        return 's' . str_replace('S-', '', $code);
    }, array_keys(Event::$unavailable));

    $clash = array_merge(
        array_intersect($unavailableNames, array_keys(Event::$available)),
        array_intersect($unavailableCodes, array_keys(Event::$aliases))
    );

    expect($clash)->toBe([]);
});

it('tem classe e XSD do S-1.3 para cada evento que monta', function () {
    $schemes = dirname(__DIR__, 2) . '/schemes/v_S_01_03_00/';

    $missing = array_filter(Event::$available, function ($class) use ($schemes) {
        $name = lcfirst(substr(strrchr($class, '\\'), 1));
        return !class_exists($class) || !is_file($schemes . $name . '.xsd');
    });

    expect($missing)->toBe([]);
});

it('informa o código da lista na exceção', function (callable $call, int $code) {
    $error = caughtException($call);

    expect($error->getCode())->toBe($code);
})->with([
    'evento desconhecido' => [function () { Event::evtInexistente('{}', new stdClass()); }, 1000],
    'sem config' => [function () { Event::s1000('', new stdClass()); }, 1001],
    'sem dados' => [function () { Event::s1000('{}', null); }, 1002],
]);
```

(Payload do S-3500 conferido no planejamento: monta e valida no XSD.)

Nota sobre o "tem XSD": o nome do XSD é a classe sem `Evt` com inicial minúscula
(`EvtExcProcTrab` → `evtExcProcTrab.xsd`); `EvtCAT` → `evtCAT.xsd`, `EvtCS` não fica. Se alguma
classe que fica tiver nome de XSD que não siga essa regra, o teste acusa — investigue antes de
mudar o teste.

- [x] **Step 3: rodar e confirmar que falham**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/EventIndisponivelTest.php tests/Unit/EventTest.php`
Esperado: `EventIndisponivelTest` FALHA (dataset vazio ou `Event::$unavailable` indefinido);
"monta o S-3500" FALHA (erro 1000); "tem XSD" FALHA (as 14 extintas não têm XSD; S-50xx e S-8200/S-8299 têm); "código da lista" FALHA
(`getCode()` = 0).

- [x] **Step 4: EventsException**

Em `src/Exception/EventsException.php`, substituir:

```php
        1003 => "",
        1004 => "",
        1005 => "",
    ];

    public static function wrongArgument($code, $msg = '')
    {
        $msg = self::replaceMsg(self::$list[$code], $msg);

        return new static($msg);
    }
```

por:

```php
        1003 => "{{msg}}",
    ];

    public static function wrongArgument($code, $msg = '')
    {
        $msg = self::replaceMsg(self::$list[$code], $msg);

        return new static($msg, $code);
    }
```

- [x] **Step 5: tirar os 22 de `$available`/`$aliases`, acrescentar `s3500` e `$unavailable`**

Rode (remove as linhas pelos nomes/códigos):

```bash
python3 - <<'EOF'
import re
p = 'src/Event.php'; s = open(p, encoding='utf-8').read()
names = ['evttabcargo', 'evttabcarreira', 'evttabfuncao', 'evttabhortur', 'evttabambiente',
         'evttabequipamento', 'evttaboperport', 'evtaqprod', 'evttotconting', 'evtcontrsindpatr',
         'evtinsapo', 'evttreicap', 'evtavprevio', 'evtconvinterm', 'evtbasestrab', 'evtirrfbenef',
         'evtbasesfgts', 'evtcs', 'evtirrf', 'evtfgts', 'evtanotjud', 'evtbaixa']
removed = 0
for n in names:
    s, k = re.subn(r"\n        '%s' => Factories\\\w+::class,(?=\n)" % n, '', s); removed += k
    s, k = re.subn(r"\n        '[s]\d{4}' => '%s',(?=\n)" % n, '', s); removed += k
print('linhas removidas:', removed)   # esperado: 44
open(p, 'w', encoding='utf-8').write(s)
EOF
```

Se o último item de algum array perder a vírgula final (ex.: `'s8299' => 'evtbaixa'` era o
último de `$aliases`), confira com `php -l src/Event.php` e ajuste à mão.

Em `$aliases`, logo depois de `'s3000' => 'evtexclusao',`, acrescentar:

```php
        's3500' => 'evtexcproctrab',
```

Antes de `public static $codes = [`, acrescentar:

```php
    /**
     * Eventos que a biblioteca não monta: código => [nome do evento no XSD, tipo].
     * tipo: retorno (o eSocial devolve ao empregador), extinto (não existe no S-1.3),
     * judiciario (enviado só pelo Poder Judiciário, procEmi 8).
     *
     * @var array
     */
    public static $unavailable = [
        'S-1030' => ['evtTabCargo', 'extinto'],
        'S-1035' => ['evtTabCarreira', 'extinto'],
        'S-1040' => ['evtTabFuncao', 'extinto'],
        'S-1050' => ['evtTabHorTur', 'extinto'],
        'S-1060' => ['evtTabAmbiente', 'extinto'],
        'S-1065' => ['evtTabEquipamento', 'extinto'],
        'S-1080' => ['evtTabOperPort', 'extinto'],
        'S-1250' => ['evtAqProd', 'extinto'],
        'S-1295' => ['evtTotConting', 'extinto'],
        'S-1300' => ['evtContrSindPatr', 'extinto'],
        'S-2241' => ['evtInsApo', 'extinto'],
        'S-2245' => ['evtTreiCap', 'extinto'],
        'S-2250' => ['evtAvPrevio', 'extinto'],
        'S-2260' => ['evtConvInterm', 'extinto'],
        'S-5001' => ['evtBasesTrab', 'retorno'],
        'S-5002' => ['evtIrrfBenef', 'retorno'],
        'S-5003' => ['evtBasesFGTS', 'retorno'],
        'S-5011' => ['evtCS', 'retorno'],
        'S-5012' => ['evtIrrf', 'retorno'],
        'S-5013' => ['evtFGTS', 'retorno'],
        'S-5501' => ['evtTribProcTrab', 'retorno'],
        'S-5503' => ['evtFGTSProcTrab', 'retorno'],
        'S-8200' => ['evtAnotJud', 'judiciario'],
        'S-8299' => ['evtBaixa', 'judiciario'],
    ];

    /**
     * @var array tipo => texto do motivo
     */
    protected static $unavailableReasons = [
        'retorno' => 'é um evento de retorno do eSocial: o empregador não envia; '
            . 'leia o totalizador no retorno do processamento do lote.',
        'extinto' => 'foi extinto e não existe no leiaute S-1.3.',
        'judiciario' => 'é enviado só pelo Poder Judiciário (procEmi 8): o empregador não envia.',
    ];
```

- [x] **Step 6: `__callStatic` consulta os indisponíveis primeiro**

Em `src/Event.php`, no `__callStatic`, substituir:

```php
        $name = str_replace('-', '', strtolower($name));
        $realname = $name;
```

por:

```php
        $name = str_replace('-', '', strtolower($name));
        foreach (self::$unavailable as $code => $info) {
            list($evtName, $kind) = $info;
            if ($name === strtolower($evtName) || $name === 's' . str_replace('S-', '', $code)) {
                throw EventsException::wrongArgument(
                    1003,
                    "$code ($evtName) " . self::$unavailableReasons[$kind]
                );
            }
        }
        $realname = $name;
```

- [x] **Step 7: remover fábricas, traits e o exemplo**

```bash
git rm -q src/Factories/{EvtTabCargo,EvtTabCarreira,EvtTabFuncao,EvtTabHorTur,EvtTabAmbiente,EvtTabEquipamento,EvtTabOperPort,EvtAqProd,EvtTotConting,EvtContrSindPatr,EvtInsApo,EvtTreiCap,EvtAvPrevio,EvtConvInterm,EvtBasesTrab,EvtIrrfBenef,EvtBasesFGTS,EvtCS,EvtIrrf,EvtFGTS,EvtAnotJud,EvtBaixa}.php
git rm -q src/Factories/Traits/TraitS{1030,1035,1040,1050,1060,1080,1250,1295,1300,2245,2250,2260,8200}.php
git rm -q examples/Fake/v_S_01_03_00/FaketestEnvia_1.php
ls src/Factories/Evt*.php | wc -l    # esperado: 40
```

- [x] **Step 8: rodar e confirmar que passam**

Rode: `php -l src/Event.php && composer test`
Esperado: tudo verde (os 72 casos do dataset de indisponíveis, os testes novos do `EventTest`,
o `BuildersTest` com os exemplos do S-1.2 e do S-1.3).

---

### Task 2: Só o leiaute S-1.3

**Arquivos:**
- Alterar: `src/Common/Factory.php` (construtor + método novo)
- Alterar: `tests/Unit/FactoryTest.php` (acrescenta testes)
- Alterar: `tests/Unit/BuildersTest.php` (dataset e comentário)
- Remover: pastas `v_S_01_00_00`, `v_S_01_01_00`, `v_S_01_02_00` em `schemes/`, `jsonSchemes/`,
  `examples/Fake/`, `examples/schemes/`; `jsonSchemes/v_S_01_03_00/evtTotConting.schema` e
  `jsonSchemes/v_S_01_03_00/evtCdBenPrRP.schema`

**Depende de:** Task 1

- [x] **Step 1: escrever os testes que falham**

Acrescentar em `tests/Unit/FactoryTest.php`, dentro do `describe('validação da entrada', ...)`:

```php
    it('recusa leiaute encerrado no eSocial', function (string $layout) {
        $config = json_decode($this->configJson, true);
        $config['eventoVersion'] = $layout;
        $std = toStdClass(infoEmpregadorData());

        new EvtInfoEmpregador(json_encode($config), $std, $this->certificate);
    })->with(['S.1.0.0', 'S.1.1.0', 'S.1.2.0', 'S.1.2'])
        ->throws(InvalidArgumentException::class, 'encerrado no eSocial: use S.1.3.0.');

    it('não chama de encerrado o S-1.3 escrito sem o último número', function () {
        $config = json_decode($this->configJson, true);
        $config['eventoVersion'] = 'S.1.3';
        $std = toStdClass(infoEmpregadorData());

        new EvtInfoEmpregador(json_encode($config), $std, $this->certificate);
    })->throws(InvalidArgumentException::class, 'Schemas não localizados');
```

- [x] **Step 2: rodar e confirmar que falham**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/FactoryTest.php`
Esperado: os 4 casos de "encerrado" FALHAM (S.1.0.0/S.1.1.0/S.1.2.0 montam; S.1.2 dá
"Schemas não localizados"); o do S.1.3 já PASSA.

- [x] **Step 3: recusa no construtor**

Em `src/Common/Factory.php`, no construtor, substituir:

```php
        $this->layout = $stdConf->eventoVersion;
```

por:

```php
        $this->layout = $stdConf->eventoVersion;
        $this->refuseClosedLayout($this->layout);
```

E acrescentar o método logo antes do docblock `/**` que abre com `* Stringfy layout number` (acima de `protected function strLayoutVer(`):

```php
    /**
     * O eSocial só aceita o leiaute S-1.3 (S-1.2 encerrado em 02/02/2025).
     * Compara maior.menor do eventoVersion (S.1.2.0 → 1.2) com 1.3.
     *
     * @param string $layout
     * @throws \InvalidArgumentException
     */
    protected function refuseClosedLayout($layout)
    {
        if (preg_match('/(\d+)\.(\d+)/', (string) $layout, $match)
            && version_compare($match[1] . '.' . $match[2], '1.3', '<')
        ) {
            throw new \InvalidArgumentException(
                "Leiaute $layout encerrado no eSocial: use S.1.3.0."
            );
        }
    }

```

Confira que o docblock de `strLayoutVer` continua logo acima dele (o método novo entra antes do
docblock, não entre o docblock e o método).

- [x] **Step 4: BuildersTest só com o S-1.3**

Em `tests/Unit/BuildersTest.php`, substituir:

```php
    foreach (['S-1.2' => 'v_S_01_02_00', 'S-1.3' => 'v_S_01_03_00'] as $label => $layout) {
```

por:

```php
    foreach (['S-1.3' => 'v_S_01_03_00'] as $label => $layout) {
```

E, no comentário do topo, substituir:

```php
 * Os exemplos leem o certificado e o bootstrap por caminho relativo. Todos rodam a partir da pasta
 * do S-1.3: o expired_certificate.pfx das pastas antigas usa criptografia legada que o OpenSSL 3
 * não abre, e a profundidade das pastas é a mesma, então ../../../bootstrap.php continua valendo.
```

por:

```php
 * Os exemplos leem o certificado e o bootstrap por caminho relativo, a partir da pasta do S-1.3
 * (o único leiaute aceito pelo eSocial e pela biblioteca).
```

- [x] **Step 5: remover pastas e schemas órfãos**

```bash
for d in schemes jsonSchemes examples/Fake examples/schemes; do
  git rm -rq $d/v_S_01_00_00 $d/v_S_01_01_00 $d/v_S_01_02_00
done
git rm -q jsonSchemes/v_S_01_03_00/evtTotConting.schema jsonSchemes/v_S_01_03_00/evtCdBenPrRP.schema
```

- [x] **Step 6: conferir as pastas**

Rode: `ls schemes jsonSchemes examples/Fake examples/schemes`
Esperado: `schemes`: `comunicacao  v_S_01_03_00`; `jsonSchemes`: `definitions.schema  v_S_01_03_00`;
`examples/Fake` e `examples/schemes`: `v_S_01_03_00`.

- [x] **Step 7: rodar a suíte**

Rode: `composer test`
Esperado: verde; `BuildersTest` com 40 casos; os casos novos do `FactoryTest` passam.

---

### Task 3: Tipos anuláveis explícitos e PHP >= 8.1

**Arquivos:**
- Alterar: `src/Factories/Evt*.php` (40), `src/Common/Factory.php`, `src/Common/Soap/SoapBase.php`,
  `src/Common/Soap/SoapCurl.php`, `src/Common/Soap/SoapFake.php`
- Alterar: `composer.json` (`require.php`)

**Depende de:** Task 1 (as fábricas removidas já saíram)

- [x] **Step 1: medir antes**

Rode: `composer test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep 'Tests:'`
Esperado: `Tests:    N deprecated, …` com N > 0.

- [x] **Step 2: trocar os tipos**

```bash
grep -rlE 'Certificate \$certificate = null|LoggerInterface \$logger = null' src \
  | xargs sed -i -E 's/([^?])Certificate \$certificate = null/\1?Certificate $certificate = null/g; s/([^?])LoggerInterface \$logger = null/\1?LoggerInterface $logger = null/g'
```

- [x] **Step 3: conferir que não sobrou nenhum**

Rode: `grep -rnE '[^?]Certificate \$certificate = null|[^?]LoggerInterface \$logger = null' src`
Esperado: vazio. Rode também `for f in $(git diff --name-only src); do php -l $f | grep -v '^No syntax'; done` → vazio.

- [x] **Step 4: suíte sem deprecations**

Rode: `composer test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep 'Tests:'`
Esperado: `Tests:    M passed (...)` **sem** a palavra `deprecated` e sem `failed`.

- [x] **Step 5: PHP mínimo**

Em `composer.json`, trocar `"php" : ">=7.0",` por:

```json
        "php" : ">=8.1",
```

Rode: `composer validate --no-check-publish --no-check-lock; echo "exit=$?"`
Esperado: `./composer.json is valid` (pode vir "but your composer.lock has some warnings": o lock
é local e está no `.gitignore`) e `exit=0`.

---

### Task 4: EVT_TABELAS e faixa do sequencial

**Arquivos:**
- Alterar: `src/Common/Tools.php` (constantes)
- Alterar: `src/Common/FactoryId.php` (`build`)
- Alterar: `tests/Unit/FactoryIdTest.php`, `tests/Unit/GruposTest.php`, `tests/Unit/ToolsTest.php`,
  `examples/Fake/v_S_01_03_00/FaketestEnvia.php`, `examples/Fake/v_S_01_03_00/FaketestEnviaLoteXml.php`

**Depende de:** nada

- [x] **Step 1: escrever os testes que falham**

Acrescentar em `tests/Unit/FactoryIdTest.php`:

```php
it('recusa sequencial fora de 1 a 99999', function ($sequential) {
    $date = new DateTime('2017-08-03 10:37:00');

    FactoryId::build(1, '99999999', $date, $sequential);
})->with([100000, -1, 'abc'])->throws(InvalidArgumentException::class, 'use um inteiro de 1 a 99999');

it('aceita o sequencial 99999', function () {
    $date = new DateTime('2017-08-03 10:37:00');

    $id = FactoryId::build(1, '99999999', $date, 99999);

    expect(substr($id, -5))->toBe('99999');
});
```

Acrescentar em `tests/Unit/GruposTest.php`:

```php
it('chama o grupo 1 de eventos de tabelas', function () {
    $group = Tools::EVT_TABELAS;

    expect($group)->toBe(1)->and(Tools::EVT_INICIAIS)->toBe($group);
});
```

- [x] **Step 2: rodar e confirmar que falham**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/FactoryIdTest.php tests/Unit/GruposTest.php`
Esperado: os 3 casos de "fora de 1 a 99999" FALHAM (não lança); "grupo 1" FALHA (constante
indefinida); "99999" PASSA.

- [x] **Step 3: constante**

Em `src/Common/Tools.php`, substituir:

```php
    const EVT_INICIAIS = 1;
```

por:

```php
    const EVT_TABELAS = 1;

    /**
     * @deprecated use EVT_TABELAS (nome oficial do grupo 1)
     */
    const EVT_INICIAIS = 1;
```

E trocar o uso nos testes:

```bash
sed -i 's/EVT_INICIAIS/EVT_TABELAS/g' tests/Unit/ToolsTest.php examples/Fake/v_S_01_03_00/Faketest*.php
grep -rn 'EVT_INICIAIS' tests examples    # vazio (código 1: normal)
```

- [x] **Step 4: faixa do sequencial**

Em `src/Common/FactoryId.php`, substituir:

```php
        $sequential = empty($sequential) ? random_int(1, 99999) : (int) $sequential;
```

por:

```php
        if (empty($sequential)) {
            $sequential = random_int(1, 99999);
        } elseif (!ctype_digit((string) $sequential) || (int) $sequential > 99999) {
            throw new \InvalidArgumentException(
                "Sequencial inválido [$sequential]: use um inteiro de 1 a 99999."
            );
        }
        $sequential = (int) $sequential;
```

- [x] **Step 5: rodar e confirmar que passam**

Rode: `php -d xdebug.mode=off vendor/bin/pest tests/Unit/FactoryIdTest.php tests/Unit/GruposTest.php tests/Unit/ToolsTest.php`
Esperado: tudo PASSA.

---

### Task 5: Código morto e `console/`

**Arquivos:**
- Remover: `src/Common/ParamsStandardize.php`, `src/Common/Sanitize.php`,
  `src/Common/Soap/SoapCode.php`, `src/Common/Soap/httpcodes.json`, `console/`
- Alterar: `src/Common/Factory.php` (`standardizeProperties` e comentários), `src/Common/Soap/SoapBase.php`,
  `src/Common/Soap/SoapCurl.php` (`getFaultString`), `tests/Unit/SoapCurlTest.php`

**Depende de:** Task 3 (mesmos arquivos do `Soap`)

- [x] **Step 1: SoapCurlTest sem `console/`**

Em `tests/Unit/SoapCurlTest.php`, substituir o teste "usa o bundle de CAs informado no lugar do
store do sistema" por:

```php
it('usa o bundle de CAs informado no lugar do store do sistema', function () {
    $soap = new SoapCurl($this->certificate);
    $bundle = tempnam(sys_get_temp_dir(), 'ca');
    $soap->loadCA($bundle);

    $options = $soap->securityOptions();

    unlink($bundle);
    expect($options)->toBe([
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CAINFO => $bundle,
    ]);
});
```

- [x] **Step 2: remover arquivos**

```bash
git rm -q src/Common/ParamsStandardize.php src/Common/Sanitize.php src/Common/Soap/SoapCode.php src/Common/Soap/httpcodes.json
git rm -rq console
```

- [x] **Step 3: remover métodos e propriedades**

Rode:

```bash
python3 - <<'EOF'
import re
def drop_method(s, name):
    pat = r'\n(    /\*\*(?:(?!\*/).)*?\*/\n)?    (?:public |protected |private )?(?:static )?function %s\(.*?\n    }\n' % name
    s, n = re.subn(pat, '\n', s, count=1, flags=re.S); assert n == 1, name; return s
def drop_prop(s, name):
    pat = r'\n(    /\*\*(?:(?!\*/).)*?\*/\n)?    (?:public|protected|private) \$%s\b[^;]*;\n' % name
    s, n = re.subn(pat, '\n', s, count=1, flags=re.S); assert n == 1, name; return s
p = 'src/Common/Factory.php'; s = open(p, encoding='utf-8').read()
s = drop_method(s, 'standardizeProperties')
old = """        //Adding forgotten or unnecessary fields.
        //This is done for standardization purposes.
        //Fields with no value will not be included by the builder.
        //$this->std = $this->standardizeProperties($this->std);
"""
assert s.count(old) == 1; s = s.replace(old, '')
open(p, 'w', encoding='utf-8').write(s)
p = 'src/Common/Soap/SoapCurl.php'; s = open(p, encoding='utf-8').read()
open(p, 'w', encoding='utf-8').write(drop_method(s, 'getFaultString'))
p = 'src/Common/Soap/SoapBase.php'; s = open(p, encoding='utf-8').read()
for m in ['__destruct', 'removeTemporarilyFiles', 'setEncriptPrivateKey', 'saveTemporarilyKeyFiles', 'makeEnvelopeSoap', 'setSoapPrefix']:
    s = drop_method(s, m)
open(p, 'w', encoding='utf-8').write(s)
print('ok')
EOF
php -l src/Common/Factory.php && php -l src/Common/Soap/SoapCurl.php && php -l src/Common/Soap/SoapBase.php
```

Depois, para cada propriedade candidata, conte o uso e remova só as que ficaram com 0:

```bash
for p in prifile pubfile certfile certsdir temppass encriptPrivateKey prefixes waitingTime; do
  echo "$p: $(grep -cE "(this->|self::\\\$)$p\b" src/Common/Soap/SoapBase.php src/Common/Soap/SoapCurl.php src/Common/Soap/SoapFake.php | awk -F: '{s+=$2} END {print s}')"
done
```

Esperado: 0 para todas. Remova com:

```bash
python3 - <<'EOF'
import re
p = 'src/Common/Soap/SoapBase.php'; s = open(p, encoding='utf-8').read()
for name in ['prifile', 'pubfile', 'certfile', 'certsdir', 'temppass', 'encriptPrivateKey', 'prefixes', 'waitingTime']:
    pat = r'\n(    /\*\*(?:(?!\*/).)*?\*/\n)?    (?:public|protected|private) \$%s\b[^;]*;\n' % name
    s, n = re.subn(pat, '\n', s, count=1, flags=re.S); print(name, n)
open(p, 'w', encoding='utf-8').write(s)
EOF
grep -n 'Strings::\|use NFePHP\\Common\\Strings' src/Common/Soap/SoapBase.php
```

Se só sobrar a linha do `use ...Strings;`, remova-a. Rode `php -l src/Common/Soap/SoapBase.php`
e confira com `git diff src/Common/Soap/SoapBase.php` que `filesystem`, `tempdir`, `debugdir`,
`setDebugMode` e `saveDebugFiles` continuam.

- [x] **Step 4: rodar a suíte e os exemplos de envio**

Rode: `composer test`
Esperado: verde e sem `deprecated`.

Rode: `cd examples/Fake/v_S_01_03_00 && for f in Faketest*.php; do php -d xdebug.mode=off -d display_errors=0 $f > /dev/null 2>&1 && echo "ok $f" || echo "FALHA $f"; done; cd -`
Esperado: `ok` em todos (8 arquivos).

---

### Task 6: AGENTS.md e verificação final

**Arquivos:**
- Alterar: `AGENTS.md`

**Depende de:** Tasks 1-5

- [x] **Step 1: AGENTS.md**

No mapa do código, substituir:

```markdown
| `schemes/comunicacao/v1_6_0/` | XSD/WSDL de envio e consulta de lotes (1.6-Alfa; `v1_5_0` mantido até o SP2) |
```

por:

```markdown
| `schemes/comunicacao/v1_6_0/` | XSD/WSDL de envio e consulta de lotes (1.6-Alfa, padrão; os pacotes 1.1.0 a 1.5.0 continuam disponíveis) |
```

Na seção "## Regras", depois da linha que começa com "- Namespace `NFePHP\eSocial`", acrescentar:

```markdown
- Só o leiaute **S-1.3** (S-1.2 encerrado no eSocial em 02/02/2025): `eventoVersion` abaixo
  disso é recusado no construtor do evento. Os `toNodeS100/S110/S120` dos traits continuam
  porque vários `toNodeS130` delegam para eles.
- Eventos que a lib não monta (`Event::$unavailable`: retornos S-5xxx, extintos, S-8200/S-8299)
  respondem com `EventsException` código 1003 e o motivo.
- PHP >= 8.1; parâmetro opcional tipado sempre com `?Tipo $x = null`. Grupo 1 do lote:
  `Tools::EVT_TABELAS` (`EVT_INICIAIS` é alias obsoleto).
```

- [x] **Step 2: deprecations**

Rode: `composer test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep 'Tests:'` e
`grep -rnE '[^?]Certificate \$certificate = null|[^?]LoggerInterface \$logger = null' src`
Esperado: resumo sem `deprecated`/`failed`; grep vazio.

- [x] **Step 3: código removido**

Rode: `grep -rnE 'ParamsStandardize|Sanitize|SoapCode|getFaultString|saveTemporarilyKeyFiles|setEncriptPrivateKey|makeEnvelopeSoap|Factories\\Evt(TreiCap|BasesTrab|AnotJud|Baixa)|TraitS(2245|8200)|v_S_01_0[012]_00' src tests examples AGENTS.md composer.json`
Esperado: vazio.

- [x] **Step 4: exemplos**

Rode: `php -d xdebug.mode=off .claude/skills/update-esocial/scripts/validate.php`
Esperado: `40/40`.

- [x] **Step 5: pastas e escopo**

Rode: `ls schemes jsonSchemes examples/Fake examples/schemes && git status --short | grep -v '^D ' | head -40`
Esperado: só `v_S_01_03_00` (+ `comunicacao`, `definitions.schema`); os arquivos modificados são
os das Tasks 1-6.

---

## Ajustes durante a execução

- **2026-10-14 (planejamento)** — o spec descreve `Event::$unavailable` como mapa
  "chave (nome e código em minúsculo) → [código, nome, motivo]". O plano usa uma lista
  `código → [nome, tipo]` e o `__callStatic` compara o nome normalizado com o nome em minúsculo e
  com o código sem hífen; os textos de motivo ficam em `$unavailableReasons`. Mesmo
  comportamento observável, sem duplicar cada evento em duas chaves.
- **2026-10-14 (planejamento)** — a mensagem do indisponível depende do dataset, então não cabe
  em `->throws()`; o teste captura a exceção com `caughtException()` no Act e confere tipo,
  mensagem e código no Assert.
- **2026-10-14 (revisão do plano)** — revisor executou o plano numa cópia: 287 passed, 0
  deprecated, 40/40. Ajustes: `validate.php` com Xdebug desligado; "tem XSD" falha com 14 (não
  22) e ganhou `class_exists`; posição do `refuseClosedLayout`; "Esperado" do `composer
  validate`; linha do motivo `retorno` quebrada (phpcs > 120); exemplos `Faketest*` passam a usar
  `EVT_TABELAS`.
- **2026-10-14 (execução, batch 1)** — no vermelho da Task 1, o `class_exists` do teste "tem classe
  e XSD" carrega a `EvtCS` e o PHP morre com o fatal do trait inexistente (o bug que a task
  corrige), interrompendo a suíte antes de mostrar as outras falhas. Vermelho legítimo; depois
  da implementação a suíte fica com 274 passed (igual à execução do revisor na cópia).
