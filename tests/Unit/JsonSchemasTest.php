<?php

/*
 * A Factory passa todas as chaves da entrada para minúsculo antes de validar no JSON schema
 * (propertiesToLower). Propriedade declarada com maiúscula no schema nunca casa com a entrada e a
 * regra dela nunca é aplicada (já aconteceu com dscSuspensao, dsccompLesao e infoInterm).
 */

function schemaKeysWithUppercase(array $node, string $path = ''): array
{
    $found = [];
    foreach ($node['properties'] ?? [] as $key => $child) {
        $childPath = ltrim("$path.$key", '.');
        if ($key !== strtolower($key)) {
            $found[] = $childPath;
        }
        $found = array_merge($found, schemaKeysWithUppercase($child, $childPath));
        if (isset($child['items']) && is_array($child['items'])) {
            $found = array_merge($found, schemaKeysWithUppercase($child['items'], $childPath));
        }
    }

    return $found;
}

it('declara as propriedades do JSON schema em minúsculo', function (string $file) {
    $schema = json_decode(file_get_contents($file), true);

    $uppercase = schemaKeysWithUppercase($schema);

    expect($uppercase)->toBe([]);
})->with(fn () => glob(dirname(__DIR__, 2) . '/jsonSchemes/v_S_01_03_00/*.schema'));
