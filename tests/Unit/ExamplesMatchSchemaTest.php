<?php

use NFePHP\eSocial\Event;

/*
 * Cada examples/Fake/v_S_01_03_00/Fake_sNNNN_*.php usa os nomes de campo que o montador lê e gera
 * XML válido no XSD (BuildersTest). Se o JSON schema não declara um desses campos no mesmo
 * caminho, quem segue o schema manda outro nome e o grupo some do XML sem erro, ou manda o nome
 * certo e ele passa sem ser conferido (os schemas não usam additionalProperties: false).
 */

/**
 * Caminhos das chaves (em minúsculo, como a Factory faz antes de validar); itens de array não
 * entram no caminho.
 */
function inputPaths($value, string $prefix = ''): array
{
    $paths = [];
    $children = is_object($value) ? get_object_vars($value) : (is_array($value) ? $value : []);
    foreach ($children as $key => $child) {
        $path = is_int($key) ? $prefix : ltrim($prefix . '/' . strtolower($key), '/');
        if (!is_int($key)) {
            $paths[] = $path;
        }
        $paths = array_merge($paths, inputPaths($child, $path));
    }

    return array_values(array_unique($paths));
}

function schemaPaths(array $node, string $prefix = ''): array
{
    $paths = [];
    foreach ($node['properties'] ?? [] as $key => $child) {
        $path = ltrim("$prefix/$key", '/');
        $paths[] = $path;
        $paths = array_merge($paths, schemaPaths($child, $path));
        if (isset($child['items']) && is_array($child['items'])) {
            $paths = array_merge($paths, schemaPaths($child['items'], $path));
        }
    }

    return $paths;
}

it('declara no JSON schema todo campo que o exemplo do evento usa', function (string $file) {
    $evt = Event::$aliases[strtolower(substr(basename($file), 5, 5))];
    $schemaFile = current(array_filter(
        glob(dirname(__DIR__, 2) . '/jsonSchemes/v_S_01_03_00/*.schema'),
        fn ($f) => strtolower(basename($f, '.schema')) === $evt
    ));
    $declared = schemaPaths(json_decode(file_get_contents($schemaFile), true));
    $input = inputPaths(exampleInput($file));

    $undeclared = array_values(array_diff($input, $declared));

    expect($undeclared)->toBe([]);
})->with(fn () => glob(dirname(__DIR__, 2) . '/examples/Fake/v_S_01_03_00/Fake_s*.php'));

it('leva ao XML o grupo que o exemplo informa', function (string $code, string $tag, array $config = []) {
    $file = current(glob(dirname(__DIR__, 2) . "/examples/Fake/v_S_01_03_00/Fake_{$code}_*.php"));
    $configJson = configWith($this->configJson, $config);
    $event = Event::$code($configJson, exampleInput($file), $this->certificate, '2026-10-16 10:00:00');

    $document = eventDocument($event);

    expect($document->getElementsByTagName($tag)->length)->toBeGreaterThan(0);
})->with([
    'S-1260 vrRatDescPR' => ['s1260', 'vrRatDescPR', ['empregador' => ['tpInsc' => 2, 'nrInsc' => '12345678901']]],
    'S-2200 infoDeficiencia' => ['s2200', 'infoDeficiencia'],
    'S-2200 depIRRF' => ['s2200', 'depIRRF'],
    'S-2200 matAnotJud' => ['s2200', 'matAnotJud'],
    'S-2220 respMonit' => ['s2220', 'respMonit'],
    'S-2240 unMed' => ['s2240', 'unMed'],
    'S-2299 procCS' => ['s2299', 'procCS'],
    'S-2300 cargoFuncao' => ['s2300', 'cargoFuncao'],
    'S-2306 infoMandElet' => ['s2306', 'infoMandElet'],
    'S-2500 dscSalVar' => ['s2500', 'dscSalVar'],
    'S-2500 clauAssec' => ['s2500', 'clauAssec'],
    'S-3500 ideSeqProc' => ['s3500', 'ideSeqProc'],
]);
