<?php

namespace Core\Log;

use Core\Foundation\Application;

class Logger
{
    /**
     * @var Application
     */
    protected $app;

    private $logHandler;

    private $isCli;

    private $id;

    private $level;
    private $message;
    private $time;
    private $dir;
    private $host;

    /**
     * 是否延迟写入，true为仅在进程结束前才开始落地日志
     *
     * @var bool
     */
    private $isLazyWrite;

    const DEBUG = 1;
    const INFO = 2;
    const NOTICE = 3;
    const WARNING = 4;
    const ERROR = 5;

    /**
     * The Log levels.
     *
     * @var array
     */
    protected $levels = [
        'debug' => self::DEBUG,
        'info' => self::INFO,
        'notice' => self::NOTICE,
        'warning' => self::WARNING,
        'error' => self::ERROR
    ];

    /**
     * Logger constructor.
     * @param Application $app
     */
    public function __construct(Application $app, $logHandler=null)
    {
        $this->app = $app;
        if ($logHandler) {
            $this->logHandler = $logHandler;
        } else {
            $this->logHandler = new LocalFileHandler();
        }
        $this->logHandler->setLogger($this);
        $this->isLazyWrite = true;
        $this->isCli = $app->isCli();
        if ($this->isCli) {
            $this->isLazyWrite = false;
        }
    }

    public function getId()
    {
        return $this->app->getTraceId();
    }

    public function getTime()
    {
        return $this->time;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getLevel()
    {
        return $this->level;
    }

    public function getApp()
    {
        return $this->app;
    }

    public function getDir()
    {
        return $this->app->getLogPath($this->dir);
    }

    public function getRelDir()
    {
        return $this->dir;
    }


    private function print()
    {
        echo $this->logHandler->formatMessage();
        echo "\r\n";
    }

    protected function log($level, $message, $dir='app')
    {
        $this->level = $level;
        $this->message = $message;
        $logConfig = $this->app->getConfig('log');
        $limitLevel = self::ERROR;

        if ($this->app->isDebug()) {
            $limitLevel = self::DEBUG;
        } elseif (isset($logConfig['level'])) {
            $limitLevel = $this->levels[$logConfig['level']];
        }

        if ($this->levels[$level]<$limitLevel) {
            return true;
        }
        $this->dir = $dir;
        $this->time = $time = time();
        if ($this->isCli) {
            $this->print();
        }
        $this->write();
        return true;
    }

    private function write()
    {
        return $this->logHandler->write();
    }


    /**
     * log debug
     *
     * @param $message
     */
    public function debug($message, $dir='app') {
        $this->log(__FUNCTION__, $message, $dir);
    }

    /**
     * log info
     *
     * @param $message
     */
    public function info($message, $dir='app') {
        $this->log(__FUNCTION__, $message, $dir);
    }

    /**
     * log notice
     *
     * @param $message
     */
    public function notice($message, $dir='app') {
        $this->log(__FUNCTION__, $message, $dir);
    }

    /**
     * log warning
     *
     * @param $message
     */
    public function warning($message, $dir='app') {
        $this->log(__FUNCTION__, $message, $dir);
    }

    /**
     * log error
     *
     * @param $message
     */
    public function error($message, $dir='app') {
        $this->log(__FUNCTION__, $message, $dir);
    }
}