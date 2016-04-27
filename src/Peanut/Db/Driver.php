<?php

namespace Peanut\Db;

class Driver extends \Pdo
{

    public static $instance;
    public static $connectInfo;

    public static function name($name)
    {
        if (false === isset(self::$instance[$name]))
        {
            self::$instance[$name] = new self($name);
        }
        return self::$instance[$name];
    }

    public static function setConnectInfo($connectInfo)
    {
        static::$connectInfo = $connectInfo;
    }

    public static function getConnectInfo($name)
    {
        if(true === isset(static::$connectInfo[$name])
            && true === isset(static::$connectInfo[$name]["dsn"])
            && true === isset(static::$connectInfo[$name]["username"])
            && true === isset(static::$connectInfo[$name]["password"]))
        {
            return static::$connectInfo[$name];
        }
        else
        {
            throw new Driver\Exception($name . " config를 확인하세요.");
        }
    }

    public function __construct($name)
    {
        $connect = static::getConnectInfo($name);

        try {
            if(false === isset($connect['options']))
            {
                $connect['options'] = [];
            }
            parent::__construct($connect["dsn"], $connect["username"], $connect["password"], $connect['options']);
        }
        catch (\PDOException $e)
        {
            throw new Driver\Exception($e);
        }
    }

    private function execute($statement, $bindParameters = [], $ret = false)
    {
        $stmt = parent::prepare($statement);
        $newBindParameters = [];
        foreach ($bindParameters as $key => $value)
        {
            if (true === is_array($value))
            {
                $newBindParameters[$key] = $value[0];
            }
            else
            {
                $newBindParameters[$key] = $value;
            }
        }
        $result = $stmt->execute($newBindParameters);
        if (true === $ret)
        {
            $stmt->closeCursor();
            return $result;
        }
        else
        {
            return $stmt;
        }
    }

    public function gets($statement, $bindParameters = [], $mode = null)
    {
        try
        {
            $stmt   = self::execute($statement, $bindParameters);
            $mode   = self::getFetchMode($mode);
            $result = $stmt->fetchAll($mode);
            $stmt->closeCursor();
            return $result;
        }
        catch (\PDOException $e)
        {
            throw new Driver\Exception($e);
        }
    }

    public function get($statement, $bindParameters = [], $mode = null)
    {
        try
        {
            $stmt   = self::execute($statement, $bindParameters);
            $mode   = self::getFetchMode($mode);
            $result = $stmt->fetch($mode);
            $stmt->closeCursor();
            return $result;
        }
        catch (\PDOException $e)
        {
            throw new Driver\Exception($e);
        }
    }

    /**
        count(*)과 같이 하나의 값만 리턴할경우 tmp[0]과 같이 사용하지 않고 바로 tmp에 select한 값을 셋팅함
     */
    public function get1($statement, $bindParameters = [], $mode = null)
    {
        try
        {
            $stmt   = self::execute($statement, $bindParameters);
            $mode   = self::getFetchMode($mode);
            $result = $stmt->fetch($mode);
            $stmt->closeCursor();
            if (true === is_array($result))
            {
                foreach ($result as $key => $value)
                {
                    return $value;
                }
            }
            return false;
        }
        catch (\PDOException $e)
        {
            throw new Driver\Exception($e);
        }
    }

    public function set($statement, $bindParameters = [])
    {
        try
        {
            return self::execute($statement, $bindParameters, true);
        }
        catch (\PDOException $e)
        {
            throw new Driver\Exception($e);
        }
    }

    /**
        return int or false
     */
    public function setId($statement, $bindParameters = [])
    {
        if (self::set($statement, $bindParameters))
        {
            return self::getInsertid();
        }
        return false;
    }

    public function getInsertId($name = null)
    {
        return self::get1("SELECT LAST_INSERT_ID()");
    }

    private function getFetchMode($mode = null)
    {
        if (true === is_null($mode))
        {
            $mode = parent::getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE);
        }
        return $mode;
    }

    public function begin()
    {
        try
        {
            return parent::beginTransaction();
        }
        catch (\PDOException $e)
        {
            throw new Driver\Exception($e);
        }
    }

    public function rollback()
    {
        try
        {
            return parent::rollBack();
        }
        catch (\PDOException $e)
        {
            throw new Driver\Exception($e);
        }
    }

    public function commit()
    {
        try
        {
            return parent::commit();
        }
        catch (\PDOException $e)
        {
            throw new Driver\Exception($e);
        }
    }

    public function transaction(callable $callback)
    {
        try
        {
            parent::beginTransaction();
            $return = call_user_func($callback);
            parent::commit();
            return $return;
        }
        catch (\Driver\Exception $e)
        {
            parent::rollBack();
            throw $e;
        }
    }

}
