<?php

namespace Core\Log;

class RemoteHandler
{
    private $logger;

    /**
     * The Log levels.
     *
     * @var array
     */
    protected $levels = [
        'debug' => LOG_DEBUG,
        'info' => LOG_INFO,
        'notice' => LOG_NOTICE,
        'warning' => LOG_WARNING,
        'error' => LOG_ERR,
        'crit' => LOG_CRIT,
        'alert' => LOG_ALERT,
        'emerg' => LOG_EMERG,
    ];


    /**
     * 传递logger父类
     *
     * @param Logger $logger
     * @return void
     */
    public function setLogger(Logger $logger)
    {
       $this->logger = $logger; 
    }

    /**
     * 格式化日志内容
     *
     * @return string
     */
    public function formatMessage()
    {
        $message = $this->logger->getMessage();
        if (is_array($message)
            ||is_object($message)) {
            $message = var_export($message, true);
        }
        $contents = [];
        $contents['time'] = $this->logger->getTime();
        $contents['level'] = $this->logger->getLevel();
        $contents['id'] = $this->logger->getId();
        $contents['content'] = $message;
        $contents['host'] = $this->logger->getApp()->getHost();
        $contents['project'] = $this->logger->getApp()->getProjectId();
        $contents['dir'] = $this->logger->getRelDir();
        $contents = \json_encode($contents);
        return $contents;
    }

    /**
     * 写日志
     *
     * @return string
     */
    public function write()
    {
        $contents = $this->formatMessage();
        $level = LOG_INFO;
        if (isset($this->levels[$this->logger->getLevel()])) {
            $level = $this->levels[$this->logger->getLevel()];
        }
        syslog(LOG_LOCAL1|$level, $contents);
    }

}