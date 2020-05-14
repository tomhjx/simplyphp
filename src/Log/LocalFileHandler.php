<?php

namespace Core\Log;

class LocalFileHandler
{
    private $logger;

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
        $contents = date('Y-m-d H:i:s', $this->logger->getTime())."\t";
        $contents .= $this->logger->getLevel()."\t";
        $contents .= $this->logger->getId()."\t";
        $contents .= "\r\n".$message."\r\n";
        return $contents;
    }

    /**
     * 写日志
     *
     * @return string
     */
    public function write()
    {
        $dir = $this->logger->getDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $dir.DIRECTORY_SEPARATOR.date('Ymd', $this->logger->getTime()).'.log';
        $contents = $this->formatMessage();
        file_put_contents($path, $contents, FILE_APPEND);
    }

}