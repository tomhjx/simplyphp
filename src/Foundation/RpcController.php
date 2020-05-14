<?php

namespace Core\Foundation;

use Core\Http\Request;
use Core\Http\Response;

abstract class RpcController
{
    /**
     * @var Application
     */
    private $app;

    /**
     * @var Request
     */
    private $request;

    private $name;
    private $actionName;

    private $filters;

    /**
     * Controller constructor.
     * @param Application $app
     * @param Request $request
     */
    public function __construct(Application $app, Request $request, string $controllerName, string $actionName)
    {
        $this->app = $app;
        $this->request = $request;
        $this->name = $controllerName;
        $this->actionName = $actionName;
        $this->filters = [];
        $this->initialize();
    }

    protected function initialize()
    {

    }

    /**
     * 新增过滤器
     *
     * @param callable $filter
     * @return void
     */
    public function addFilter(callable $filter)
    {
        $this->filters[] = $filter;
    }
    
    /**
     * 过滤逻辑，在执行action前会被执行, 根据执行结果判断是否继续
     *
     * @return boolean
     */
    public function filter() : bool
    {
        foreach ($this->filters as $filter) {
            call_user_func_array($filter, []);
        }
        return true;
    }

    /**
     * @return Application
     */
    public final function getApp() : Application
    {
        return $this->app;
    }

    /**
     * @return Request
     */
    public final function getRequset() : Request
    {
        return $this->request;
    }

    /**
     * Execute an action on the controller.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return array
     */
    public function callActionMethod($method, $parameters)
    {
        $reqId = $this->getApp()->getTraceId();
        $method = $method.'Action';
        $tryFilterMethod = 'exist'.ucfirst($method).'Filter';
        try {
            if (!method_exists($this, $tryFilterMethod)
                || $this->$tryFilterMethod()) {
                    $this->filter();
            }

            $ret = call_user_func_array([$this, $method], $parameters);
            return ['data'=>$ret, 'code'=>0, 'msg'=>'', 'reqid'=>$reqId];
        } catch (\Throwable $e) {
            $this->getApp()->getLogger()->error($e->__toString());
            $code = $e->getCode();
            if (empty($code)) {
                $code = 1;
            }
            return [
                'data' => empty($e->extData) ? [] : $e->extData,
                'code' => $code, 
                'msg' => $e->getMessage(), 
                'reqid' => $reqId
            ];
        }
    }

    /**
     * Handle calls to missing methods on the controller.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        throw new \BadMethodCallException(sprintf(
            'Method %s::%s does not exist.', static::class, $method
        ));
    }

    /**
     * 获取当前被请求的控制器名称
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * 获取当前被请求的方法名
     *
     * @return string
     */
    public function getActionName()
    {
        return $this->actionName;
    }

}