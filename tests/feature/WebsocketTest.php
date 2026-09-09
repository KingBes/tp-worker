<?php

use GuzzleHttp\Client;
use React\EventLoop\Loop;
use Tests\Support\ServerProcess;
use function Ratchet\Client\connect;

$server = null;
beforeAll(function () use (&$server) {
    $server = new ServerProcess([
        'PHP_WEBSOCKET_ENABLE' => 'true',
        'PHP_QUEUE_ENABLE'     => 'false',
        'PHP_HOT_ENABLE'       => 'false',
    ]);

    $server->start();
});

afterAll(function () use (&$server) {
    echo $server->output();
    $server->stop();
});

beforeEach(function () {
    $this->httpClient = new Client([
        'base_uri'    => 'http://127.0.0.1:8080',
        'cookies'     => true,
        'http_errors' => false,
        'timeout'     => 1,
    ]);
});

it('http', function () {
    $response = $this->httpClient->get('/');

    expect($response->getStatusCode())
        ->toBe(200)
        ->and($response->getBody()->getContents())
        ->toBe('hello world');
});

it('websocket', function () {
    $connected = 0;
    $messages  = [];

    // 兜底定时器：连接失败时 reject 的 promise 不会终止事件循环，
    // 没有它整个测试会挂住直到 CI 超时，而不是快速失败
    Loop::get()->addTimer(15, function () {
        Loop::get()->stop();
    });

    connect('ws://127.0.0.1:8080/websocket')
        ->then(function (\Ratchet\Client\WebSocket $conn) use (&$connected, &$messages) {
            $connected++;
            $conn->on('message', function ($msg) use ($conn, &$messages) {
                $messages[] = (string) $msg;
                $conn->close();
            });
        });

    connect('ws://127.0.0.1:8080/websocket')
        ->then(function (\Ratchet\Client\WebSocket $conn) use (&$connected, &$messages) {
            $connected++;
            $conn->on('message', function ($msg) use ($conn, &$messages) {
                $messages[] = (string) $msg;
                $conn->close();
            });

            $conn->send('hello');
        });

    Loop::get()->run();

    expect($connected)->toBe(2);
    expect($messages)->toBe(['hello', 'hello']);
});
