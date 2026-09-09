<?php

namespace app\job;

use think\queue\Job;

/**
 * 队列测试用的任务：被 worker 消费后把负载追加写入日志文件。
 */
class Demo
{
    public function fire(Job $job, $data): void
    {
        // 先删除再落盘：保证测试一旦在日志里看到该任务，任务文件必然已被清理
        $job->delete();

        file_put_contents(
            runtime_path() . 'queue_consumed.log',
            json_encode($data) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
