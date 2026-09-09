<?php

namespace think\queue\connector;

use think\queue\Connector;
use think\queue\InteractsWithTime;
use think\queue\job\File as FileJob;

/**
 * 测试专用的文件队列驱动。
 *
 * 内置的 Redis / Database 驱动都依赖外部服务，而 GitHub Actions 的 service
 * 容器只有 Linux runner 支持，队列在 Windows / macOS 上就无法被覆盖。
 * 这里用「一任务一文件 + 原子 rename」实现，无外部依赖，跨平台可用。
 *
 * 仅用于测试，不属于运行时代码。
 */
class File extends Connector
{
    use InteractsWithTime;

    /** @var string 队列根目录 */
    protected $path;

    /** @var string 默认队列名 */
    protected $default;

    /** @var int 已保留任务超过该秒数未确认则回收重投 */
    protected $retryAfter;

    public static function __make(array $config)
    {
        return new self(
            $config['path'],
            $config['queue'] ?? 'default',
            (int) ($config['retry_after'] ?? 60)
        );
    }

    public function __construct(string $path, string $default = 'default', int $retryAfter = 60)
    {
        $this->path       = rtrim($path, '\\/');
        $this->default    = $default;
        $this->retryAfter = $retryAfter;
    }

    public function size($queue = null)
    {
        return count($this->jobFiles($this->getQueue($queue), false));
    }

    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue);
    }

    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return $this->store($this->getQueue($queue), $payload, 0);
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->store($this->getQueue($queue), $this->createPayload($job, $data), $delay);
    }

    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);

        $this->recycleExpired($queue);

        foreach ($this->jobFiles($queue, false) as $file) {
            $job = $this->readJob($file);

            if ($job === null || $job['available_time'] > $this->currentTime()) {
                continue;
            }

            $job['attempts']++;
            $job['reserve_time'] = $this->currentTime();

            // 先写内容再 rename：rename 是原子的，并发抢占时只有一个 worker 能成功
            $this->writeJob($file, $job);

            if (!@rename($file, $this->reservedFile($queue, $job['id']))) {
                continue;
            }

            return new FileJob($this->app, $this, (object) $job, $this->connection, $queue);
        }

        return null;
    }

    public function deleteReserved($id)
    {
        foreach ($this->glob('*' . DIRECTORY_SEPARATOR . $id . '.reserved') as $file) {
            @unlink($file);
        }
    }

    public function release($queue, $job, $delay)
    {
        $this->deleteReserved($job->id);

        return $this->store($this->getQueue($queue), $job->payload, $delay, (int) $job->attempts);
    }

    /**
     * 写入一个任务并返回任务 ID
     *
     * @param int|float|\DateTimeInterface $delay
     */
    protected function store(string $queue, string $payload, $delay, int $attempts = 0): string
    {
        $this->ensureQueueDir($queue);

        // 文件名前缀用定宽时间戳，字典序即 FIFO
        $id = sprintf('%013.4f-%s', microtime(true), bin2hex(random_bytes(4)));

        $this->writeJob($this->pendingFile($queue, $id), [
            'id'             => $id,
            'queue'          => $queue,
            'payload'        => $payload,
            'attempts'       => $attempts,
            'available_time' => $this->availableAt($delay),
            'reserve_time'   => null,
        ]);

        return $id;
    }

    /**
     * 回收超时未确认的任务，重新变为待处理
     */
    protected function recycleExpired(string $queue): void
    {
        $expiration = $this->currentTime() - $this->retryAfter;

        foreach ($this->jobFiles($queue, true) as $file) {
            $job = $this->readJob($file);

            if ($job !== null && ($job['reserve_time'] ?? 0) <= $expiration) {
                @rename($file, $this->pendingFile($queue, $job['id']));
            }
        }
    }

    /**
     * @param bool $reserved true 取已保留任务，false 取待处理任务
     * @return array 已按文件名排序（即按入队顺序）
     */
    protected function jobFiles(string $queue, bool $reserved): array
    {
        $files = $this->glob($queue . DIRECTORY_SEPARATOR . '*.' . ($reserved ? 'reserved' : 'json'));

        sort($files, SORT_STRING);

        return $files;
    }

    protected function glob(string $pattern): array
    {
        return glob($this->path . DIRECTORY_SEPARATOR . $pattern) ?: [];
    }

    protected function pendingFile(string $queue, string $id): string
    {
        return $this->path . DIRECTORY_SEPARATOR . $queue . DIRECTORY_SEPARATOR . $id . '.json';
    }

    protected function reservedFile(string $queue, string $id): string
    {
        return $this->path . DIRECTORY_SEPARATOR . $queue . DIRECTORY_SEPARATOR . $id . '.reserved';
    }

    protected function readJob(string $file): ?array
    {
        $json = @file_get_contents($file);

        if ($json === false) {
            return null;
        }

        $job = json_decode($json, true);

        return is_array($job) ? $job : null;
    }

    protected function writeJob(string $file, array $job): void
    {
        file_put_contents($file, json_encode($job), LOCK_EX);
    }

    protected function ensureQueueDir(string $queue): void
    {
        $dir = $this->path . DIRECTORY_SEPARATOR . $queue;

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    protected function getQueue($queue)
    {
        return $queue ?: $this->default;
    }
}
