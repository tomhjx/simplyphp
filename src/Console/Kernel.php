<?php

namespace Core\Console;

use Core\Foundation\Application;

class Kernel
{
    public function run(Application $app)
    {
        if ($app->isProd()) {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_WARNING);
        }

        $argv = $_SERVER['argv'];
        $path = array_shift($argv);
        $class = $action = array_shift($argv);

        $namespace = 'App';

        if (stripos($action, ':')) {
            list($namespace, $class) = explode(':', $action);
            $namespace = ucfirst($namespace);
        }

        $alias = [];
        $alias['Core'] = 'Core\\Console';
        if (isset($alias[$namespace])) {
            $namespace = $alias[$namespace];
        }

        $class = explode('.', $class);
        foreach ($class as $key => $item) {
            $class[$key] = ucfirst($item);
        }

        array_unshift($class, '\\' . $namespace, 'Commands');
        $class = implode('\\', $class);

        $cmd = new $class($app, $path, $action, $argv);

        $env = $cmd->getOption('e');
        $app->setEnv($env);

        $isDebug = $cmd->getOption('debug');
        $app->setDebug(boolval($isDebug));

        $cmd->execute();
    }
}
