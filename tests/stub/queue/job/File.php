<?php

namespace think\queue\job;

use think\App;
use think\queue\connector\File as FileQueue;
use think\queue\Job;

/**
 * 文件队列的任务封装，配合 think\queue\connector\File 使用（仅测试用）。
 */
class File extends Job
{
    /** @var FileQueue */
    protected $file;

    /** @var object */
    protected $job;

    public function __construct(App $app, FileQueue $file, $job, $connection, $queue)
    {
        $this->app        = $app;
        $this->job        = $job;
        $this->queue      = $queue;
        $this->file       = $file;
        $this->connection = $connection;
    }

    public function delete()
    {
        parent::delete();

        $this->file->deleteReserved($this->job->id);
    }

    public function release($delay = 0)
    {
        parent::release($delay);

        $this->delete();

        $this->file->release($this->queue, $this->job, $delay);
    }

    public function attempts()
    {
        return (int) $this->job->attempts;
    }

    public function getJobId()
    {
        return $this->job->id;
    }

    public function getRawBody()
    {
        return $this->job->payload;
    }
}
