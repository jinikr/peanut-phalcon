<?php
namespace Peanut\Phalcon\Pdo;

class Mysql extends \Phalcon\Db\Adapter\Pdo\Mysql
{
    /**
     * @param $descriptor
     * @param mixed $connect
     */
    public function connect(array $connect = null)
    {
        if (true === isset($connect['timezone'])) {
            $connect['options'][\PDO::MYSQL_ATTR_INIT_COMMAND] = "SET time_zone = '".$connect['timezone']."'";
        }
        if (true === isset($connect['persistent'])) {
            $connect['options'][\Pdo::ATTR_PERSISTENT] = $connect['persistent'];
        }
        try {
            $this->_pdo = new \Pdo($connect['dsn'], $connect['username'], $connect['password'], $connect['options']);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @param  $statement
     * @param  array           $bindParameters
     * @param  $mode
     * @throws \PDOException
     * @return array
     */
    public function gets($statement, $bindParameters = [], $mode = \Phalcon\Db::FETCH_ASSOC)
    {
        try {
            return parent::fetchAll($statement, $mode, $bindParameters);
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * @param  $statement
     * @param  array           $bindParameters
     * @param  $mode
     * @throws \PDOException
     * @return array
     */
    public function get($statement, $bindParameters = [], $mode = \Phalcon\Db::FETCH_ASSOC)
    {
        try {
            return parent::fetchOne($statement, $mode, $bindParameters);
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * @param  $statement
     * @param  array           $bindParameters
     * @param  $mode
     * @throws \PDOException
     * @return string
     */
    public function get1($statement, $bindParameters = [], $mode = \Phalcon\Db::FETCH_ASSOC)
    {
        try {
            $results = parent::fetchOne($statement, $mode, $bindParameters);

            if (true === is_array($results)) {
                foreach ($results as $result) {
                    return $result;
                }
            }

            return $results;
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * @param  $statement
     * @param  array           $bindParameters
     * @throws \PdoException
     * @return bool
     */
    public function set($statement, $bindParameters = [])
    {
        try {
            return parent::execute($statement, $bindParameters);
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /*
    \Peanut\Phalcon\Db::name('master')->sets(
        'insert into test (a,b,c,d) values (:a,:b,:c,:d)', [
            [
                ':a' => 1,
                ':b' => 2,
                ':c' => 1,
                ':d' => 2,
            ],
            [
                ':a' => 1,
                ':b' => 2,
                ':c' => 1,
                ':d' => 2,
            ],
            [
                ':a' => 1,
                ':b' => 2,
                ':c' => 1,
                ':d' => 2,
            ],
        ]
    );
    =>
    insert into test(a,b,c,d) values(:a0, :b0, :c0, :d0),(:a1, :b1, :c1, :d1),(:a2, :b2, :c2, :d2)
    [
      [:a0] => 1
      [:b0] => 2
      [:c0] => 1
      [:d0] => 2
      [:a1] => 1
      [:b1] => 2
      [:c1] => 1
      [:d1] => 2
      [:a2] => 1
      [:b2] => 2
      [:c2] => 1
      [:d2] => 2
    ]
    */
    public function sets($statement, $bindParameters)
    {
        if (
            0 < count($bindParameters)
            && 1 === preg_match('/(?P<control>.*)(?:[\s]+)values(?:[^\(]+)\((?P<holders>.*)\)/Us', $statement, $m)
        ) {
            $holders = explode(',', preg_replace('/\s/', '', $m['holders']));

            $newStatements     = [];
            $newBindParameters = [];
            foreach ($bindParameters as $key => $value) {
                $statements = [];
                foreach ($holders as $holder) {
                    $statements[]                    = $holder.$key;
                    $newBindParameters[$holder.$key] = $value[$holder];
                }
                $newStatements[] = '('.implode(', ', $statements).')';
            }
            $newStatement = $m['control'].' values '.implode(', ', $newStatements);
            try {
                if (parent::execute($newStatement, $newBindParameters)) {
                    return count($bindParameters);
                }
            } catch (\PDOException $e) {
                throw $e;
            }
        }

        return -1;
    }

    /**
     * @param  $statement
     * @param  array        $bindParameters
     * @return int|false
     */
    public function setAndGetSequnce($statement, $bindParameters = [])
    {
        if (true === self::set($statement, $bindParameters)) {
            return parent::lastInsertId();
        }

        return false;
    }

    /**
     * @param  $callback
     * @throws \Exception
     * @return mixed
     */
    public function transaction(callable $callback)
    {
        try {
            parent::begin();
            $return = call_user_func_array($callback, [$this]);
            if (parent::getTransactionLevel()) {
                parent::commit();
            } else {
                throw new \Exception('There is no active transaction');
            }

            return $return;
        } catch (\Throwable $e) {
            if (parent::getTransactionLevel()) {
                parent::rollback();
            }

            throw new TransactionException($e, $e->getCode());
        }
    }
}
class TransactionException extends \Exception
{
    public function __construct($message, $code = 0)
    {
        parent::__construct($message->getMessage(), $code);

        $tmp  = $message->getTrace()[0] ?? [];

        if (true === isset($tmp['file']) && $tmp['file']) {
            $this->addFile($tmp['file']);
            $this->addLine($tmp['line'].' {Closure}');
        } else {
            $this->addFile($message->getFile());
            $this->addLine($message->getLine());
        }
    }
    public function addFile($file)
    {
        $this->file = $file;
    }
    public function addLine($line)
    {
        $this->line = $line;
    }
}
