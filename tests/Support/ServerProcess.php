<?php

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * 测试用的 worker 服务进程封装。
 *
 * 不能靠「等到进程有输出」判断服务就绪：Workerman 在各 worker 真正 accept
 * 之前就打印了启动横幅，Windows 下尤其明显，断言会撞上 Connection refused。
 * 这里改为轮询端口，并在进程提前退出时把输出抛出来便于定位。
 */
class ServerProcess
{
    /** @var Process */
    protected $process;

    protected string $host;

    protected int $port;

    public function __construct(array $env = [], string $host = '127.0.0.1', int $port = 8080)
    {
        $this->host = $host;
        $this->port = $port;

        $this->process = new Process([PHP_BINARY, 'think', 'worker'], STUB_DIR, $env);
        $this->process->setTimeout(null);
    }

    /**
     * 启动并等待端口就绪
     */
    public function start(int $timeout = 60): void
    {
        $this->process->start();

        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            if ($this->portIsOpen()) {
                return;
            }

            if (!$this->process->isRunning()) {
                throw new RuntimeException(
                    "worker exited before the port was ready:\n" . $this->output()
                );
            }

            usleep(200000);
        }

        $this->stop();

        throw new RuntimeException(sprintf(
            "worker did not listen on %s:%d within %ds:\n%s",
            $this->host,
            $this->port,
            $timeout,
            $this->output()
        ));
    }

    public function portIsOpen(): bool
    {
        $client = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, 1);

        if ($client) {
            fclose($client);
            return true;
        }

        return false;
    }

    public function output(): string
    {
        return $this->process->getOutput() . $this->process->getErrorOutput();
    }

    /**
     * 停止服务并回收整棵进程树。
     *
     * Windows 下 proc_terminate 只杀掉直接子进程，Workerman 的 master 与各
     * worker 会残留并继续占用端口，导致后续测试文件起不来。
     * 用 taskkill /T 按进程树终止。
     */
    public function stop(): void
    {
        if (!$this->process->isRunning()) {
            return;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            exec(sprintf('taskkill /F /T /PID %d 2>nul', $this->process->getPid()));
        }

        $this->process->stop();
    }
}
