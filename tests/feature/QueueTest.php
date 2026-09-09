<?php

use GuzzleHttp\Client;
use Tests\Support\ServerProcess;

/**
 * 消费日志：任务被 worker 消费后追加写入
 */
function queueLog(): string
{
    return STUB_DIR . '/runtime/queue_consumed.log';
}

/**
 * 文件队列的存储目录
 */
function queueStore(): string
{
    return STUB_DIR . '/runtime/queue';
}

/**
 * 清空上一轮遗留的任务文件与消费日志
 */
function resetQueueStore(): void
{
    foreach (glob(queueStore() . '/*/*.json') ?: [] as $file) {
        @unlink($file);
    }

    foreach (glob(queueStore() . '/*/*.reserved') ?: [] as $file) {
        @unlink($file);
    }

    if (is_file(queueLog())) {
        @unlink(queueLog());
    }
}

$server = null;
beforeAll(function () use (&$server) {
    resetQueueStore();

    $server = new ServerProcess([
        'PHP_WEBSOCKET_ENABLE' => 'false',
        'PHP_QUEUE_ENABLE'     => 'true',
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
        'timeout'     => 5,
    ]);
});

it('registers a queue worker', function () use (&$server) {
    // 队列 worker 以 "queue [队列名]" 命名，出现在 Workerman 的 worker 列表里
    expect($server->output())->toContain('queue [default]');
});

it('consumes the pushed job', function () {
    $response = $this->httpClient->get('/queue');

    expect($response->getStatusCode())->toBe(200);

    $data = json_decode($response->getBody()->getContents(), true);

    expect($data)->toBeArray()->toHaveKey('id');

    // worker 每 0.1s 轮询一次，这里给它一个充裕的窗口
    $deadline = microtime(true) + 20;
    $consumed = '';

    while (microtime(true) < $deadline) {
        if (is_file(queueLog())) {
            $consumed = (string) file_get_contents(queueLog());

            if (str_contains($consumed, $data['id'])) {
                break;
            }
        }

        usleep(200000);
    }

    expect($consumed)->toContain($data['id']);
});

it('removes the job once it is consumed', function () {
    // 消费成功后任务文件应被删除，不留下待处理或已保留的残留
    $pending  = glob(queueStore() . '/*/*.json') ?: [];
    $reserved = glob(queueStore() . '/*/*.reserved') ?: [];

    expect($pending)->toBeEmpty()
        ->and($reserved)->toBeEmpty();
});
