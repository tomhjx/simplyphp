<?php

namespace Core\Exceptions;

use Core\Foundation\Application;

class LangException extends \Exception
{
    public $extData = [];

    public function __construct(int $code, array $params = [], array $data = [])
    {
        $res = \Core\Support\Error::getResources($this->_langEnv());

        $message = empty($res[$code]) ? 'This error code is not defined ! projectId: ' . $this->_projectId() : $res[$code];

        if ($params) {
            $message = vsprintf($message, $params);
        }

        $this->extData = $data;

        parent::__construct($message, $this->_genFullCode($code));
    }

    private function _langEnv()
    {
        return 'default';
    }

    private function _projectId()
    {
        $conf = Application::getInstance()->getConfig('project');
        return empty($conf['id']) ? 0 : $conf['id'];
    }

    private function _genFullCode(int $code)
    {
        return $code > 1000000 ? $code : $this->_projectId() * 1000000 + $code;
    }

}
