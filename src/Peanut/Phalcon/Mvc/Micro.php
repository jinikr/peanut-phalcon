<?php

namespace Peanut\Phalcon\Mvc;

class Micro extends \Phalcon\Mvc\Micro
{

    private $pattern;
    private $instance = [];

    private function classLoader($className)
    {
            if (false === isset($this->instance[$className]))
            {
                $this->instance[$className] = new $className;
            }
            return $this->instance[$className];
    }

    private function callHandler($handler, $args = [], $name = '')
    {
        if (true === is_callable($handler))
        {
            $status = call_user_func_array($handler, $args);
        }
        else if (true === is_string($handler))
        {
            if (false !== strpos($handler, '->'))
            {
                $tmp = explode('->', $handler);
                try
                {
                    $class = $this->classLoader($tmp[0]);
                }
                catch(\Throwable $e)
                {
                    throw new \Exception(($name ? $name.' ' : '' ).'\''.$handler.'\' handler is not callable: '.$e->getMessage());
                }
                if(true === is_callable([$class, $tmp[1]]))
                {
                    $status = call_user_func_array([$class, $tmp[1]], $args);
                }
                else
                {
                    throw new \Exception(($name ? $name.' ' : '' ).'\''.$handler.'\' handler is not callable');
                }
            }
            else
            {
                echo $handler;
                $status = '';
            }
        }
        else
        {
            throw new \Exception(($name ? $name.' ' : '' ).str_replace(PHP_EOL, '', print_r($handler, true)).' is not support');
        }
        return $status;
    }

    public function getPatternParts($matchedRoute)
    {
        $pattern = str_replace(['#^','/([^/]*)$#u'], '', $matchedRoute->getPattern());
        $spilits = preg_split('#(?<!\^|\\\)/#', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        $url = '';
        $parts = [];
        foreach($spilits as $uri)
        {
            $url .= '/'.$uri;
            $parts[] = '/'.trim($url,'/');
        }
        return $parts;
    }

    /**
     * Handle the whole request
     *
     * @param string uri
     * @return mixed
     */
    public function handle($uri = null)
    {

        $dependencyInjector = $this->_dependencyInjector;
        if (false === is_object($dependencyInjector))
        {
            throw new \Exception("A dependency injection container is required to access required micro services");
        }

        try
        {
            $returnedValue = null;
            $router = $dependencyInjector->getShared("router");

            foreach($router->ROUTE as $method => $_routes)
            {
                foreach($_routes as $url => $handler)
                {
                    $p = parent::{$method}($url, $handler);
                }
            }

            $router->handle($uri);
            $matchedRoute = $router->getMatchedRoute();


            if (true === is_object($matchedRoute))
            {
                $handler = $this->_handlers[$matchedRoute->getRouteId()];

                if (!$handler)
                {
                    throw new \Exception("Matched route doesn't have an associated handler");
                }
                $this->_activeHandler = $handler;
                $params = [];
                foreach($matchedRoute->getPaths() as $name => $key)
                {
                    $params[$name] = $router->getMatches()[$key];
                }

                $method  = $this->request->getMethod();
                $parts = $this->getPatternParts($matchedRoute);

                foreach($parts as $part)
                {
                    if(($_method = true === isset($router->PARAM['MAP'][$part]) ? 'MAP' : '')
                        || ($_method = true === isset($router->PARAM[$method][$part]) ? $method : '')
                    )
                    {
                        $check = $router->PARAM[$_method][$part];
                        foreach($check as $k => $_handler)
                        {
                            if(true === isset($params[$k]))
                            {
                                $status = $this->callHandler($_handler, [$params[$k]], 'param');
                                if (false === $status)
                                {
                                    return false;
                                }
                            };
                        };
                    }
                }

                foreach($parts as $part)
                {
                    if(($_method = true === isset($router->BEFORE['MAP'][$part]) ? 'MAP' : '')
                        || ($_method = true === isset($router->BEFORE[$method][$part]) ? $method : '')
                    )
                    {
                        $_handler = $router->BEFORE[$_method][$part];
                        $status = $this->callHandler($_handler, $params, 'before');
                        if (false === $status)
                        {
                            return false;
                        }
                    }
                }

                $returnedValue = $this->callHandler($handler, $params);

                foreach($parts as $part)
                {
                    if(($_method = true === isset($router->AFTER['MAP'][$part]) ? 'MAP' : '')
                        || ($_method = true === isset($router->AFTER[$method][$part]) ? $method : '')
                    )
                    {
                        $_handler = $router->AFTER[$_method][$part];
                        $status = $this->callHandler($_handler, $params, 'after');
                        if (false === $status)
                        {
                            return false;
                        }
                    }
                }
            }
            else
            {
                $returnedValue = $this->callHandler($this->_notFoundHandler, [], 'notFound');
            }

            $this->_returnedValue = $returnedValue;
        }
        catch (\Exception $e)
        {
            if ($this->_errorHandler)
            {
                $returnedValue = $this->callHandler($this->_errorHandler, [$e], 'error');

                if (true === is_object($returnedValue)
                    && !($returnedValue instanceof \Phalcon\Http\ResponseInterface))
                {
                    throw $e;
                }
            }
            else if (false !== $returnedValue)
            {
                throw $e;
            }
        }

        if (true === is_object($returnedValue)
            && $returnedValue instanceof \Phalcon\Http\ResponseInterface)
        {
            $returnedValue->send();
        }

        return $returnedValue;
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

