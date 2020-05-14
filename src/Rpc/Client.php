<?php

namespace Core\Rpc;

use Core\Foundation\Application;

class Client
{
    private $timeout = null;
    private $headers = [];
    private $defaultTimeout = 30000;
    private $app;
    private static $instances = [];

    /**
     * Model constructor.
     * @param Application $app
     */
    public function __construct()
    {
        $this->app = Application::getInstance();
    }

    /**
     * @return Application
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * 单例
     *
     * @param Application $app
     * @return null|static
     */
    public static function getInstance()
    {
        $className = get_called_class();
        if (isset(self::$instances[$className])) {
            return self::$instances[$className];
        }
        return self::$instances[$className] = new static();
    }

    /**
     * 设置超时时间
     *
     * @param 毫秒 $ms
     * @return void
     */
    public function setTimeOut($ms)
    {
        $this->timeout = $ms;
    }

    /**
     * 设置 header
     *
     * @param string $key 所传 header 的键
     * @param string $value 所传 header 的值
     */
    public function setHeader(string $key, string $value)
    {
        if ($key) {
            $this->headers[$key] = $value;
        }
    }

    public function call($configKey, $method, $params = [])
    {
        $client = $this->create($configKey, $method);
        if (is_array($method)) {
            $method = \end($method);
        }

        $timeout = $this->defaultTimeout;
        if ($this->timeout) {
            $timeout = $this->timeout;
        }
        $this->timeout = null;
        $client->setTimeout($timeout);

        $this->setConfigHeaders();
        if ($this->headers) {
            foreach ($this->headers as $hk => $hv) {
                $client->setHeader($hk, $hv);
            }
            $this->headers = [];
        }

        try {
            $r = \call_user_func_array([$client, $method], $params);
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'Operation timed out after')) {
                throw new TimeoutException($client->uri, $method, $params, $e);
            } else {
                throw new Exception($client->uri, $method, $params, $e);
            }
        }
        if ($r['code'] > 0) {
            throw new LangException($client->uri, $method, $params, $r['code'], $r['msg']);
        }
        return $r;
    }

    private function create($configKey, $path)
    {
        if (is_array($path)) {
            $path = \implode('/', $path);
        }
        $config = $this->getApp()->getConfig('rpc');
        $config = $config[$configKey];
        $host = $config['host'];
        $url = 'http://' . $host . '/' . $path;
        $client = \Hprose\Client::create($url, false);
        return $client;
    }

    private function setConfigHeaders()
    {
        if (!empty($appConfig['rpcHeaders']) && \is_array($appConfig['rpcHeaders'])) {
            foreach ($appConfig['rpcHeaders'] as $k => $v) {
                $this->headers[$k] = $v;
            }
        }
        $app = $this->getApp();
        $this->headers['X-HEAD-REQUEST-ID'] = $app->getTraceId();
        $this->headers['X-HEAD-PROJECT-ID'] = $app->getProjectId();
        $catContextHeader = $this->getCatContextHeader();
        if(!is_null($catContextHeader)) {
            $this->headers['catcontextheader'] = $catContextHeader;
        }
    }

    private  function getCatContextHeader(){
        $catContextHeader = null;
        if(function_exists("kg_hook_get_hex_ip") && function_exists("kg_hook_next_mid") &&
            function_exists("kg_hook_get_ip") && function_exists("kg_hook_root_mid") &&
            function_exists("kg_hook_mid"))
        {
            static $domain = null;
            if(is_null($domain)) {
                $domain = isset($_SERVER["SERVER_NAME"]) ? $_SERVER["SERVER_NAME"] : "unknown_server_name";
            }
            $nextMessageId = $domain.'-' . kg_hook_get_hex_ip() . '-' . (int)(time() / 3600) . '-' . kg_hook_next_mid();
            $catContextHeader = $domain . ';' . kg_hook_get_ip() . ';;;' . kg_hook_root_mid() . ';' . kg_hook_mid() . ';' . $nextMessageId;
        }
        return $catContextHeader;
    }

}
