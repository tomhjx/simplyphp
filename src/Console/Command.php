<?php

namespace Core\Console;

use Core\Foundation\Application;
use Core\Support\Process;

abstract class Command
{
    /**
     * @var Application
     */
    private $app;

    private $arguments = [];
    private $argumentDescs = [];
    private $inputArguments = [];
    private $optionDescs = [];
    private $options = [];
    private $help = '';
    private $path = '';
    private $action = '';
    private $timeout = 0;

    private $startTime;

    private $startUsedMemory;

    private $inputOptions = [];

    public function getArgument($key, $default=null)
    {
        if (isset($this->arguments[$key])) {
            return $this->arguments[$key];
        }
        return $default;
    }

    public function getOption($key, $default=null)
    {
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }
        return $default;
    }

    public function setHelp($help)
    {
        $this->help = $help;
    }

    /**
     * @return Application
     */
    protected function getApp()
    {
        return $this->app;
    }

    public function __construct(Application $app, $path, $action, $argv)
    {
        $this->startTime =  microtime(true);
        $this->startUsedMemory = memory_get_usage();
        $this->app = $app;
        $this->inputArguments = $argv;
        $this->path = $path;
        $this->action = $action;
        $this->defined();
        $this->declareInput();
        $this->printHelp();
        $this->parseInput();
        $this->initialize();
    }

    protected function declareInput()
    {

    }

    private function defined()
    {
        $this->addOption('e', '环境变量：dev（开发环境）, prod（生产环境）', $this->app->getEnv())
        ->addOption('debug', '是否使用debug模式，1：使用，0：不使用', intval($this->app->isDebug()))
        ->addOption('always', '设为1则以常驻方式运行，进程退出时会被拉起', 0)
        ->addOption('timeout', '超时时间，单位秒，0即不限制', $this->getTimeOut());

        register_shutdown_function(function () {
            $this->onShutDown();
        });
    }


    private function printHelp()
    {
        if (array_intersect($this->inputArguments, ['-v', '--version'])) {
            printf("\r\n%s\r\n", $this->app::VERSION);
            exit();
        }

        if (array_intersect($this->inputArguments, ['-h', '--help'])) {

            printf("\r\n命令：\r\n%s %s %s \r\n", PHP_BINARY, $this->path, $this->action);

            printf("\r\n描述：\r\n%s\r\n", $this->help);

            echo "参数：\r\n";
            foreach ($this->options as $name => $value) {

                printf("    -%s%s  （%s）  %s\r\n",
                    (strlen($name)>1?'-':''), $name,
                    is_null($value)?'必填':('可选，默认值：'.$value),
                    $this->optionDescs[$name]);
            }

            exit();
        }
    }

    /**
     * 声明参数
     *
     * @param string $name 参数名称
     * @param string $desc 参数描述
     * @param string|null $default  默认值，该参数为null时，参数为必填
     * @return $this
     */
    public function addArgument(string $name, string $desc, string $default = null)
    {
        $this->arguments[$name] = $default;
        $this->argumentDescs[$name] = $desc;
        return $this;
    }

    /**
     * 声明配置项
     *
     * @param string $name 参数名称
     * @param string $desc 参数描述
     * @param string|null $default  默认值，该参数为null时，参数为必填
     * @return $this
     */
    public function addOption(string $name, string $desc, string $default = null)
    {
        $this->options[$name] = $default;
        $this->optionDescs[$name] = $desc;
        return $this;
    }

    public function setOption(string $name, string $value)
    {
        $this->options[$name] = $value;
        return $this;
    }

    public function setDefaultOption(string $name, string $value)
    {
        if (isset($this->inputOptions[$name])) {
            return $this;
        }
        return $this->setOption($name, $value);
    }

    public function initialize()
    {

    }

    /**
     * 对输入做解析
     *
     */
    public function parseInput()
    {
        $argv = $this->inputArguments;
        $inputOptions = [];
        $inputArguments = [];
        foreach ($argv as $item) {
            if (0===stripos($item, '--')) {
                list($name, $value) = explode('=', $item);
                $name = ltrim($name, '--');
                $inputOptions[$name] = $value;
            } elseif (0===stripos($item, '-')) {
                list($name, $value) = explode('=', $item);
                $name = ltrim($name, '-');
                $inputOptions[$name] = $value;
            } else {
                $inputArguments[] = $item;
            }
        }

        $index = -1;
        foreach ($this->arguments as $key => $item) {
            $index++;
            if (isset($inputArguments[$index])) {
                $this->arguments[$key] = $inputArguments[$index];
                continue;
            }
            if (is_null($item)) {
                throw new \InvalidArgumentException($key.' 参数不能为空');
            }
        }

        foreach ($this->options as $key => $item) {
            if (isset($inputOptions[$key])) {
                $this->options[$key] = $inputOptions[$key];
                continue;
            }
            if (is_null($item)) {
                throw new \InvalidArgumentException($key.' 选项不能为空，格式：-'.(strlen($key)>1?'-':'').$key.'=?');
            }
        }

        $this->inputOptions = $inputOptions;
    }

    public function writeln($str)
    {
        echo $str;
        echo "\r\n";
    }

    public function run()
    {
        echo "请重载".__METHOD__.'方法，执行具体逻辑';
        exit();
    }


    public function getRaw()
    {
        return sprintf('%s %s %s %s',
            PHP_BINARY, $this->path, $this->action, implode(' ', $this->inputArguments));
    }


    /**
     * 从标准输入流获取内容
     *
     * @return bool|string
     */
    public function getStdInput()
    {
        return fgets(STDIN);
    }

    protected function setTimeOut($timeout)
    {
        $this->timeout = $timeout;
    }

    protected function getTimeOut()
    {
        return $this->timeout;
    }


    private function runWithTimeOut()
    {
        $cmd = $this->getRaw();
        $cmd .= " --timeout=0";

        return $this->runWithProcess($cmd,
                $this->getOption('timeout'),
                function ($finished) {
                    if (2==$finished['status']) {
                        $this->log('error', sprintf("%s\r\n%s s 超时", $finished['cmd'], $finished['endTime']-$finished['startTime']));
                        return;
                    }
                    $stdout = $finished['stdout'];
                    $stderr = $finished['stderr'];
                    if ($stdout) {
                        echo $stdout."\r\n";
                    }
                    if ($stderr) {
                        $this->log('error', sprintf("%s\r\n%s", $finished['cmd'], $stderr));
                    }
            });
    }

    protected function beforeRun()
    {

    }

    protected function afterRun()
    {

    }

    protected function onShutDown()
    {

    }

    private function runWithProcess($cmd, $timeout, $callback)
    {
        $setting = [];
        $setting['cmd'] = $cmd;
        $setting['timeout'] = $timeout;
        $setting['callback'] = $callback;

        $list = [];
        $list[] = $setting;
        return Process::concurrent($list);
    }


    private function runWithAlways()
    {
        $cmd = $this->getRaw();
        $cmd .= " --always=0 --timeout=0";

        while (true) {

            $this->runWithProcess($cmd,
                $this->getOption('timeout'),
                function ($finished) {

                    $this->log('debug',sprintf("进程停止信号 %s \r\n", $finished['stopsig']));

                    if (2==$finished['status']) {
                        $this->log('error',
                            sprintf("%s\r\n%s s 超时", $finished['cmd'], $finished['endTime']-$finished['startTime'])
                        );
                        return;
                    }
                    $stdout = $finished['stdout'];
                    $stderr = $finished['stderr'];
                    if ($stdout) {
                        echo $stdout."\r\n";
                    }
                    if ($stderr) {
                        $this->log('error', sprintf("%s\r\n%s", $finished['cmd'], $stderr));
                    }
                });



            $this->log('info',
                sprintf("耗时 %s s, 内存使用了 %s MB, 开始至今累积使用了内存 %s MB",
                microtime(true)-$this->getStartTime(),
                    round(memory_get_usage()/1024, 2),
                    round((memory_get_usage()-$this->getStartUsedMemory())/1024, 2)
                )
            );

            usleep(200000);

        }

    }


    public final function getStartTime()
    {
        return $this->startTime;
    }

    public final function getStartUsedMemory()
    {
        return $this->startUsedMemory;
    }

    public final function execute()
    {
        $this->beforeRun();

        $this->log('debug', '开始执行');

        try {

            if ($this->getOption('always')) {
                $this->runWithAlways();
            } else if ($this->getOption('timeout')>0) {
                $this->runWithTimeOut();
            } else {
                $this->run();
            }

        } catch (\Throwable $e) {
            $this->log('error', $e->__toString());
            throw $e;
        }



        $this->log('debug', '执行完毕');
        $this->afterRun();
    }

    protected function log($level, $msg)
    {
        $this->getApp()->getLogger()->$level($msg, 'command');
    }

    protected function output($msg)
    {
        echo $msg;
        echo "\r\n";
    }

}