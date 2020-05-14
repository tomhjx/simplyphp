<?php

namespace Core\Console\Commands\Schedule;

use Core\Console\Command;

class Start extends Command
{
    public function run()
    {
        $app = $this->getApp();
        $crontabLine = sprintf('* * * * * %s %s %s >/dev/null 2>&1',
            PHP_BINARY,
            $app->getRootPath('console'),
            'core:schedule.run');
        $app->getLogger()->debug($crontabLine);

        exec('crontab -l 2>&1', $list);

        foreach ($list as $key => $line) {
            if (false!==strpos($line, 'no crontab for')) {
                unset($list[$key]);
                break;
            }

            if (strpos($line, '/console ')
                && strpos($line, ' core:schedule.run ')) {
                $list[$key] = $crontabLine;
            }
        }

        if (!in_array($crontabLine, $list)) {
            $list[] = $crontabLine;
        }

        $file = \tempnam(\sys_get_temp_dir(), 'cron');
        file_put_contents($file, implode(PHP_EOL, $list).PHP_EOL);

        exec('crontab '.$file);
    }



}
