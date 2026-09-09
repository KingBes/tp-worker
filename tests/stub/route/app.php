<?php

use think\facade\Route;

Route::get('/', function () {
    return 'hello world';
});

Route::put('/', function () {
    return 'put';
});

Route::delete('/', function () {
    return 'delete';
});

Route::get('/sse', function () {

    $generator = function () {
        foreach (range(0, 9) as $event) {
            yield 'data: ' . json_encode($event) . "\n\n";
        }

        yield "data: [DONE]\n\n";
    };

    $response = new \think\worker\response\Iterator($generator());

    return $response->header([
        'Content-Type'  => 'text/event-stream',
        'Cache-Control' => 'no-cache, must-revalidate',
    ]);
});

Route::get('/websocket', function () {
    return (new \think\worker\response\Websocket())
        ->onOpen(function (\think\worker\Websocket $websocket) {
            $websocket->join('foo');
        })
        ->onMessage(function (\think\worker\Websocket $websocket, \think\worker\websocket\Frame $frame) {
            $websocket->to('foo')->push($frame->data);
        });
});

Route::get('test', 'index/test');
Route::post('json', 'index/json');

// 投递一个队列任务，由 queue worker 消费后写入 runtime/queue_consumed.log
Route::get('queue', function () {
    $data = ['id' => uniqid('', true)];

    \think\facade\Queue::push(\app\job\Demo::class, $data);

    return json_encode($data);
});

// 数据库连接验证：SELECT 1 走默认连接（sqlite），配合 db_heartbeat 覆盖保活路径
Route::get('db', function () {
    $connection = \think\facade\Db::connect();

    $rows = $connection instanceof \think\db\PDOConnection
        ? $connection->query('SELECT 1 AS one')
        : [];

    return json_encode(['one' => $rows[0]['one'] ?? null]);
});

Route::get('static/:path', function (string $path) {
    $filename = public_path() . $path;
    return new \think\worker\response\File($filename);
})->pattern(['path' => '.*\.\w+$']);
