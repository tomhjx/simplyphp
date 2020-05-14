<?php

namespace Core\Console\Commands\Task;

use Core\Console\Command;
use Core\Console\Task;
use Core\Support\Finder;

class Run extends Command
{

    private $taskClassName;
    private $task;
    private $taskName;
    private $runTimePath;


    public function declareInput()
    {
        $this->addArgument('name', '任务名称，欲执行 app/Tasks/My/Demo.php，则该参数为：My.Demo')
            ->addOption('schedule', '是否限制执行频率, 1:限制，0：不限制',1)
            ->addOption('params', '传递给Task->run方法的参数，;分隔','');
    }

    public function initialize()
    {
        $this->taskName = $taskName = $this->getArgument('name');
        $taskName = ucwords($taskName, '.');
        $this->taskClassName = '\\App\\Tasks\\'.str_replace('.', '\\', $taskName);
        $task = $this->getTaskInstance();
        $this->runTimePath = $this->getApp()->getDataPath('runtimes'.DIRECTORY_SEPARATOR.'task'.DIRECTORY_SEPARATOR.$taskName);
        $timeout = $task->getTimeOut();
        $this->setDefaultOption('timeout', $timeout);
    }

    /**
     * @return Task
     */
    private function getTaskInstance()
    {
        if ($this->task) {
            return $this->task;
        }
        $this->task = new $this->taskClassName($this->getApp());
        return $this->task;
    }

    protected function onShutDown()
    {
        $startTime = $this->getStartTime();
        $endTime = microtime(true);
        $logger = $this->getApp()->getLogger();
        $logger->info(sprintf("%s|%s|%s|%s s",
            $this->taskClassName,
            date('Y-m-d H:i:s',  $startTime),
            date('Y-m-d H:i:s',  $endTime),
            $endTime-$startTime
        ), 'task/history');
    }


    private function getLastRunTime()
    {
        $lastRunTime = 0;
        $runTimePath = $this->runTimePath;
        if (file_exists($runTimePath)) {
            $lastRunTime = filemtime($runTimePath);
            $this->log('debug',$this->taskClassName.' 上次执行时间是 '.date('Y-m-d H:i:s', $lastRunTime));
        }
        return $lastRunTime;
    }

    private function tryRun()
    {
        $isSchedule = $this->getOption('schedule');
        if (empty($isSchedule)) {
            return true;
        }
        $task = $this->getTaskInstance();

        $timeLimits = [];
        $timeLimits['m'] = ['月份', $task->getMonth(), 'year'];
        $timeLimits['d'] = ['月份中的第几天', $task->getDayOfMonth(), 'month'];
        $timeLimits['N'] = ['1（表示星期一）到 7（表示星期天）', $task->getDayOfWeek(), 'week'];
        $timeLimits['H'] = ['小时', $task->getHour(), 'day'];
        $timeLimits['i'] = ['分钟数', $task->getMinute(), 'hour'];

        $realTimeLimitFormats = [];
        $realTimeLimitTips = [];
        $realTimeLimits = [];
        $intervalUnit = null;

        foreach ($timeLimits as $key => $item) {
            list($cellTips, $limit, $cellIntervalUnit) = $item;
            if (is_null($limit)) {
                continue;
            }
            if (is_null($intervalUnit)) {
                $intervalUnit = $cellIntervalUnit;
            }

            $limit = str_pad($limit, 2, '0', STR_PAD_LEFT);
            $realTimeLimitFormats[] = $key;
            $realTimeLimitTips[] = sprintf('%s (%s)', $limit, $cellTips);
            $realTimeLimits[] = $limit;
        }

        $currTimes = $limitTimes ='';
        if ($realTimeLimits) {
            $currTimes = date(implode(':', $realTimeLimitFormats));
            $limitTimes = implode(':', $realTimeLimits);
            $realTimeLimitTips = implode(':', $realTimeLimitTips);
        }

        if ($currTimes!=$limitTimes) {
            $this->log('debug',sprintf('条件不符，计划执行的时间为 %s，当前时间为 %s',
                $realTimeLimitTips, $currTimes));
            return false;
        }
        $lastRunTime = $this->getLastRunTime();
        if ($intervalUnit) {
            $allowTime = \strtotime(\date('Ymd H:i:s', $lastRunTime).' +1 '.$intervalUnit);
            if (time() < $allowTime) {
                $this->log('debug', 
                '在'.date('Y-m-d H:i:s', $allowTime).'前不能执行');
                return false;
            }
        } else {
            $interval = $task->getInterval();
            if (time() - $lastRunTime < $interval) {
                $this->log('debug','上次执行时间为 '.date('Y-m-d H:i:s', $lastRunTime).', 执行间隔不足'.$interval.'秒');
                return false;
            }
        }

        return true;
    }

    public function run()
    {
        $task = $this->getTaskInstance();

        $params = $this->getOption('params');
        if ($params) {
            $params = explode(';', $params);
        } else {
            $params = [];
        }

        $class = $this->taskClassName;

        $this->log('debug','准备执行定时器 '.$class);

        Finder::mkdir(dirname($this->runTimePath));

        if (empty($this->tryRun())) {
            return false;
        }

        try {

            $this->log('debug', '更新文件修改时间 '.$this->runTimePath);
            touch($this->runTimePath);

            $this->log('debug', '开始执行定时器 '.$class);

            call_user_func_array([$task, 'run'], $params);

            $this->log('debug', '定时器执行完毕 '.$class);

        } catch (\Exception $e) {
            $this->log('debug','定时器执行失败 '.$class);
            $this->log('error', $e->__toString());
        }
    }

    protected function log($level, $msg)
    {
        $this->getApp()->getLogger()->$level($msg, 'task/run');
    }

}