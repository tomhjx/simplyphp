<?php

namespace Core\Http;

use Core\Foundation\Application;

class Kernel
{
    private $controllerNameSpace = '\App\Controllers';
    private $cacheExpireSeconds = 0;

    public function setControllerNameSpace($name)
    {
        $this->controllerNameSpace = $name;
    }

    public function setCacheExpires(int $seconds)
    {
        $this->cacheExpireSeconds = $seconds;
    }


    public function run(Application $app)
    {
        if ($app->isProd()) {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_WARNING);
        }
        
        try {

            $request = Request::capture();
            $requestPathArr = $request->segments();
            if (empty($requestPathArr)) {
                $requestPathArr = ['home', 'index'];
            } elseif (empty($requestPathArr[1])) {
                $requestPathArr[] = 'index';
            }

            $traceId = $request->getHeader('X_HEAD_REQUEST_ID');
            $app->setTraceId($traceId);
    
            $actionName = array_pop($requestPathArr);
            foreach ($requestPathArr as $key => $item) {
                $requestPathArr[$key] = ucfirst($item);
            }
            $controllerName = implode(".", $requestPathArr);
            $controllerClass = $this->controllerNameSpace.'\\'.implode('\\', $requestPathArr);
            $controller = new $controllerClass($app, $request, $controllerName, $actionName);
            $controller->setCacheExpires($this->cacheExpireSeconds);
            $response = $controller->callActionMethod($actionName, []);
            $response->send();

        } catch (\Throwable $e) {
            $app->getLogger()->error($e->__toString());
            echo $e->getMessage();
        }
    }
}
