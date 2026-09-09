<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

return [
    // 测试默认走文件驱动：Redis/Database 都需要外部服务，
    // 而 GitHub Actions 的 service 容器只在 Linux runner 上可用
    'default'     => env('QUEUE_CONNECTION', 'file'),
    'connections' => [
        'sync'     => [
            'type' => 'sync',
        ],
        'file'     => [
            'type'        => 'file',
            'queue'       => 'default',
            'path'        => runtime_path() . 'queue',
            'retry_after' => 60,
        ],
        'database' => [
            'type'       => 'database',
            'queue'      => 'default',
            'table'      => 'jobs',
            'connection' => null,
        ],
        'redis'    => [
            'type'        => 'redis',
            'queue'       => 'default',
            'host'        => env('REDIS_HOST', 'redis'),
            'port'        => env('REDIS_PORT', 6379),
            'password'    => '',
            'select'      => 0,
            'timeout'     => 0,
            'persistent'  => true,
            'retry_after' => 600,
        ],
    ],
    'failed'      => [
        'type'  => 'none',
        'table' => 'failed_jobs',
    ],
];
