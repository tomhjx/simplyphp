<?php

namespace Core\Console\Commands\System;


use Core\Console\Command;

class Check extends Command
{
    public function run()
    {
        $app = $this->getApp();
        printf("版本：%s\r\n", $app::VERSION);
        printf("当前环境：%s\r\n", $app->getEnv());
    }
}