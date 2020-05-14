<?php

namespace Core\Support;

class Process
{
    /**
     * 执行命令，支持批量执行，批量执行过程中子进程间采用的是并行方案，可以通过回调函数来即时完成执行后事务
     *
     * @param array     $settings
     *        string    $settings[]['cmd']        命令行
     *        int       $settings[]['timeout']    超时时间，单位秒
     *        callable  $settings[]['callback']   回调函数，参数跟返回的数组元素一致
     * @return array    $finishedQueues
     *        string    $finishedQueues[]['cmd']       命令行
     *        int       $finishedQueues[]['pid']       子进程id
     *        int       $finishedQueues[]['status']    1：正常退出，2：超时
     *        string    $finishedQueues[]['stderr']    错误内容
     *        string    $finishedQueues[]['stdout']    输出内容
     */
    public static function concurrent($settings)
    {
        if (empty($settings)) {
            return [];
        }
        $awaitQueues = [];
        foreach ($settings as $key => $item) {

            $cmd = $item['cmd'];
            $env = null;

            $pipeSpec = [
                0 => ['pipe', 'r'],
                1 => STDOUT,
                2 => ['pipe', 'w'],
            ];
            $pipes = [];

            $process = \proc_open(
                $cmd,
                $pipeSpec,
                $pipes,
                null,
                $env
            );

            if (!\is_resource($process)) {
                continue;
            }

            if (empty($item['timeout'])) {
                $item['timeout'] = 0;
            }

            $item['resource'] = $process;
            $item['pipes'] = $pipes;
            $item['stdout'] = '';
            $item['stderr'] = '';
            $item['startTime'] = time();
            $awaitQueues[] = $item;

            \fclose($pipes[0]);
        }

        $finishedQueues = [];
        while ($awaitQueues) {
            usleep(200000);

            $item = current($awaitQueues);
            if (empty($item)) {
                $item = reset($awaitQueues);
            }
            $currIndex = key($awaitQueues);
            next($awaitQueues);
            $process = $item['resource'];
            $pipes = $item['pipes'];
            $timeout = $item['timeout'];
            $startTime = $item['startTime'];
            $isTimeOut = false;

            $processInfo = \proc_get_status($process);

            $currTime = time();

            if ($timeout>0) {
                list($pipes, $stdout, $stderr) = self::getStreamContents($pipes, 0);


                if (is_string($stdout)) {
                    $item['stdout'] .= $stdout;
                }
                if (is_string($stderr)) {
                    $item['stderr'] .= $stderr;
                }
                $item['pipes'] = $pipes;

                $isTimeOut = ($currTime-$startTime>=$timeout);
            }

            if ($processInfo['running']
                && empty($isTimeOut)) {
                $awaitQueues[$currIndex] = $item;
                continue;
            }

            if ($timeout<=0) {
                list($pipes, $item['stdout'], $item['stderr']) = self::getStreamContents($pipes);
            }

            $subPid = $processInfo['pid'];
            $finished = [];
            $finished['cmd'] = $item['cmd'];
            $finished['pid'] = $subPid;
            $finished['startTime'] = $item['startTime'];
            $finished['endTime'] = $currTime;
            $finished['stderr'] = $item['stderr'];
            $finished['stdout'] = $item['stdout'];
            $finished['status'] = 1;
            $finished['stopsig'] = $processInfo['stopsig'];

            if ($isTimeOut) {
                $finished['status'] = 2;
                \proc_terminate($process, 9);
            } else {
                \proc_close($process);
            }

            unset($awaitQueues[$currIndex]);

            if (isset($item['callback'])) {
                $item['callback']($finished);
            }

            $finishedQueues[] = $finished;
        }

        return $finishedQueues;
    }

    public static function getStreamContents(array $pipes, int $awaitTime=-1)
    {
        $stdout = $stderr = '';
        if ($awaitTime<0) {
            if (isset($pipes[1])) {
                $stdout = \stream_get_contents($pipes[1]);
                \fclose($pipes[1]);
            }
            if (isset($pipes[2])) {
                $stderr = \stream_get_contents($pipes[2]);
                \fclose($pipes[2]);
            }
            return [$pipes, $stdout, $stderr];
        }

        unset($pipes[0]);
        $r = $pipes;
        $w = null;
        $e = null;
        $n = @\stream_select($r, $w, $e, $awaitTime);
        if ($n === false) {
            return [$pipes, false, false];
        }

        if ($n === 0) {
            return [$pipes, null, null];
        }

        if ($n<0) {
            return [$pipes, $stdout, $stderr];
        }

        foreach ($r as $pipe) {
            $pipeOffset = 0;

            foreach ($pipes as $i => $origPipe) {
                if ($pipe === $origPipe) {
                    $pipeOffset = $i;

                    break;
                }
            }

            if (!$pipeOffset) {
                break;
            }

            $line = \fread($pipe, 8192);

            if ($line === '') {
                \fclose($pipes[$pipeOffset]);

                unset($pipes[$pipeOffset]);
            } else {
                if ($pipeOffset === 1) {
                    $stdout .= $line;
                } else {
                    $stderr .= $line;
                }
            }
        }

        return [$pipes, $stdout, $stderr];
    }


}