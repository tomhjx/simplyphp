<?php

namespace Core\Support;

use Core\Foundation\Application;

class Error
{

    /**
     * 获取错误码资源包
     *
     * @param $key
     * @return array
     */
    public static function getResources($key)
    {
        static $res = [];
        if (isset($res[$key])) {
            return $res[$key];
        }

        $app = Application::getInstance();
        $errorPath = $app->getResourcesPath() . '/errors/';
        if (!empty($lang) && \is_dir($errorPath . $lang . '/')) {
            $errorPath = $errorPath . $lang . '/';
        }

        $res[$key] = include $errorPath . $key . '.php';
        return $res[$key];
    }
}
