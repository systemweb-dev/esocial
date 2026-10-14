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
