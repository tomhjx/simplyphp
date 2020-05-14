<?php

namespace Core\Http;

use Core\Foundation\Application;
use Core\Http\Request;

class RpcKernel
{
    public function run(Application $app)
    {
        try {
            $request = Request::capture();
            $requestPathArr = $request->segments();
            $actionName = array_pop($requestPathArr);
            foreach ($requestPathArr as $key => $item) {
                $requestPathArr[$key] = ucfirst($item);
            }
            $traceId = $request->getHeader('X_HEAD_REQUEST_ID');
            $app->setTraceId($traceId);
            
            $controllerName = implode(".", $requestPathArr);
            array_unshift($requestPathArr, 'RpcControllers');
            $controllerClass = '\\App\\'.implode('\\', $requestPathArr);

            $controller = new $controllerClass($app, $request, $controllerName, $actionName);
            $server = new \Hprose\Http\Server();
            $server->addFunction(function() use ($controller, $actionName) {
                return $controller->callActionMethod($actionName, func_get_args());
            }, $actionName);
            $server->start();

        } catch (\Throwable $e) {
            $app->getLogger()->error($e->__toString());
            echo $e->getMessage();
        }

    }
}
