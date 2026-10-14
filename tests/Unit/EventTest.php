<?php

use NFePHP\eSocial\Event;
use NFePHP\eSocial\Exception\EventsException;
use NFePHP\eSocial\Factories\EvtInfoEmpregador;

function s1000Payload(): stdClass
{
    return json_decode(json_encode([
        'sequencial' => 1,
        'modo' => 'INC',
        'ideperiodo' => ['inivalid' => '2017-01'],
        'infocadastro' => [
            'classtrib' => '01',
            'indcoop' => 0,
            'indconstr' => 0,
            'inddesfolha' => 0,
            'indporte' => 'S',
            'indoptregeletron' => 1,
        ],
    ]));
}

it('resolve o evento pelo nome ou pelo código', function (string $name) {
    $std = s1000Payload();

    $event = Event::$name($this->configJson, $std, $this->certificate, '2017-08-03 10:37:00');

    expect($event)->toBeInstanceOf(EvtInfoEmpregador::class)
        ->and($event->alias())->toBe('S-1000');
})->with(['evtInfoEmpregador', 'EVTINFOEMPREGADOR', 's1000', 'S-1000', 'S1000']);

it('usa a data informada no Id do evento', function () {
    $std = s1000Payload();

    $event = Event::s1000($this->configJson, $std, $this->certificate, '2017-08-03 10:37:00');

    expect($event->evtid)->toBe('ID1' . '99999999000000' . '20170803103700' . '00001');
});

it('recusa nome de evento desconhecido', function () {
    $std = s1000Payload();

    Event::evtInexistente($this->configJson, $std);
})->throws(EventsException::class, 'Este evento [evtinexistente] não foi encontrado.');

it('recusa código de evento desconhecido', function () {
    $std = s1000Payload();

    Event::s9999($this->configJson, $std);
})->throws(EventsException::class, 'Este evento [s9999] não foi encontrado.');

it('exige o config', function () {
    $std = s1000Payload();

    Event::s1000('', $std);
})->throws(EventsException::class, 'Não foi passado o config.');

it('exige os dados do evento', function () {
    $std = null;

    Event::s1000($this->configJson, $std);
})->throws(EventsException::class, 'Não foram passados os dados do evento.');

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

    expect($error)->toBeInstanceOf(EventsException::class)
        ->and($error->getCode())->toBe($code);
})->with([
    'evento desconhecido' => [function () { Event::evtInexistente('{}', new stdClass()); }, 1000],
    'sem config' => [function () { Event::s1000('', new stdClass()); }, 1001],
    'sem dados' => [function () { Event::s1000('{}', null); }, 1002],
]);
