<?php

declare(strict_types=1);

use App\Library\Mesa;

test('mesa livre pode receber pedido', function () {

    $mesa = new Mesa(
        numero: 1,
        status: 'livre',
        ativo: true
    );

    expect(
        $mesa->podeReceberPedido()
    )->toBeTrue();

});

test('mesa ocupada nao pode receber pedido', function () {

    $mesa = new Mesa(
        numero: 1,
        status: 'ocupada',
        ativo: true
    );

    expect(
        $mesa->podeReceberPedido()
    )->toBeFalse();

});

test('mesa reservada nao pode receber pedido', function () {

    $mesa = new Mesa(
        numero: 1,
        status: 'reservada',
        ativo: true
    );

    expect(
        $mesa->podeReceberPedido()
    )->toBeFalse();

});

test('mesa inativa nao pode receber pedido', function () {

    $mesa = new Mesa(
        numero: 1,
        status: 'inativa',
        ativo: true
    );

    expect(
        $mesa->podeReceberPedido()
    )->toBeFalse();

});

test('mesa desativada nao pode receber pedido', function () {

    $mesa = new Mesa(
        numero: 1,
        status: 'livre',
        ativo: false
    );

    expect(
        $mesa->podeReceberPedido()
    )->toBeFalse();

});

test('mesa livre esta disponivel', function () {

    $mesa = new Mesa(
        numero: 5,
        status: 'livre',
        ativo: true
    );

    expect(
        $mesa->estaDisponivel()
    )->toBeTrue();

});

test('retorna numero da mesa corretamente', function () {

    $mesa = new Mesa(
        numero: 15
    );

    expect(
        $mesa->getNumero()
    )->toBe(15);

});