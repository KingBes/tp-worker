<?php

use GuzzleHttp\Client;
use Tests\Support\ServerProcess;

$server = null;
beforeAll(function () use (&$server) {
    $server = new ServerProcess([
        'PHP_WEBSOCKET_ENABLE' => 'false',
        'PHP_QUEUE_ENABLE'     => 'false',
    ]);

    $server->start();
});

afterAll(function () use (&$server) {
    echo $server->output();
    $server->stop();
});

it('db heartbeat keeps the worker alive and db usable after idle', function () {
    $http = new Client([
        'base_uri'    => 'http://127.0.0.1:8080',
        'http_errors' => false,
        'timeout'     => 5,
    ]);

    // 空闲跨越多个心跳周期（stub db_heartbeat = 1s）：
    // 1) 心跳定时器不应拖垮/阻塞常驻 worker；2) 心跳持续运行后 DB 查询路径正常
    sleep(3);

    $response = $http->get('/db');

    expect($response->getStatusCode())
        ->toBe(200)
        ->and(json_decode($response->getBody()->getContents(), true))
        ->toBe(['one' => 1]);
});
