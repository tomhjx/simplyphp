<?php

namespace Core\Support;

class Finder
{
    /**
     * 创建目录
     *
     * @param $dir
     * @return bool
     */
    public static function mkdir($dir, $mode = 0755)
    {
        if (is_dir($dir)) {
            return true;
        }
        return mkdir($dir, $mode, true);
    }
}