<?php

namespace Peanut\Phalcon\Mvc;

class Micro extends \Phalcon\Mvc\Micro
{

    public $prefix;

    private function callHandler($name, $handlers, $args = [])
    {
        if (true === is_callable($handlers))
        {
            $status = call_user_func_array($handlers, $args);
        }
        else if (true === is_string($handlers))
        {
            if (false !== strpos($handlers, '->'))
            {
                $tmp = explode('->', $handlers);
                $class = Router::getInstance()->load($tmp[0]);
                $status = call_user_func_array([$class, $tmp[1]], $args);
            }
            else
            {
                throw new \Exception($name.' '.$handlers.' handler is not callable');
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
             parent::{$value['method']}($value['prefix'].$value['pattern'] ?: '/', $value['handler']);
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
            $params = $router->getParams();

            if (true === is_object($matchedRoute))
            {
                $handler = $this->_handlers[$matchedRoute->getRouteId()];
                if (!$handler)
                {
                    throw new \Exception("Matched route doesn't have an associated handler");
                }
                $this->_activeHandler = $handler;

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
                                $status = $this->callHandler('before', $beforeHandler);
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
                                $status = $this->callHandler('after', $afterHandler);
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

    public function group($prefix, \Closure $callback)
    {
        $scope = clone $this;
        $scope->prefix .= '/'.trim($prefix, '/');

        $callback = $callback->bindTo($scope);
        $tmp = $callback();

        return $this;
    }

    public function param($key, $methodName)
    {
        if(func_num_args() > 2)
        {
            list($routePattern, $key, $methodName) = func_get_args();
            Router::getInstance()->setPattern('param', $this->prefix, $routePattern, [$key, $methodName]);
        }
        else
        {
            Router::getInstance()->set('param', $this->prefix, [$key, $methodName]);
        }
        return $this;
    }

    public function before($methodName)
    {
        if(func_num_args() > 1)
        {
            list($routePattern, $methodName) = func_get_args();
            Router::getInstance()->setPattern('before', $this->prefix, $routePattern, $methodName);
        }
        else
        {
            Router::getInstance()->set('before', $this->prefix, $methodName);
        }
        return $this;
    }

    public function after($methodName)
    {
        if(func_num_args() > 1)
        {
            list($routePattern, $methodName) = func_get_args();
            Router::getInstance()->setPattern('after', $this->prefix, $routePattern, $methodName);
        }
        else
        {
            Router::getInstance()->set('after', $this->prefix, $methodName);
        }
        return $this;
    }

    public function map($routePattern, $handler)
    {
        Router::getInstance()->setRoute('map', $this->prefix, $routePattern, $handler);
        return $this;
    }

    public function get($routePattern, $handler)
    {
        Router::getInstance()->setRoute('get', $this->prefix, $routePattern, $handler);
        return $this;
    }

    public function post($routePattern, $handler)
    {
        Router::getInstance()->setRoute('post', $this->prefix, $routePattern, $handler);
        return $this;
    }

    public function put($routePattern, $handler)
    {
        Router::getInstance()->setRoute('put', $this->prefix, $routePattern, $handler);
        return $this;
    }

    public function patch($routePattern, $handler)
    {
        Router::getInstance()->setRoute('patch', $this->prefix, $routePattern, $handler);
        return $this;
    }

    public function head($routePattern, $handler)
    {
        Router::getInstance()->setRoute('head', $this->prefix, $routePattern, $handler);
        return $this;
    }

    public function delete($routePattern, $handler)
    {
        Router::getInstance()->setRoute('delete', $this->prefix, $routePattern, $handler);
        return $this;
    }

    public function options($routePattern, $handler)
    {
        Router::getInstance()->setRoute('options', $this->prefix, $routePattern, $handler);
        return $this;
    }

}
