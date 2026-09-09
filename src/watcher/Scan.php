<?php

namespace think\worker\watcher;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Workerman\Timer;

class Scan implements Driver
{
    protected $finder;

    /** @var array 实际参与监听的目录，为空时跳过扫描 */
    protected $directory = [];

    protected $files = [];

    public function __construct($directory, $exclude, $name)
    {
        $this->directory = array_values(array_filter((array) $directory));

        $this->finder = new Finder();

        $this->finder->files()->name($name);

        // include 目录可能全部不存在（例如项目尚未创建 route/），
        // 此时不能调用 in([])：Finder 在无目录时迭代会抛 LogicException，直接打断启动
        if ($this->directory) {
            $this->finder->in($this->directory)->exclude($exclude);
        }
    }

    protected function findFiles()
    {
        // 无目录可监听时静默返回空，热更新退化为不生效而不是崩溃
        if (!$this->directory) {
            return [];
        }

        $files = [];
        /** @var SplFileInfo $f */
        foreach ($this->finder as $f) {
            $files[$f->getRealpath()] = $f->getMTime();
        }
        return $files;
    }

    public function watch(callable $callback)
    {
        $this->files = $this->findFiles();

        Timer::add(2, function () use ($callback) {
            $files = $this->findFiles();

            // 文件数量变化说明有文件被删除，同样需要重载
            if (count($files) !== count($this->files)) {
                call_user_func($callback);
                $this->files = $files;
                return;
            }

            foreach ($files as $path => $time) {
                if (empty($this->files[$path]) || $this->files[$path] != $time) {
                    call_user_func($callback);
                    break;
                }
            }

            $this->files = $files;
        });
    }
}
