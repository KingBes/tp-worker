<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 测试环境用 sqlite：无需任何数据库服务即可在三个平台跑通 db 相关用例
return [
    'default'     => 'sqlite',
    'connections' => [
        'sqlite' => [
            'type'            => 'sqlite',
            'database'        => runtime_path() . 'db.sqlite',
            'prefix'          => '',
            // 常驻进程连接失效（server has gone away 等）时断线重连并重试当前查询
            'break_reconnect' => true,
        ],
    ],
];
