<?php

namespace NFePHP\eSocial\Common;

use DateTime;

/**
 * Class FactoryId
 *
 * Id do evento: "ID" + tpInsc + nrInsc (completado com zeros à direita até 14) +
 * AAAAMMDDHHMMSS + sequencial de 5 dígitos.
 *
 * Os Ids gerados ficam registrados no processo: se o mesmo empregador, segundo e sequencial se
 * repetirem, usa o próximo sequencial livre. Por isso o Id de um evento é o que está em
 * $evento->evtid, e não deve ser recalculado por fora. Entre processos diferentes não há como
 * garantir unicidade: quem roda vários workers para o mesmo empregador aloca o sequencial.
 */
class FactoryId
{
    /**
     * Quantas bases (empregador + segundo) o registro guarda antes de descartar as mais antigas.
     */
    const MAX_BASES = 1000;

    /**
     * @var array<string, array<int, bool>> base => sequenciais já usados
     */
    private static $issued = [];

    /**
     * @param int $tpInsc
     * @param string $nrInsc
     * @param DateTime|null $date
     * @param int|null $sequential
     * @return string
     */
    public static function build(
        $tpInsc,
        $nrInsc,
        ?DateTime $date = null,
        $sequential = null
    ) {
        if (empty($date)) {
            $date = new DateTime();
        }
        $base = "ID"
            . $tpInsc
            . str_pad($nrInsc, 14, '0', STR_PAD_RIGHT)
            . $date->format('YmdHis');
        $sequential = empty($sequential) ? random_int(1, 99999) : (int) $sequential;
        $sequential = self::reserve($base, $sequential);

        return $base . str_pad($sequential, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Esquece os Ids gerados neste processo.
     */
    public static function reset()
    {
        self::$issued = [];
    }

    /**
     * @param string $base
     * @param int $sequential
     * @return int sequencial reservado
     */
    private static function reserve($base, $sequential)
    {
        $used = self::$issued[$base] ?? [];
        if (count($used) >= 99999) {
            throw new \RuntimeException(
                "Todos os 99999 sequenciais de $base já foram usados neste processo."
            );
        }
        while (isset($used[$sequential])) {
            $sequential = $sequential >= 99999 ? 1 : $sequential + 1;
        }
        // libera a cópia: sem isto cada escrita abaixo duplica o array (copy-on-write) e o
        // registro vira O(n²)
        unset($used);
        if (!isset(self::$issued[$base]) && count(self::$issued) >= self::MAX_BASES) {
            array_shift(self::$issued);
        }
        self::$issued[$base][$sequential] = true;

        return $sequential;
    }
}
