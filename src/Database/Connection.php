<?php

namespace Core\Database;

use PDO;

abstract class Connection
{

    /**
     * @var \PDO
     */
    protected $pdo;

    protected $dsn;
    protected $config;

    protected $connector;

    protected $queryLog = [];

    protected $loggingQueries = false;

    protected $logger = null;

    /**
     * The default fetch mode of the connection.
     *
     * @var int
     */
    protected $fetchMode = PDO::FETCH_ASSOC;

    /**
     * Create a new database connection instance.
     *
     * @param string $host
     * @param string $port
     * @param string $username
     * @param string $password
     * @param string $database
     * @param array $options
     * @return void
     */
    public function __construct($host, $port, $username, 
    $password, $database, array $config = [], $logger=null)
    {
        $this->logger = $logger;
        $dsn = "mysql:host={$host};port={$port};dbname={$database}";

        $this->connector = function () use ($dsn, $username, $password, $config) {
            $this->pdo = new \PDO($dsn, $username, $password, $config);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        };
        $this->connect();
    }

    /**
     * 记录日志
     *
     * @param string         $level      error|debug|info|warning|notice
     * @param string         $query
     * @param array          $bindings
     * @param Exception|null $e
     * @return bool
     */
    protected function log($level, $query, $bindings, $e=null)
    {
        if (!$this->logger) {
            return false;
        }

        $msg = 'SQL: '.$query.' '.json_encode($bindings);
        if ($e) {
            $msg .= "\r\n".$e->__toString();
        }
        $this->logger->$level($msg);
    }

    public function connect()
    {
        call_user_func($this->connector, $this);
    }

    /**
     * @return \PDO
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    public function setPdo($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Reconnect to the database if a PDO connection is missing.
     *
     * @return void
     */
    protected function reconnectIfMissingConnection()
    {
        if (is_null($this->pdo)) {
            $this->reconnect();
        }
    }

    /**
     * Reconnect to the database.
     *
     * @return void
     *
     * @throws \LogicException
     */
    public function reconnect()
    {
        $this->disconnect();
        $this->connect();
    }

    /**
     * Disconnect from the underlying PDO connection.
     *
     * @return void
     */
    public function disconnect()
    {
        $this->pdo = null;
    }

    /**
     * Bind values to their parameters in the given statement.
     *
     * @param  \PDOStatement $statement
     * @param  array  $bindings
     * @return void
     */
    public function bindValues($statement, $bindings)
    {
        foreach ($bindings as $key => $value) {
            $statement->bindValue(
                is_string($key) ? $key : $key + 1, $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
    }



    /**
     * Run a select statement and return a single result.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return mixed
     */
    public function fetchOne($query, $bindings = [])
    {
        $records = $this->fetchAll($query, $bindings);

        return array_shift($records);
    }

    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function fetchAll($query, $bindings=[])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {

            $statement = $this->getPdo()->prepare($query);
            $statement->setFetchMode($this->fetchMode);
            $this->bindValues($statement, $bindings);
            $statement->execute();

            return $statement->fetchAll();
        });

    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function execute($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {

            $statement = $this->getPdo()->prepare($query);
            $this->bindValues($statement, $bindings);
            $statement->execute();
            return $statement->rowCount();
        });
    }

    /**
     * 执行sql并返回最后插入的自增id
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function executeRetLastId($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $pdo = $this->getPdo();
            $statement = $pdo->prepare($query);
            $this->bindValues($statement, $bindings);
            $statement->execute();
            return $pdo->lastInsertId();
        });
    }
    /**
     * Run a SQL statement.
     *
     * @param  string    $query
     * @param  array     $bindings
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws QueryException
     */
    protected function runQueryCallback($query, $bindings, \Closure $callback)
    {
        // To execute the statement, we'll simply call the callback, which will actually
        // run the SQL against the PDO connection. Then we can calculate the time it
        // took to execute and log the query SQL, bindings and time in our memory.
        try {
            $result = $callback($query, $bindings);
        }

            // If an exception occurs when attempting to run a query, we'll format the error
            // message to include the bindings with SQL, which will make this exception a
            // lot more helpful to the developer instead of just the database's errors.
        catch (\Exception $e) {

            $this->log('error', $query, $bindings, $e);

            if (stripos($e->getMessage(), '1062 Duplicate entry')) {
                throw new DuplicateException(
                    $query, $bindings, $e
                ); 
            }

            throw new QueryException(
                $query, $bindings, $e
            );
        }

        return $result;
    }

    /**
     * Run a SQL statement and log its execution context.
     *
     * @param  string    $query
     * @param  array     $bindings
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws QueryException
     */
    protected function run($query, $bindings, \Closure $callback)
    {
        $this->reconnectIfMissingConnection();

        $start = microtime(true);

        // Here we will run this query. If an exception occurs we'll determine if it was
        // caused by a connection that has been lost. If that is the cause, we'll try
        // to re-establish connection and re-run the query with a fresh connection.
        try {
            $result = $this->runQueryCallback($query, $bindings, $callback);
        } catch (QueryException $e) {
            $result = $this->handleQueryException(
                $e, $query, $bindings, $callback
            );
        }

        // Once we have run the query we will calculate the time that it took to run and
        // then log the query, bindings, and execution time so we will report them on
        // the event that the developer needs them. We'll log time in milliseconds.
        $this->logQuery(
            $query, $bindings, $this->getElapsedTime($start)
        );

        return $result;
    }
    /**
     * Get the connection query log.
     *
     * @return array
     */
    public function getQueryLog()
    {
        return $this->queryLog;
    }

    /**
     * Clear the query log.
     *
     * @return void
     */
    public function flushQueryLog()
    {
        $this->queryLog = [];
    }

    /**
     * Enable the query log on the connection.
     *
     * @return void
     */
    public function enableQueryLog()
    {
        $this->loggingQueries = true;
    }

    /**
     * Disable the query log on the connection.
     *
     * @return void
     */
    public function disableQueryLog()
    {
        $this->loggingQueries = false;
    }

    /**
     * Determine whether we're logging queries.
     *
     * @return bool
     */
    public function logging()
    {
        return $this->loggingQueries;
    }

    /**
     * Log a query in the connection's query log.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @param  float|null  $time
     * @return void
     */
    public function logQuery($query, $bindings, $time)
    {
        if ($this->loggingQueries) {
            $this->queryLog[] = compact('query', 'bindings', 'time');
        }
    }

    /**
     * Get the elapsed time since a given starting point.
     *
     * @param  int    $start
     * @return float
     */
    protected function getElapsedTime($start)
    {
        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Handle a query exception.
     *
     * @param  \Exception  $e
     * @param  string  $query
     * @param  array  $bindings
     * @param  \Closure  $callback
     * @return mixed
     * @throws \Exception
     */
    protected function handleQueryException($e, $query, $bindings, \Closure $callback)
    {
        return $this->tryAgainIfCausedByLostConnection(
            $e, $query, $bindings, $callback
        );
    }



    /**
     * Handle a query exception that occurred during query execution.
     *
     * @param  QueryException  $e
     * @param  string    $query
     * @param  array     $bindings
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws QueryException
     */
    protected function tryAgainIfCausedByLostConnection(QueryException $e, $query, $bindings, \Closure $callback)
    {
        if ($this->causedByLostConnection($e->getPrevious())) {
            $this->reconnect();

            return $this->runQueryCallback($query, $bindings, $callback);
        }

        throw $e;
    }

    /**
     * Determine if the given exception was caused by a lost connection.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    protected function causedByLostConnection(\Throwable $e)
    {
        return false;
    }
}
