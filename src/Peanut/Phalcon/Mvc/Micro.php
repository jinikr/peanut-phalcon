<?php

namespace Peanut\Phalcon\Mvc;

class Micro extends \Phalcon\Mvc\Micro
{

    const methods = ['POST', 'GET', 'PUT', 'PATCH', 'HEAD', 'DELETE', 'OPTIONS'];
    private $methods = self::methods;
    private $pattern;
    private $routeGroups = [];

    private function callHandler($name, $handler, $args = [])
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
                    $class = Router::getInstance()->load($tmp[0]);
                }
                catch(\Throwable $e)
                {
                    throw new \Exception($name.' \''.$handler.'\' handler is not callable');
                }
                if(true === is_callable([$class, $tmp[1]]))
                {
                    $status = call_user_func_array([$class, $tmp[1]], $args);
                }
                else
                {
                    throw new \Exception($name.' \''.$handler.'\' handler is not callable');
                }
            }
            else
            {
                throw new \Exception($name.' \''.$handler.'\' handler is not callable');
            }
        }
        else
        {
            throw new \Exception($name.' handler is not callable');
        }
        return $status;
    }

    /**
     * Handle the whole request
     *
     * @param string uri
     * @return mixed
     */
    public function handle($uri = null)
    {
        foreach (Router::getInstance()->getRoutes() as $key => $value)
        {
            parent::{$value['method']}($value['pattern'], $value['handler']);
        }

        $dependencyInjector = $this->_dependencyInjector;
        if (false === is_object($dependencyInjector))
        {
            throw new \Exception("A dependency injection container is required to access required micro services");
        }

        try
        {
            $returnedValue = null;
            $router = $dependencyInjector->getShared("router");
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

                $routeParamHandlers = Router::getInstance()->get('param');
                if (true === is_array($routeParamHandlers))
                {
                    foreach ($routeParamHandlers as $paramHandlers)
                    {
                        if (true === is_array($paramHandlers))
                        {
                            foreach ($paramHandlers as $paramHandler)
                            {
                                if (true === isset($paramHandler[0])
                                    && true === isset($paramHandler[1])
                                    && true === isset($params[$paramHandler[0]]))
                                {
                                    $status = $this->callHandler('param', $paramHandler[1], [$params[$paramHandler[0]]]);
                                    if (false === $status)
                                    {
                                        return false;
                                    }
                                }
                            }
                        }
                    }
                }

                $routeBeforeHandlers = Router::getInstance()->get('before');
                if (true === is_array($routeBeforeHandlers))
                {
                    foreach ($routeBeforeHandlers as $beforeHandlers)
                    {
                        if (true === is_array($beforeHandlers))
                        {
                            foreach ($beforeHandlers as $beforeHandler)
                            {
                                $status = $this->callHandler('before', $beforeHandler, $params);
                                if (false === $status)
                                {
                                    return false;
                                }
                            }
                        }
                    }
                }

                $returnedValue = $this->callHandler('class', $handler, $params);

                $routeAfterHandlers = Router::getInstance()->get('after');
                if (true === is_array($routeAfterHandlers))
                {
                    foreach ($routeAfterHandlers as $afterHandlers)
                    {
                        if (true === is_array($afterHandlers))
                        {
                            foreach ($afterHandlers as $afterHandler)
                            {
                                $status = $this->callHandler('after', $afterHandler, $params);
                                if (false === $status)
                                {
                                    return false;
                                }
                            }
                        }
                    }
                }
            }
            else
            {
                $returnedValue = $this->callHandler('notFound', $this->_notFoundHandler);
            }

            $this->_returnedValue = $returnedValue;
        }
        catch (\Exception $e)
        {
            if ($this->_errorHandler)
            {
                $returnedValue = $this->callHandler('error', $this->_errorHandler, [$e]);

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

    public function group($callback)
    {
        if(func_num_args() === 2)
        {
            list($prefix, $callback) = func_get_args();
        }
        else
        {
            $prefix = '';
        }
        if($callback instanceof \Closure)
        {
            if($prefix)
            {
                array_push($this->routeGroups, $prefix);
            }
            $callback = $callback->bindTo($this);
            $callback();
            if($prefix)
            {
                array_pop($this->routeGroups);
            }
        }
        else
        {
            $msg = debug_backtrace()[0];
            $msg = 'Closure can\'t be loaded'.PHP_EOL.'in '.$msg['file'].', line '.$msg['line'];
            throw new \Exception($msg);
        }
        //return $this;
    }

    public function chainInit()
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

    public function getRouteGroup($pattern = '')
    {
        $first = trim(implode('/', $this->routeGroups),'/') ?: '';
        $middle = $this->pattern ?: '';
        $last = trim($pattern, '/') ?: '';

        $uri = $first;
        if($middle)
        {
            if($uri)
            {
                $uri .= '/'.$middle;
            }
            else
            {
                $uri = $middle;
            }
        }
        if($last)
        {
            if($uri)
            {
                $uri .= '/'.$last;
            }
            else
            {
                $uri = $last;
            }
        }
        return $uri ? '/'.$uri : '/';
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

    public function param($key, $handler, $pattern = '')
    {
        if(func_num_args() === 3) list($pattern, $key, $handler) = func_get_args();
        Router::getInstance()->setPattern('param', $this->getRouteGroup($pattern), [$key, $handler], $this->methods);
        $this->chainInit();
    }

    public function before($handler, $pattern = '')
    {
        if(func_num_args() === 2) list($pattern, $handler) = func_get_args();
        Router::getInstance()->setPattern('before', $this->getRouteGroup($pattern), $handler, $this->methods);
        $this->chainInit();
    }

    public function after($handler, $pattern = '')
    {
        if(func_num_args() === 2) list($pattern, $handler) = func_get_args();
        Router::getInstance()->setPattern('after', $this->getRouteGroup($pattern), $handler, $this->methods);
        $this->chainInit();
    }

    public function any($handler, $pattern = '')
    {
        if(2 === func_num_args()) list($pattern, $handler) = func_get_args();
        Router::getInstance()->setRoute($this->getRouteGroup($pattern), $handler, $this->methods);
        $this->chainInit();
    }

    public function map($handler, $pattern = '')
    {
        if(2 === func_num_args()) list($pattern, $handler) = func_get_args();
        if(self::methods !== $this->methods) throw new ChainingException();
        Router::getInstance()->setRoute($this->getRouteGroup($pattern), $handler, ['MAP']);
        $this->chainInit();
    }

    public function get($handler, $pattern = '')
    {
        if(2 === func_num_args()) list($pattern, $handler) = func_get_args();
        if(self::methods !== $this->methods) throw new ChainingException();
        Router::getInstance()->setRoute($this->getRouteGroup($pattern), $handler, ['GET']);
        $this->chainInit();
    }

    public function post($handler, $pattern = '')
    {
        if(2 === func_num_args()) list($pattern, $handler) = func_get_args();
        if(self::methods !== $this->methods) throw new ChainingException();
        Router::getInstance()->setRoute($this->getRouteGroup($pattern), $handler, ['POST']);
        $this->chainInit();
    }

    public function put($handler, $pattern = '')
    {
        if(2 === func_num_args()) list($pattern, $handler) = func_get_args();
        if(self::methods !== $this->methods) throw new ChainingException();
        Router::getInstance()->setRoute($this->getRouteGroup($pattern), $handler, ['PUT']);
        $this->chainInit();
    }

    public function patch($handler, $pattern = '')
    {
        if(2 === func_num_args()) list($pattern, $handler) = func_get_args();
        if(self::methods !== $this->methods) throw new ChainingException();
        Router::getInstance()->setRoute($this->getRouteGroup($pattern), $handler, ['PATCH']);
        $this->chainInit();
    }

    public function head($handler, $pattern = '')
    {
        if(2 === func_num_args()) list($pattern, $handler) = func_get_args();
        if(self::methods !== $this->methods) throw new ChainingException();
        Router::getInstance()->setRoute($this->getRouteGroup($pattern), $handler, ['HEAD']);
        $this->chainInit();
    }

    public function delete($handler, $pattern = '')
    {
        if(2 === func_num_args()) list($pattern, $handler) = func_get_args();
        if(self::methods !== $this->methods) throw new ChainingException();
        Router::getInstance()->setRoute($this->getRouteGroup($pattern), $handler, 'DELETE');
        $this->chainInit();
    }

    public function options($handler, $pattern = '')
    {
        if(2 === func_num_args()) list($pattern, $handler) = func_get_args();
        if(self::methods !== $this->methods) throw new ChainingException();
        Router::getInstance()->setRoute($this->getRouteGroup($pattern), $handler, 'OPTIONS');
        $this->chainInit();
    }

}

class ChainingException extends \Exception
{

    public function __construct($message = '', $code = 0, \Exception $previous = null)
    {
        $last = (debug_backtrace()[1]);
        if($last['class'] === 'Peanut\Phalcon\Mvc\Micro'
            && true === in_array(strtoupper($last['function']), \Peanut\Phalcon\Mvc\Micro::methods))
        {
            $message .= $last['function'].'()은 methods()와 chaining될수 없습니다.'.PHP_EOL.'in '.$last['file'].', line '.$last['line'];
        }
        parent::__construct($message);
    }

}

