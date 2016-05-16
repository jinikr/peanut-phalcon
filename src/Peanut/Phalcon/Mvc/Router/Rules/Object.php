<?php

namespace Peanut\Phalcon\Mvc\Router\Rules;

class Object extends \Peanut\Phalcon\Mvc\Router
{

    private function chainInit()
    {
        $this->methods = self::methods;
        $this->pattern = '';
        return $this;
    }

    public function pattern($pattern)
    {
        $this->pattern = trim($pattern, '/');
        return $this;
    }

    public function methods($methods = [])
    {
        if(false === is_array($methods))
        {
            $methods = func_get_args();
        }
        if(!$methods)
        {
            $methods = self::methods;
        }
        $this->methods = array_map('strtoupper', $methods);
        return $this;
    }

    public function group($callback)
    {
        if(func_num_args() === 2)
        {
            list($prefix, $callback) = func_get_args();
        }
        else
        {
            $prefix = [];
        }
        if($callback instanceof \Closure)
        {
            array_push($this->groupParts, $prefix);
            $callback = $callback->bindTo($this);
            $callback();
            array_pop($this->groupParts);
        }
        else
        {
            $msg = debug_backtrace()[0];
            $msg = 'Closure can\'t be loaded'.PHP_EOL.'in '.$msg['file'].', line '.$msg['line'];
            throw new \Exception($msg);
        }
        //return $this;
    }

    public function get($handler, $pattern = '')
    {
        if(func_num_args() === 2) list($pattern, $handler) = func_get_args();
        if(self::methods !== $this->methods) throw new ChainingException();
        foreach($this->methods as $method)
        {
            $this->routeHandler['GET'][$this->getUri($pattern)] = $handler;
        }
        $this->chainInit();
    }

    public function post($handler, $pattern = '')
    {
        if(func_num_args() === 2) list($pattern, $handler) = func_get_args();
        if(self::methods !== $this->methods) throw new ChainingException();
        foreach($this->methods as $method)
        {
            $this->routeHandler['POST'][$this->getUri($pattern)] = $handler;
        }
        $this->chainInit();
    }

    public function put($handler, $pattern = '')
    {
        if(func_num_args() === 2) list($pattern, $handler) = func_get_args();
        if(self::methods !== $this->methods) throw new ChainingException();
        foreach($this->methods as $method)
        {
            $this->routeHandler['PUT'][$this->getUri($pattern)] = $handler;
        }
        $this->chainInit();
    }

    public function patch($handler, $pattern = '')
    {
        if(func_num_args() === 2) list($pattern, $handler) = func_get_args();
        if(self::methods !== $this->methods) throw new ChainingException();
        foreach($this->methods as $method)
        {
            $this->routeHandler['PATCH'][$this->getUri($pattern)] = $handler;
        }
        $this->chainInit();
    }

    public function head($handler, $pattern = '')
    {
        if(func_num_args() === 2) list($pattern, $handler) = func_get_args();
        if(self::methods !== $this->methods) throw new ChainingException();
        foreach($this->methods as $method)
        {
            $this->routeHandler['HEAD'][$this->getUri($pattern)] = $handler;
        }
        $this->chainInit();
    }

    public function options($handler, $pattern = '')
    {
        if(func_num_args() === 2) list($pattern, $handler) = func_get_args();
        if(self::methods !== $this->methods) throw new ChainingException();
        foreach($this->methods as $method)
        {
            $this->routeHandler['OPTIONS'][$this->getUri($pattern)] = $handler;
        }
        $this->chainInit();
    }

    public function any($handler, $pattern = '')
    {
        if(func_num_args() === 2) list($pattern, $handler) = func_get_args();
        foreach($this->methods as $method)
        {
            $this->routeHandler[$method][$this->getUri($pattern)] = $handler;
        }
        $this->chainInit();
    }

    public function param($param, $handler, $pattern = '')
    {
        if(func_num_args() === 2) list($pattern, $handler) = func_get_args();
        foreach($this->methods as $method)
        {
            $this->paramHandler[$method][$this->getUri($pattern)][$param] = $handler;
        }
        $this->chainInit();
    }

    public function before($handler, $pattern = '')
    {
        if(func_num_args() === 2) list($pattern, $handler) = func_get_args();
        foreach($this->methods as $method)
        {
            $this->beforeHandler[$method][$this->getUri($pattern)] = $handler;
        }
        $this->chainInit();
    }

    public function after($handler, $pattern = '')
    {
        if(func_num_args() === 2) list($pattern, $handler) = func_get_args();
        foreach($this->methods as $method)
        {
            $this->afterHandler[$method][$this->getUri($pattern)] = $handler;
        }
        $this->chainInit();
    }

}

class ChainingException extends \Exception
{
    public function __construct($message = '', $code = 0, \Exception $previous = null)
    {
        $last = (debug_backtrace()[1]);
        if($last['class'] === 'Peanut\Phalcon\Mvc\Micro'
            && true === in_array(strtoupper($last['function']), \Peanut\Phalcon\Mvc\Router::methods))
        {
            $message .= $last['function'].'()은 methods()와 chaining될수 없습니다.'.PHP_EOL.'in '.$last['file'].', line '.$last['line'];
        }
        parent::__construct($message);
    }
}

/*

$router = new \Peanut\Phalcon\Mvc\Router\RulesObject;
$router->group('huga', function() use ($router)
{
    $router->before(function()
    {
        echo 'huga before';
    });
    $router->get(function()
    {
        echo 'huga index page';
    });
    $router->get('{name}', function($name)
    {
        echo $name;
    });
    $router->get('view/{view_id:[0-9]+}', function()
    {

    });
    $router->get('write', function()
    {

    });
    $router->after(function() {
        echo 'huga after';
    });
});
$router->group('board', function() use ($router)
{
    $router->before(function()
    {
        echo 'board before';
    });
    $router->get(function()
    {
        echo 'board index page';
    });
    $router->group('{board_id:[a-z0-9A-Z]+}', function() use ($router)
    {
        $router->param('board_id', function($boardId)
        {
            $this->board = $boardId;
            echo 'board id : ' .$boardId;
        });
        $router->param('view_id', function($viewId)
        {
            $this->view = $viewId;
            echo 'view id : ' .$viewId;
        });
        $router->get(function($boardId)
        {
            echo 'board index page <b>'.$boardId.'</b>';
        });
        $router->get('add', function($board)
        {
            echo 'add '.($this->board === $board ? $board : false);
        });
        $router->get('view/{view_id:[0-9]+}', function($boardId, $viewId)
        {
            echo '<hr />';
            echo $viewId;
            echo '<hr />';
        });
        $router->get('write', function()
        {

        });
    });
    $router->after(function()
    {
        echo 'board after';
    });
});
$router->get('info', function()
{
    phpinfo();
});
$router->get(function()
{
    echo '/';
});

return $router;

*/