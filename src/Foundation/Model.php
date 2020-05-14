<?php

namespace Core\Foundation;

abstract class Model
{
    private $app;

    private static $instances = [];

    /**
     * Model constructor.
     * @param Application $app
     */
    public function __construct()
    {
        $this->app = Application::getInstance();
        $this->initialize();
    }

    protected function initialize()
    {

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
}