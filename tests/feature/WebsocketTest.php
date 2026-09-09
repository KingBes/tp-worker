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
    $conns     = [];
    $sent      = false;

    // 兜底定时器：连接失败时 reject 的 promise 不会终止事件循环，
    // 没有它整个测试会挂住直到 CI 超时，而不是快速失败
    Loop::get()->addTimer(15, function () {
        Loop::get()->stop();
    });

    // 两个连接都完成握手后再触发广播：连接 A 的 join('foo') 在服务端 A 的
    // onOpen 中执行（先于 A 的 101 到达客户端），若只等 B 的 open 就发送，
    // B 的回环可能跑赢 W1 对 A 的握手处理，广播时 A 尚未入房间，消息丢失。
    // 服务端 onOpen 与 101 在同一同步块内执行（无挂起点），因此「客户端 open
    // 事件」严格蕴含「该连接的房间登记已写入 conduit」。
    $open = function (\Ratchet\Client\WebSocket $conn) use (&$connected, &$messages, &$conns, &$sent) {
        $connected++;
        $conns[] = $conn;

        $conn->on('message', function ($msg) use ($conn, &$messages) {
            $messages[] = (string) $msg;
            $conn->close();

            // 收齐两条立即结束，正常路径不再等满 15s 兜底定时器
            if (count($messages) === 2) {
                Loop::get()->stop();
            }
        });

        if (count($conns) === 2 && !$sent) {
            $sent = true;
            $conns[0]->send('hello');
        }
    };

    connect('ws://127.0.0.1:8080/websocket')->then($open);
    connect('ws://127.0.0.1:8080/websocket')->then($open);

    Loop::get()->run();

    expect($connected)->toBe(2);
    expect($messages)->toBe(['hello', 'hello']);
});
