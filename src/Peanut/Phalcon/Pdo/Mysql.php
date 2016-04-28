<?php

namespace Peanut\Phalcon\Pdo;

class Mysql extends \Phalcon\Db\Adapter\Pdo\Mysql
{

    public static $instance;

    public static function name($name)
    {
        if (false === isset(self::$instance[$name]))
        {
            $tmp = \Phalcon\Di::getDefault();
            self::$instance[$name] = new self (\Phalcon\Di::getDefault()['db_'.$name]);
        }
        return self::$instance[$name];
    }

    public function gets($statement, $bindParameters = [], $mode = \Phalcon\Db::FETCH_ASSOC)
    {
        try
        {
            return parent::fetchAll($statement, $mode, $bindParameters);
        }
        catch (\PDOException $e)
        {
            throw $e;
        }
    }

    public function get($statement, $bindParameters = [], $mode = \Phalcon\Db::FETCH_ASSOC)
    {
        try
        {
            return parent::fetchOne($statement, $mode, $bindParameters);
        }
        catch (\PDOException $e)
        {
            throw $e;
        }
    }

    public function get1($statement, $bindParameters = [], $mode = \Phalcon\Db::FETCH_ASSOC)
    {
        try
        {
            $results = parent::fetchOne($statement, $mode, $bindParameters);
            if(true === is_array($results))
            {
                foreach($results as $result)
                {
                    return $result;
                }
            }
            return $results;
        }
        catch (\PDOException $e)
        {
            throw $e;
        }
    }

    public function set($statement, $bindParameters = [], $mode = \Phalcon\Db::FETCH_ASSOC)
    {
        try
        {
            return parent::execute($statement, $bindParameters, $mode);
        }
        catch (\PDOException $e)
        {
            throw $e;
        }
    }

    public function setId($statement, $bindParameters = [], $mode = \Phalcon\Db::FETCH_ASSOC)
    {
        if (true === self::set($statement, $bindParameters, $mode))
        {
            return parent::lastInsertId();
        }
        return false;
    }

    public function transaction(callable $callback)
    {
        try {
            parent::begin();
            $return = call_user_func($callback);
            parent::commit();
            return $return;
        }
        catch (\Throwable $e)
        {
            parent::rollback();
            throw $e;
        }
    }

}
