<?php

// 测试桩没有 vendor/services.php，ThinkPHP 的 RegisterService 找不到任何扩展服务，
// queue / worker 的服务都不会被注册（表现为容器里没有 queue 绑定，调用即 500）。
// 这里显式声明，保证 console 与 worker 子进程两条启动路径注册一致。
return [
    \think\worker\Service::class,
    \think\queue\Service::class,
];
