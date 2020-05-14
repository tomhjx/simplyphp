<?php

namespace Core\Console;

use Core\Foundation\Application;

abstract class Task
{
    /**
     * @var Application
     */
    private $app;

    /**
     * Task constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @return Application
     */
    public function getApp()
    {
        return $this->app;
    }

    public function getInterval()
    {
        return 60;
    }

    public function getHour()
    {
        return null;
    }

    public function getMinute()
    {
        return null;
    }

    public function getDayOfMonth()
    {
        return null;
    }
    public function getDayOfWeek()
    {
        return null;
    }

    public function getMonth()
    {
        return null;
    }


    public function getTimeOut()
    {
        return 0;
    }

    public function run()
    {

    }

    protected function log($level, $msg)
    {
        $this->getApp()->getLogger()->$level($msg, 'task/run');
    }

}