<?php

use NFePHP\eSocial\Common\TranslateJsonValidation;

/*
 * Mensagens do justinrainbow/json-schema como saem do validador e a tradução esperada. A tradução
 * troca trechos: "is required" aparece tanto no campo obrigatório ("The property X is required")
 * quanto no erro de tipo ("..., but a string is required"), com gêneros diferentes.
 */
it('traduz a mensagem de validação com a concordância certa', function (string $message, string $expected) {
    $translated = TranslateJsonValidation::translate($message);

    expect($translated)->toBe($expected);
})->with([
    'campo obrigatório' => ['The property evtcomprod is required', 'A propriedade evtcomprod é obrigatória'],
    'tipo errado (string)' => ['Integer value found, but a string is required', 'Integer encontrado, mas o esperado é uma string'],
    'tipo errado (inteiro)' => ['String value found, but an integer is required', 'String encontrado, mas o esperado é um inteiro'],
    'tipo errado (vários)' => ['Integer value found, but a string or a null is required', 'Integer encontrado, mas o esperado é uma string ou um null'],
    'número' => ['String value found, but a number is required', 'String encontrado, mas o esperado é um número'],
    'repetição no array' => ['There are no duplicates allowed in the array', 'Não são permitidas repetições no array'],
    'data e hora' => [
        'Invalid date-time "x", expected format YYYY-MM-DDThh:mm:ssZ or YYYY-MM-DDThh:mm:ss+hh:mm',
        'Data e hora inválidas "x", esperado o formato YYYY-MM-DDThh:mm:ssZ ou YYYY-MM-DDThh:mm:ss+hh:mm',
    ],
    'valor mínimo' => ['Must have a minimum value of 1', 'Deve ter no mínimo 1'],
    'propriedade a mais' => [
        'The property x is not defined and the definition does not allow additional properties',
        'A propriedade x não está definida e a definição não permite propriedades adicionais',
    ],
]);
