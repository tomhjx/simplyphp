<?php

namespace Core\Foundation;

use Core\Database\ConnectionFactory;
use Core\Database\NotFoundConfigException;

class Application
{
    /**
     * 框架版本
     *
     * @var string
     */
    const VERSION = '1.2.0';

    /**
     * 项目的根目录
     *
     * @var string
     */
    private $basePath;

    private $logger;

    private $kernel;
    
    /**
     * 当前主机名
     *
     * @var string
     */
    private $host;

    /**
     * 当前使用的环境
     *
     * @var string
     */
    private $env = 'dev';

    /**
     * 是否debug模式
     *
     * @var null|bool
     */
    private $debug = null;

    private $id = 0;
    private $projectId = null;
    private $traceId = 0;
    private $eventId = 0;

    private static $instance = null;

    /**
     *
     * @param  string|null  $basePath
     * @return void
     */
    public function __construct($basePath = null)
    {
        if ($basePath) {
            $this->setBasePath($basePath);
        }

        if  (\getenv('PHP_BUSINESS_PROJECT_ENV')) {
            $this->env = \getenv('PHP_BUSINESS_PROJECT_ENV');
        } else {
            $this->env = include $this->getConfigPath('env');
        }
    }

    /**
     * 单例
     *
     * @param null $basePath
     * @return Application|null
     */
    public static function getInstance($basePath = null)
    {
        if (self::$instance) {
            return self::$instance;
        }
        self::$instance =  new self($basePath);
        return self::$instance;
    }

    /**
     * 标记当前环境
     *
     * @param string $env
     */
    public function setEnv(string $env)
    {
        $this->env = $env;
    }

    /**
     * 判断是否开发环境
     *
     * @return bool
     */
    public function isDev()
    {
        return 'dev'===$this->env;
    }

    /**
     * 标记是否使用debug模式
     *
     * @param bool $debug
     */
    public function setDebug(bool $debug)
    {
        $this->debug = $debug;
    }

    /**
     * 判断是否debug模式
     *
     * @return bool
     */
    public function isDebug()
    {
        if (!is_null($this->debug)) {
            return $this->debug;
        }

        $this->debug = false;
        $config = $this->getConfig('app');
        if ($config
            && isset($config['debug'])) {
            $this->debug = $config['debug'];
        }

        return $this->debug;
    }

    /**
     * 判断是否生产环境
     *
     * @return bool
     */
    public function isProd()
    {
        return 'prod'===$this->env;
    }

    /**
     * 获取当前环境标志
     *
     * @return string
     */
    public function getEnv()
    {
        return $this->env;
    }


    public function getKernel()
    {
        return $this->kernel;
    }

    /**
     * Get the version number of the application.
     *
     * @return string
     */
    public function version()
    {
        return static::VERSION;
    }

    protected function setBasePath($basePath)
    {
        $this->basePath = $basePath;
    }

    protected function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * Get the path to the bootstrap directory.
     *
     * @param  string  $path Optionally, a path to append to the bootstrap path
     * @return string
     */
    public function getBootstrapPath($path = '')
    {
        return $this->basePath.DIRECTORY_SEPARATOR.'bootstrap'.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     * Get the path to the application configuration files.
     *
     * @param  string  $path Optionally, a path to append to the config path
     * @return string
     */
    public function getConfigPath($path = '')
    {
        return $this->basePath.DIRECTORY_SEPARATOR.'config'.($path ? DIRECTORY_SEPARATOR.$path.'.php' : $path);
    }

    /**
     * Get the path to the application "app" directory.
     *
     * @param  string  $path Optionally, a path to append to the app path
     * @return string
     */
    public function getPath($path = '')
    {
        return $this->basePath.DIRECTORY_SEPARATOR.'app'.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     * Get the path to the application "src" directory.
     *
     * @param  string  $path Optionally, a path to append to the app path
     * @return string
     */
    public function getSrcPath($path = '')
    {
        return $this->getPath('src'.($path ? DIRECTORY_SEPARATOR.$path : $path));
    }

    /**
     * Get the path to the application "log" directory.
     *
     * @param  string  $path Optionally, a path to append to the app path
     * @return string
     */
    public function getLogPath($path = '')
    {

        return $this->getDataPath('logs').($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     * 获取本地生成文件的地址
     *
     * @param string $path
     * @return string
     */
    public function getDataPath($path = '')
    {
        return $this->basePath.DIRECTORY_SEPARATOR.'data'.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     * 获取公开目录的地址
     *
     * @param string $path
     * @return string
     */
    public function getPublicPath($path = '')
    {
        return $this->basePath.DIRECTORY_SEPARATOR.'public'.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     * 获取资源文件地址
     *
     * @param string $path
     * @return string
     */
    public function getResourcesPath($path = '')
    {
        return $this->basePath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.
            'resources'.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     * 获取视图文件地址
     *
     * @param string $path
     * @return string
     */
    public function getViewPath($path = '')
    {
        return $this->getResourcesPath('views').($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     *
     * @param string $path
     * @return string
     */
    public function getRootPath($path = '')
    {
        return $this->basePath.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     * @param $name
     * @return mixed
     */
    public function getConfig($name)
    {
        static $configs=[];
        $key = $this->getEnv().DIRECTORY_SEPARATOR.$name;
        if (isset($configs[$key])) {
            return $configs[$key];
        }
        $path = $this->getConfigPath($key);
        $bakKey = 'common'.DIRECTORY_SEPARATOR.$name;
        $bakPath = $this->getConfigPath($bakKey);
        $config = null;
        if (file_exists($path)) {
            $config = include $this->getConfigPath($key);
        } elseif (file_exists($bakPath)) {
            $config = include $this->getConfigPath($bakKey);
        }
        $configs[$key] = $config;
        return $config;
    }

    /**
     * @return \Core\Log\Logger
     */
    public function getLogger()
    {
        if ($this->logger) {
            return $this->logger;
        }
        $config = $this->getConfig('log');
        $handleClass = null;
        if (isset($config['handleClass'])) {
            $handleClass = new $config['handleClass']();
        }
        $this->logger = new \Core\Log\Logger($this, $handleClass);
        return $this->logger;
    }

    public function run($kernelClass)
    {
        if (is_string($kernelClass)) {
            $this->kernel = new $kernelClass();
        } else {
            $this->kernel = $kernelClass;
        }
        $this->kernel->run($this);
    }

    /**
     * 判断是否使用cli模式运行
     *
     * @return bool
     */
    public function isCli()
    {
        return 'cli' === \PHP_SAPI;
    }

    /**
     * 根据配置创建数据库连接
     *
     * @param $configKey
     * @return \Core\Database\Connection|\Core\Database\Connections\MySql|mixed
     */
    public function getDataBaseConnection($configKey)
    {
        static $conns = [];

        if (!empty($conns[$configKey])) {
            return $conns[$configKey];
        }

        $config = $this->getConfig('db');
        if (empty($config[$configKey])) {
            new NotFoundConfigException(sprintf('没有找到可用的 ( %s ) 数据库配置', $configKey));
        }

        $config = $config[$configKey];

        $driverConfig = [];
        if (isset($config['options'])) {
            $driverConfig = $config['options'];
        }

        if (!isset($driverConfig[\PDO::MYSQL_ATTR_INIT_COMMAND])) {
            $driverConfig[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET character_set_client=binary, character_set_connection=\'utf8\', character_set_results=\'utf8\'';
        }

        if (!isset($driverConfig[\PDO::ATTR_PERSISTENT])) {
            $driverConfig[\PDO::ATTR_PERSISTENT] = false;
        }

        $conns[$configKey] = $conn = ConnectionFactory::create('mysql',
            $config['host'], $config['port'], $config['user'], $config['password'], $config['database'],
            $driverConfig, $this->getLogger());
        return $conn;
    }

    /**
     * 返回该进程的会话id，相对唯一
     *
     * @return int|string
     */
    public function getId()
    {
        if ($this->id) {
            return $this->id;
        }
        $this->id = md5(getmypid().'|'.microtime().'|'.mt_rand(1, 999));
        return $this->id;
    }

    /**
     * 返回该链路id，优先使用上游传递的链路id，如果没传递即使用自身的进程id
     *
     * @return int|string
     */
    public function getTraceId()
    {
        if ($this->traceId) {
            return $this->traceId;
        }
        $this->traceId = $this->getId();
        return $this->traceId;
    }

    /**
     * 设置链路id
     *
     * @return int|string
     */
    public function setTraceId($traceId)
    {
        $this->traceId = $traceId;
    }

    /**
     * 获取当前主机名
     *
     * @return string
     */
    public function getHost()
    {
        if ($this->host) {
            return $this->host;
        }
        $this->host = gethostname();
        return $this->host;
    }

    /**
     * 获取项目id，用于标识不同的项目（系统编码）
     *
     * @return bool
     */
    public function getProjectId()
    {
        if (!is_null($this->projectId)) {
            return $this->projectId;
        }
        $this->projectId = 0;
        $config = $this->getConfig('project');
        if ($config
            && isset($config['id'])) {
            $this->projectId = $config['id'];
        }
        return $this->projectId;
    }

    /**
     * 设置埋点时间ID(暂时不必和前端同)
     * @param $eventId
     */
    public function setEventId($eventId)
    {
        $this->eventId = $eventId;
    }

    /**
     * @return int|string
     */
    public function getEventId()
    {
        if ($this->eventId) {
            return $this->eventId;
        }
        $this->eventId = md5(getmypid().'|'.microtime().'|'.uniqid());
        return $this->eventId;
    }

}