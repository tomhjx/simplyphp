<?php

namespace Core\Database\Connections;

use Core\Database\Connection;
use Core\Support\Str;

class MySql extends Connection
{


    /**
     * Run an insert duplicate key update statement against the database.
     *
     * @param  string  $query
     * @param  array   $values
     * @param  array   $updates
     * @return int
     */
    public function insertDuplicateKeyUpdate($tableName, $values, $updates)
    {
        if (empty($values)) {
            throw new \InvalidArgumentException('插入字段不能为空。');
        }
        $valueHolder = '?';
        $count = count($values);
        if ($count>1) {
            $valueHolder .= str_repeat(',?', count($values) - 1);
        }
        $columns = '`'.implode('`,`', array_keys($values)).'`';

        $updateStatement = [];
        foreach ($updates as $field => $item) {
            $updateStatement[] = '`'.$field.'`='.$item;
        }
        $updateStatement = implode(',', $updateStatement);

        $query = 'insert into `%s` (%s) values (%s) on duplicate key update %s';
        $query = sprintf($query, $tableName, $columns, $valueHolder, $updateStatement);
        return $this->execute($query, array_values($values));
    }

    /**
     * Run an insert statement against the database.
     *
     * @param  string  $query
     * @param  array   $values
     * @param bool $isIgnore
     * @return int
     */
    public function insert($tableName, $values, $isIgnore=false)
    {
        $query = $this->insertQuery($tableName, $values, $isIgnore);
        return $this->execute($query, array_values($values));
    }
    /**
     * 执行插入sql，并返回最后插入的自增id
     *
     * @param  string  $query
     * @param  array   $values
     * @param bool $isIgnore
     * @return int
     */
    public function insertRetLastId($tableName, $values, $isIgnore=false)
    {
        $query = $this->insertQuery($tableName, $values, $isIgnore);
        return $this->executeRetLastId($query, array_values($values));
    }
    /**
     * 生成查询sql语句
     *
     * @param string $tableName
     * @param array $values
     * @param boolean $isIgnore
     * @return void
     */
    private function insertQuery(string $tableName, array $values, $isIgnore=false)
    {
        if (empty($values)) {
            throw new \InvalidArgumentException('插入字段不能为空。');
        }
        $valueHolder = '?';
        $count = count($values);
        if ($count>1) {
            $valueHolder .= str_repeat(',?', count($values) - 1);
        }
        $columns = '`'.implode('`,`', array_keys($values)).'`';
        $ignore = '';
        if ($isIgnore) {
            $ignore = 'IGNORE';
        }
        $query = 'insert %s into `%s` (%s) values (%s)';
        $query = sprintf($query, $ignore, $tableName, $columns, $valueHolder);  
        return $query;
    }

    /**
     * 批量插入，主键冲突即触发更新
     *
     * @param string    $tableName
     * @param array     $values
     * @param array     $updates
     * @return int
     */
    public function insertBatchDuplicateKeyUpdate($tableName, $values, $updates)
    {
        if (empty($values)) {
            throw new \InvalidArgumentException('插入字段不能为空。');
        }
        $first = reset($values);
        $valueHolder = '?';
        $fieldCount = count($first);
        if ($fieldCount>1) {
            $valueHolder .= str_repeat(',?', $fieldCount - 1);
        }
        $valueHolder = sprintf('(%s)', $valueHolder);
        $count = count($values);
        if ($count>1) {
            $valueHolder .= str_repeat(','.$valueHolder, $count - 1);
        }

        $columns = '`'.implode('`,`', array_keys($first)).'`';
        $query = 'insert into `%s` (%s) values %s on duplicate key update %s';

        $updateStatement = [];
        foreach ($updates as $field => $item) {
            $updateStatement[] = $field.'='.$item;
        }
        $updateStatement = implode(',', $updateStatement);
        $query = sprintf($query, $tableName, $columns, $valueHolder, $updateStatement);
        $bindings = [];
        foreach ($values as $item) {
            $bindings = array_merge($bindings, array_values($item));
        }
        return $this->execute($query, $bindings);
    }


    /**
     * 批量插入
     *
     * @param $tableName
     * @param $values
     * @param bool $isIgnore
     * @return int
     */
    public function insertBatch($tableName, $values, $isIgnore=false)
    {
        if (empty($values)) {
            throw new \InvalidArgumentException('插入字段不能为空。');
        }
        $first = reset($values);
        $valueHolder = '?';
        $fieldCount = count($first);
        if ($fieldCount>1) {
            $valueHolder .= str_repeat(',?', $fieldCount - 1);
        }
        $valueHolder = sprintf('(%s)', $valueHolder);
        $count = count($values);
        if ($count>1) {
            $valueHolder .= str_repeat(','.$valueHolder, $count - 1);
        }

        $columns = '`'.implode('`,`', array_keys($first)).'`';
        $ignore = '';
        if ($isIgnore) {
            $ignore = 'IGNORE';
        }
        $query = 'insert %s into `%s` (%s) values %s';
        $query = sprintf($query, $ignore, $tableName, $columns, $valueHolder);
        $bindings = [];
        foreach ($values as $item) {
            $bindings = array_merge($bindings, array_values($item));
        }
        return $this->execute($query, $bindings);
    }

    /**
     *
     * Run an update statement against the database.
     *
     * @param string $tableName
     * @param array $upgs
     * @param string|array $where
     * @param array $whereBindings
     * @return int
     */
    public function update($tableName, array $upgs, $where='', $whereBindings=[])
    {
        list($where, $whereBindings) = $this->formatConditions($where, $whereBindings);
        $sets = [];
        $bindings = [];
        foreach ($upgs as $key => $item) {
            $sets[] = '`'.$key.'` = ?';
            $bindings[] = $item;
        }

        if ($whereBindings) {
            $bindings = array_merge($bindings, $whereBindings);
        }

        $sets = implode(',', $sets);
        $query = 'update `%s` set %s where %s';
        $query = sprintf($query, $tableName, $sets, $where);

        return $this->execute($query, $bindings);
    }

    /**
     * 格式化条件语句，转化为sql与用于绑定的参数数组
     *
     * @param string|array  $where
     * @param array         $whereBindings
     * @return array
     */
    private function formatConditions($where, array $whereBindings=[])
    {
        if (!is_array($whereBindings)) {
            $whereBindings = [$whereBindings];
        }

        $wherePart1Bindings = [];
        $conditions = '';
        if (empty($where)) {
            $conditions = 1;
        } elseif (is_array($where)) {

            $conditions = [];
            foreach ($where as $key => $item) {
                if (is_array($item)) {
                    $wherePart1Bindings = array_merge($wherePart1Bindings, array_values($item));
                    $conditions[] = '`'.$key.'` in ('.implode(',', array_fill(0, count($item), '?')).')';
                } else {
                    $wherePart1Bindings[] = $item;
                    $conditions[] = '`'.$key.'` = ?';
                }
            }
            $conditions = implode(' and ', $conditions);

        } else {
            $conditions = $where;
        }

        if ($wherePart1Bindings) {
            $whereBindings = array_merge($wherePart1Bindings, $whereBindings);
        }

        return [$conditions, $whereBindings];
    }

    /**
     * Run a select statement against the database.
     *
     * @param string        $tableName      表名
     * @param array         $fields         字段名称
     * @param array|string  $where          查询条件
     * @param array         $whereBindings  预处理参数
     * @param array         $order          排序字段集合，['id'=>'desc', 'a'=>'asc']
     * @param array|int     $limit          限制记录数, int:limit ?, array: limit ?1, ?2
     * @return array
     */
    public function select(string $tableName, array $fields, 
    $where = [], array $whereBindings = [], $order = [], $limit = null)
    {
        list($where, $whereBindings) = $this->formatConditions($where, $whereBindings);
        $fields = implode(',', $fields);
        $query = 'select %s from `%s` where %s';

        if ($order) {
            foreach ($order as $key => $item) {
                $item = $key.' '.$item;
                $order[$key] = $item;
            }
            $query .= ' order by '.implode(',', $order);
        }

        if (is_array($limit)) {
            $query .= ' limit '.$limit[0].', '.$limit[1];
        } elseif(is_int($limit)) {
            $query .= ' limit '.$limit;
        }

        $query = sprintf($query, $fields, $tableName, $where);
        return $this->fetchAll($query, $whereBindings);
    }


    /**
     * Run a select statement and return a single result.
     *
     * @param string $tableName
     * @param array $fields
     * @param $where
     * @param array $whereBindings
     * @return array
     */
    public function selectOne(string $tableName, array $fields, $where = [], array $whereBindings = [])
    {
        list($where, $whereBindings) = $this->formatConditions($where, $whereBindings);
        $fields = implode(',', $fields);
        $query = 'select %s from `%s` where %s limit 1';
        $query = sprintf($query, $fields, $tableName, $where);
        return $this->fetchOne($query, $whereBindings);
    }

    /**
     * Run a count statement and return a single result.
     *
     * @param string $tableName
     * @param $where
     * @param array $whereBindings
     * @return array
     */
    public function count(string $tableName, $where = [], array $whereBindings = [])
    {
        list($where, $whereBindings) = $this->formatConditions($where, $whereBindings);
        $query = 'select count(1) n from `%s` where %s limit 1';
        $query = sprintf($query, $tableName, $where);
        $r = $this->fetchOne($query, $whereBindings);
        if (empty($r)) {
            return 0;
        }
        return intval($r['n']);
    }



    /**
     * Run a delete statement against the database.
     *
     * @param string $tableName
     * @param string|array $where
     * @param array $whereBindings
     * @return int
     */
    public function delete(string $tableName, $where, array $whereBindings = [])
    {
        list($where, $whereBindings) = $this->formatConditions($where, $whereBindings);
        $query = 'delete from `%s` where %s';
        $query = sprintf($query, $tableName, $where);
        return $this->execute($query, $whereBindings);
    }


    /**
     * Determine if the given exception was caused by a lost connection.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    protected function causedByLostConnection(\Throwable $e)
    {
        $message = $e->getMessage();

        return Str::contains($message, [
            'server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'server closed the connection unexpectedly',
            'SSL connection has been closed unexpectedly',
            'Error writing data to the connection',
            'Resource deadlock avoided',
            'Transaction() on null',
            'child connection forced to terminate due to client_idle_limit',
            'query_wait_timeout',
            'reset by peer',
            'Physical connection is not usable',
            'TCP Provider: Error code 0x68',
        ]);
    }
}