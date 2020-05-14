<?php

namespace Core\Rpc;

class LangException extends Exception
{
    /**
     * Create a new rpc exception instance.
     *
     * @param  string  $sql
     * @param  array  $bindings
     * @param  \Exception $previous
     * @return void
     */
    public function __construct($server, $method, array $params, $code, $message)
    {
        $this->callServer = $server;
        $this->callMethod = $method;
        $this->callParams = $params;
        $this->code = $code;
        $this->message = $message;
    }
}
