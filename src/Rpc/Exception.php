<?php

namespace Core\Rpc;
use Core\Foundation\Application;

class Exception extends \Exception
{
    protected $callServer = '';
    protected $callMethod = '';
    protected $callParams = [];

    /**
     * Create a new rpc exception instance.
     *
     * @param  string  $server
     * @param  string  $method
     * @param  array  $params
     * @param  \Exception $previous
     * @return void
     */
    public function __construct($server, $method, array $params, $previous)
    {
        parent::__construct('', 0, $previous);

        $this->callServer = $server;
        $this->callMethod = $method;
        $this->callParams = $params;
        $this->code = $previous->getCode();
        $this->message = $this->formatMessage($server, $method,  $params, $previous);
    }

    /**
     * 格式化异常内容
     *
     * @param  string  $server
     * @param  string  $method
     * @param  array  $params
     * @param  \Exception $previous
     * @return string
     */
    protected function formatMessage($server, $method, $params, $previous)
    {
        return $previous->getMessage().' (id: '.$this->getId().', server: '.$server.', method: '.$method.', params: '.json_encode($params).')';
    }

    public function getCallServer()
    {
        return $this->callServer;
    }

    public function getCallMethod()
    {
        return $this->callMethod;
    }

    public function getCallParams()
    {
        return $this->callParams;
    }

    public function getId()
    {
        return Application::getInstance()->getTraceId();
    }
}
