<?php

namespace NFePHP\eSocial;

/**
 * Class eSocial Event constructor
 *
 * @category  library
 * @package   NFePHP\eSocial
 * @copyright NFePHP Copyright (c) 2017
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Roberto L. Machado <linux.rlm at gmail dot com>
 * @link      http://github.com/nfephp-org/sped-esocial for the canonical source repository
 */
use NFePHP\eSocial\Exception\EventsException;

class Event
{
    /**
     * Relationship between the name of the event and its respective class
     * @var array
     */
    public static $available = [
        'evtadmissao' => Factories\EvtAdmissao::class,
        'evtcessao' => Factories\EvtCessao::class,
        'evtpgtos' => Factories\EvtPgtos::class,
        'evtrmnrpps' => Factories\EvtRmnRPPS::class,
        'evtadmprelim' => Factories\EvtAdmPrelim::class,
        'evtdeslig' => Factories\EvtDeslig::class,
        'evtafasttemp' => Factories\EvtAfastTemp::class,
        'evtexclusao' => Factories\EvtExclusao::class,
        'evtexcproctrab' => Factories\EvtExcProcTrab::class,
        'evtaltcadastral' => Factories\EvtAltCadastral::class,
        'evtexprisco' => Factories\EvtExpRisco::class,
        'evtaltcontratual' => Factories\EvtAltContratual::class,
        'evtfechaevper' => Factories\EvtFechaEvPer::class,
        'evttabestab' => Factories\EvtTabEstab::class,
        'evtinfocomplper' => Factories\EvtInfoComplPer::class,
        'evtinfoempregador' => Factories\EvtInfoEmpregador::class,
        'evttablotacao' => Factories\EvtTabLotacao::class,
        'evtbenprrp' => Factories\EvtBenPrRP::class,
        'evttabprocesso' => Factories\EvtTabProcesso::class,
        'evtcat' => Factories\EvtCAT::class,
        'evtmonit' => Factories\EvtMonit::class,
        'evttabrubrica' => Factories\EvtTabRubrica::class,
        'evtcdbenefin' => Factories\EvtCdBenefIn::class,
        'evtcdbenefalt' => Factories\EvtCdBenefAlt::class,
        'evtcdbenin' => Factories\EvtCdBenIn::class,
        'evtcdbenalt' => Factories\EvtCdBenAlt::class,
        'evtreativben' => Factories\EvtReativBen::class,
        'evtcdbenterm' => Factories\EvtCdBenTerm::class,
        'evttsvaltcontr' => Factories\EvtTSVAltContr::class,
        'evtcomprod' => Factories\EvtComProd::class,
        'evtreabreevper' => Factories\EvtReabreEvPer::class,
        'evttsvinicio' => Factories\EvtTSVInicio::class,
        'evtcontratavnp' => Factories\EvtContratAvNP::class,
        'evtreintegr' => Factories\EvtReintegr::class,
        'evttsvtermino' => Factories\EvtTSVTermino::class,
        'evtremun' => Factories\EvtRemun::class,
        'evttoxic' => Factories\EvtToxic::class,
        'evtproctrab' => Factories\EvtProcTrab::class,
        'evtcontproc' => Factories\EvtContProc::class,
        'evtconsolidcontproc' => Factories\EvtConsolidContProc::class,
    ];

    /**
     * Relationship between the code of the event and its respective name
     *
     * @var array
     */
    public static $aliases = [
        's2200' => 'evtadmissao',
        's2231' => 'evtcessao',
        's1202' => 'evtrmnrpps',
        's2190' => 'evtadmprelim',
        's2299' => 'evtdeslig',
        's2230' => 'evtafasttemp',
        's3000' => 'evtexclusao',
        's3500' => 'evtexcproctrab',
        's2205' => 'evtaltcadastral',
        's2240' => 'evtexprisco',
        's2206' => 'evtaltcontratual',
        's1299' => 'evtfechaevper',
        's1005' => 'evttabestab',
        's1280' => 'evtinfocomplper',
        's1000' => 'evtinfoempregador',
        's1020' => 'evttablotacao',
        's1207' => 'evtbenprrp',
        's1070' => 'evttabprocesso',
        's2210' => 'evtcat',
        's2220' => 'evtmonit',
        's2221' => 'evttoxic',
        's1010' => 'evttabrubrica',
        's2400' => 'evtcdbenefin',
        's1210' => 'evtpgtos',
        's2306' => 'evttsvaltcontr',
        's1260' => 'evtcomprod',
        's1298' => 'evtreabreevper',
        's2300' => 'evttsvinicio',
        's1270' => 'evtcontratavnp',
        's2298' => 'evtreintegr',
        's2399' => 'evttsvtermino',
        's1200' => 'evtremun',
        's2405' => 'evtcdbenefalt',
        's2410' => 'evtcdbenin',
        's2416' => 'evtcdbenalt',
        's2418' => 'evtreativben',
        's2420' => 'evtcdbenterm',
        's2500' => 'evtproctrab',
        's2501' => 'evtcontproc',
        's2555' => 'evtconsolidcontproc',
    ];

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

    public static $codes = [
        'evtInfoEmpregador' => 'S-1000',
        'evtTabEstab' => 'S-1005',
        'evtTabRubrica' => 'S-1010',
        'evtTabLotacao' => 'S-1020',
        'evtTabProcesso' => 'S-1070',
        'evtRemun' => 'S-1200',
        'evtRmnRPPS' => 'S-1202',
        'evtBenPrRP' => 'S-1207',
        'evtPgtos' => 'S-1210',
        'evtComProd' => 'S-1260',
        'evtContratAvNP' => 'S-1270',
        'evtInfoComplPer' => 'S-1280',
        'evtReabreEvPer' => 'S-1298',
        'evtFechaEvPer' => 'S-1299',
        'evtAdmPrelim' => 'S-2190',
        'evtAdmissao' => 'S-2200',
        'evtAltCadastral' => 'S-2205',
        'evtAltContratual' => 'S-2206',
        'evtCAT' => 'S-2210',
        'evtMonit' => 'S-2220',
        'evtToxic' => 'S-2221',
        'evtAfastTemp' => 'S-2230',
        'evtCessao' => 'S-2231',
        'evtExpRisco' => 'S-2240',
        'evtReintegr' => 'S-2298',
        'evtDeslig' => 'S-2299',
        'evtTSVInicio' => 'S-2300',
        'evtTSVAltContr' => 'S-2306',
        'evtTSVTermino' => 'S-2399',
        'evtCdBenefIn' => 'S-2400',
        'evtCdBenefAlt' => 'S-2405',
        'evtCdBenIn' => 'S-2410',
        'evtCdBenAlt' => 'S-2416',
        'evtReativBen' => 'S-2418',
        'evtCdBenTerm' => 'S-2420',
        'evtProcTrab' => 'S-2500',
        'evtContProc' => 'S-2501',
        'evtConsolidContProc' => 'S-2555',
        'evtExclusao' => 'S-3000',
        'evtExcProcTrab' => 'S-3500',
        'evtAnotJud' => 'S-8200',
        'evtBaixa' => 'S-8299'
    ];

    /**
     * Call classes to build XML eSocial Event
     *
     * @param  string $name
     * @param  array $arguments [config, std, certificate, $date]
     *
     * @return object
     * @throws EventsException
     */
    public static function __callStatic($name, $arguments)
    {
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
        if (substr($name, 0, 1) == 's') {
            if (!array_key_exists($name, self::$aliases)) {
                //este evento não foi localizado
                throw EventsException::wrongArgument(1000, $name);
            }
            $realname = self::$aliases[$name];
        }
        if (!array_key_exists($realname, self::$available)) {
            //este evento não foi localizado
            throw EventsException::wrongArgument(1000, $name);
        }
        $className = self::$available[$realname];
        if (empty($arguments[0])) {
            throw EventsException::wrongArgument(1001);
        }
        if (empty($arguments[1])) {
            throw EventsException::wrongArgument(1002, $name);
        }
        if (count($arguments) > 1 && count($arguments) < 3) {
            return new $className($arguments[0], $arguments[1]);
        }
        if (count($arguments) > 2 && count($arguments) < 4) {
            return new $className($arguments[0], $arguments[1], $arguments[2]);
        }
        if (count($arguments) > 3) {
            return new $className($arguments[0], $arguments[1], $arguments[2], $arguments[3]);
        }
        return new $className($arguments[0], $arguments[1]);
    }
}
