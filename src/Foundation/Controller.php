<?php

namespace Core\Foundation;

use Core\Http\HtmlResponse;
use Core\Http\JsonResponse;
use Core\Http\RedirectResponse;
use Core\Http\Request;
use Core\Http\Response;

abstract class Controller
{
    /**
     * @var Application
     */
    private $app;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var string
     */
    private $viewName;

    private $viewer;

    private $assigns;

    private $name;
    private $actionName;

    private $filters;

    private $cacheExpireSeconds;

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
        $this->viewName = $this->name.'.'.$this->actionName;
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

    protected function addCorsResponseHeaders(Response $resp) : bool
    {
        $request = $this->getRequset();
        $config = $this->getApp()->getConfig('crossOrigin');
        $origin = $request->getHeader('ORIGIN');
        if (empty($origin)) {
            return false;
        }

        if (isset($config['allowHosts'])
            && !in_array($origin, $config['allowHosts'])) {
            return false;
        }
        $resp->headers['Access-Control-Allow-Origin'] = $origin;
        $resp->headers['Access-Control-Allow-Methods'] = 'POST, GET, OPTIONS';
        $resp->headers['Access-Control-Allow-Headers'] = 'Content-Type';
        $corsAllowHeader = $this->corsAllowHeader();
        $resp->headers['Access-Control-Allow-Headers'] .= ','.implode(',', $corsAllowHeader);
        $resp->headers['Access-Control-Max-Age'] = 86400;
        return true;
    }

    /**
     * 设置缓存的有效秒数
     *
     * @param integer $seconds
     * @return void
     */
    public function setCacheExpires(int $seconds)
    {
        $this->cacheExpireSeconds = $seconds;
    }

    protected function addCacheResponseHeaders(Response $resp) : bool
    {
        $seconds = $this->cacheExpireSeconds;
        if (empty($seconds)) {
            header('Cache-Control: no-cache, must-revalidate');
            return false;
        }
        header('Cache-Control: public, max-age='.$seconds);
        return true;
    }
    
    /**
     * 以json格式返回
     *
     * @param array $data
     * @param int   $httpCode
     * @return JsonResponse
     */
    public function json(array $data=[], int $httpCode=200) : JsonResponse
    {
        $config = $this->getApp()->getConfig('crossOrigin');
        $callBackName = '_callback';
        if (isset($config['jsonpCallbackUrlParamName'])) {
            $callBackName = $config['jsonpCallbackUrlParamName'];
        }
        $request = $this->getRequset();
        $headers = [];
        $resp = new JsonResponse($data, $httpCode, $headers);
        $this->addCorsResponseHeaders($resp);
        $this->addCacheResponseHeaders($resp);
        $callBack = $request->get($callBackName);
        $resp->setCallback($callBack);
        return $resp;
    }

    /**
     * 以字符串返回
     *
     * @param string $data
     * @param int   $httpCode
     * @return Response
     */
    public function resp(string $data='', int $httpCode=200) : Response
    {
        $request = $this->getRequset();
        $headers = [];
        $resp = new Response($data, $httpCode, $headers);
        $this->addCorsResponseHeaders($resp);
        $this->addCacheResponseHeaders($resp);
        return $resp;
    }

    

    /**
     * 跳转url
     *
     * @param null|string $url
     * @param int $status
     * @param array $headers
     * @return RedirectResponse
     */
    public function redirect(?string $url, int $status = 302, array $headers = array()) : RedirectResponse
    {
        return new RedirectResponse($url, $status, $headers);
    }


    /**
     * @param string $name
     */
    public function setViewName(string $name)
    {
        $this->viewName = $name;
    }

    public function assign($key, $value)
    {
        $this->assigns[$key] = $value;
    }

    /**
     * 创建视图
     *
     */
    public function view($title=null)
    {
        $viewer = $this->getViewer();
        if ($title) {
            $viewer->setTitle($title);
        }
        $content = $viewer->render();
        return new HtmlResponse($content);
    }

    /**
     * 获取视图对象
     *
     * @return Viewer
     */
    public function getViewer()
    {
        if ($this->viewer) {
            return $this->viewer;
        }
        $this->viewer = new Viewer($this->getApp(), $this->viewName);
        return $this->viewer;
    }


    /**
     * Execute an action on the controller.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return \Core\Http\Response
     */
    public function callActionMethod($method, $parameters)
    {
        $reqId = $this->getApp()->getTraceId();
        $eventId  = $this->getApp()->getEventId();
        $timestamp = \Core\Support\Time::getInstance()->format();

        $method = $method.'Action';
        $tryFilterMethod = 'exist'.ucfirst($method).'Filter';
        try {

            if (\in_array($this->getRequset()->getMethod(), ['OPTIONS'])) {
                return $this->resp();
            }

            if (!method_exists($this, $tryFilterMethod)
                || $this->$tryFilterMethod()) {
                    $this->filter();
            }

            $ret = call_user_func_array([$this, $method], $parameters);
            if ($ret instanceof Response) {
                return $ret;
            }
            return $this->json(['data'=>$ret, 'code'=>0, 'msg'=>'', 'timestamp'=>$timestamp, 'reqid'=>$reqId,'eventid'=>$eventId,]);
        } catch (\Throwable $e) {
            $this->setCacheExpires(0);
            $this->getApp()->getLogger()->error($e->__toString());
            $code = $e->getCode();
            if (empty($code)) {
                $code = 1;
            }
            return $this->json([
                'data' => empty($e->extData) ? [] : $e->extData,
                'code' => $code,
                'msg' => $e->getMessage(),
                'timestamp' => $timestamp,
                'reqid' => $reqId,
                'eventid' => $eventId
            ]);
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

    /*
     * 获取默认的设定header头
     */
    private function corsAllowHeader()
    {
        $corsAllowHeader = [
            'X-HEAD-REQUEST-ID', 'X-HEAD-AUTH-TOKEN', 'X-HEAD-COUNTRY', 'X-HEAD-COUNTRY-ID', 'X-HEAD-STATE-ID',
            'X-HEAD-CITY-ID', 'X-HEAD-APP-VERSION', 'X-HEAD-APP-ID', 'X-HEAD-APP-CHANNEL', 'X-HEAD-PHONE-MODEL',
            'X-HEAD-PLATFORM', 'X-HEAD-OS-VERSION', 'X-HEAD-DEVICE-ID', 'X-HEAD-IMEI', 'X-HEAD-DEVICE-NETWORK',
            'X-HEAD-LOCATION-LNG', 'X-HEAD-LOCATION-LAT', 'X-HEAD-CONTENT-LANGUAGE', 'X-HEAD-CURRENCY',
            'X-HEAD-UTM-SOURCE', 'X-HEAD-UTM-MEDIUM','X-HEAD-ABTEST','X-HEAD-ABTEST-DEFAULT','X-HEAD-EVENT-ID'
        ];
        $config = $this->getApp()->getConfig('crossOrigin');
        if (isset($config['corsAllowHeaders'])) {
            $corsAllowHeader = array_merge($corsAllowHeader, $config['corsAllowHeaders']);
        }
        return $corsAllowHeader;
    }

}