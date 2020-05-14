<?php

namespace Core\Console\Commands\Schedule;

use Core\Console\Command;
use Core\Support\Process;

class Run extends Command
{
    public function declareInput()
    {
        $this->addOption('c','配置名称，配置内容是需要执行的定时器名称列表，不指定则默认执行 app/Tasks 所有定时器', '');
    }

    public function run()
    {
        $taskNames = $this->lsTasks();
        $this->runTasks($taskNames);
    }

    public function runTasks($taskNames)
    {
        $startTime = time();
        $processSettings = [];
        foreach ($taskNames as $taskName) {
            $item = [];
            $item['cmd'] = $this->getTaskCmd($taskName);
            $item['callback'] = function ($finished) use ($taskName) {
                $this->onTaskProcessFinished($taskName, $finished);
            };
            $processSettings[] = $item;
        }

        Process::concurrent($processSettings);
        $this->log('debug', sprintf('耗时 %s s', time()-$startTime));
    }


    protected function onTaskProcessFinished($taskName, $finished)
    {
        if (2==$finished['status']) {
            $this->log('error', sprintf("%s\r\n%s s 超时", $finished['cmd'], $finished['endTime']-$finished['startTime']));
            return;
        }
        $stdout = $finished['stdout'];
        $stderr = $finished['stderr'];
        if ($stdout) {
            echo $stdout."\r\n";
        }
        if ($stderr) {
            $this->log('error', sprintf("%s\r\n%s", $finished['cmd'], $stderr));
        }
    }


    public function lsTasks()
    {
        $dir = $this->getApp()->getSrcPath('Tasks');
        exec('find  '.$dir.' -name "*.php" ', $out);

        $list = [];
        foreach ($out as $item) {
            $name = substr($item, 0, -4);
            $name = str_replace($dir.'/', '', $name);
            $name = str_replace('/', '.', $name);
            $list[] = $name;
        }
        return $list;
    }

    public function getTaskCmd($taskName)
    {
        return sprintf('%s %s core:task.run %s', PHP_BINARY, $_SERVER['PHP_SELF'], $taskName);
    }

}
