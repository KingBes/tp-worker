<?php

namespace think\worker\watcher;

use Symfony\Component\Process\Process;
use Workerman\Timer;

class Find implements Driver
{
    protected $name;
    protected $directory;
    protected $exclude;

    public function __construct($directory, $exclude, $name)
    {
        $this->directory = $directory;
        $this->exclude   = $exclude;
        $this->name      = $name;
    }

    public function watch(callable $callback)
    {
        $ms      = 2000;
        $seconds = ceil(($ms + 1000) / 1000);
        $minutes = sprintf('-%.2f', $seconds / 60);

        $dest = implode(' ', $this->directory);

        $name = empty($this->name) ? '' : ' \( ' . join(' -o ', array_map(fn($v) => "-name \"{$v}\"", $this->name)) . ' \)';

        $command = sprintf(
            'find %s%s%s -mmin %s -type f -print',
            $dest,
            $name,
            $this->buildExcludeExpr(),
            $minutes
        );

        Timer::add($ms / 1000, function () use ($callback, $command) {
            $stdout = $this->exec($command);
            if (!empty($stdout)) {
                call_user_func($callback);
            }
        });
    }

    /**
     * 构建排除表达式。
     *
     * 文件按基名匹配（-name），目录按前缀匹配（-path）。
     * 多个条件必须用 -o 连接：用 -and 的话「排除 A 和 B」会退化成
     * 「排除同时是 A 且是 B 的文件」，即恒真条件，排除完全失效。
     *
     * @return string 空字符串表示无排除项
     */
    protected function buildExcludeExpr(): string
    {
        $exprs = [];

        foreach ((array) $this->exclude as $path) {
            $path = rtrim((string) $path, '/');

            if ($path === '') {
                continue;
            }

            $exprs[] = is_dir($path)
                ? sprintf('-path "%s/*"', $path)
                : sprintf('-name "%s"', $path);
        }

        if (empty($exprs)) {
            return '';
        }

        return ' -not \( ' . join(' -o ', $exprs) . ' \)';
    }

    public function exec($command)
    {
        $process = Process::fromShellCommandline($command);
        $process->run();
        if ($process->isSuccessful()) {
            return $process->getOutput();
        }
        return false;
    }

}
