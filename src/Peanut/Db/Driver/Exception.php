<?php

namespace Peanut\Db\Driver;

class Exception extends \PDOException
{

    public static function varExport($mixed, $return = false, $lvl=0)
    {
        if(true === is_array($mixed))
        {
            $out = '[';
            $index = 0;
            $count = count($mixed);
            foreach ($mixed as $key => $value)
            {
                $index++;
                if (is_string($key))
                {
                    $key = var_export($key, true);
                }
                if(0 === strpos($key,'\':')) {
                    $out .= $key . '=>';
                }
                $val = static::varExport($value, true, $lvl+1);

                $out .= $val . ($index == $count ? '' : ',');
            }
            $out .= ']';
        }
        else
        {
            $out = var_export($mixed, true);
        }
        if($return === true)
        {
            return $out;
        }
        else
        {
            echo $out;
            return;
        }
    }

    public function __construct($message, $code = 0, Exception $previous = null)
    {
        if($message instanceof \PDOException)
        {
            $msg = [];
            foreach($message->getTrace() as $key => $value)
            {
                if(true === isset($value['class']))
                {
                    if(true === isset($value['file']) && false !== strrpos($value['file'], 'Driver.php')) {
                        continue;
                    }
                    $tmp = true === isset($value['file']) ? $value['file'].'('.$value['line'].')' : 'internal function';
                    $tmp .= ': '.$value['class'].$value['type'].$value['function'].'(';
                    $t = [];
                    foreach($value['args'] as $key => $value)
                    {
                        $t[] = static::varExport($value, true);
                    }
                    $tmp .= implode(",", $t);
                    $tmp .= ')';
                    $msg[] = $tmp;
                }
            }

            $message = $message->getMessage();
            $message .= "\nin ".$msg[0];
        }

        parent::__construct($message);
    }

}
