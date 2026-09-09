<?php
define('STUB_DIR', realpath(__DIR__ . '/stub'));

// 测试请求全部指向 127.0.0.1。Guzzle 在 CLI 下会自动采用 HTTP_PROXY /
// HTTPS_PROXY，一旦环境里存在代理，请求会被转发到别处，断言全部落空。
// 这里清掉代理相关变量，保证测试不受运行环境网络配置影响。
foreach (['HTTP_PROXY', 'http_proxy', 'HTTPS_PROXY', 'https_proxy', 'NO_PROXY', 'no_proxy'] as $name) {
    putenv($name);
    unset($_SERVER[$name], $_ENV[$name]);
}
