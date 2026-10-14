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
            $catalog = is_file($file) ? json_decode(file_get_contents($file), true) : null;
            if (!isset($catalog['versao'], $catalog['mensagens'])) {
                throw new \RuntimeException("Catálogo das Mensagens do Sistema ausente ou inválido: $file");
            }
            self::$catalog = $catalog;
        }

        return self::$catalog;
    }
}
