<?php

namespace Core\Database;

use Core\Database\Connections\MySql;

class ConnectionFactory
{
    /**
     * Create a new connection instance.
     *
     * @param  string   $driver
     * @param  \PDO|\Closure     $connection
     * @param  string   $database
     * @param  string   $prefix
     * @param  array    $config
     * @param  Logger   $logger
     * @return MySql
     *
     * @throws \InvalidArgumentException
     */
    public static function create($driver, $host, $port, $username, $password, $database, array $options = [], $logger=null)
    {
        switch ($driver) {
            case 'mysql':
                return new MySql($host, $port, $username, $password, $database, $options, $logger);
        }

        throw new \InvalidArgumentException("Unsupported driver [{$driver}]");
    }
}

