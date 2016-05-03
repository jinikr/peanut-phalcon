<?php

namespace Peanut\Phalcon\Mvc;

class Router
{
    private static $instance; //The single instance

    private $routes = [];
    private $before = [];
    private $after = [];
    private $param = [];

    private $segments = [];
    private $segmentParts = [];

    private $prefix;
    private $method;
    private $class;

    private function getSegments()
    {
        return \Phalcon\Di::getDefault()->get('request')->getSegments();
    }

    private function getMethod()
    {
        return \Phalcon\Di::getDefault()->get('request')->getMethod();
    }

    private function getRewriteUri()
    {
        return \Phalcon\Di::getDefault()->get('request')->getRewriteUri();
    }

    public function init()
    {
        $this->segments = $this->getSegments();
        array_unshift($this->segments, '');
        $this->prefix = '/'.(true === isset($this->segments[1]) ? $this->segments[1] : '');
        $this->method = strtoupper($this->getMethod());
        $this->segmentParts = [];
        $tmp = '';
        foreach ($this->segments as $key => $value)
        {
            $tmp .= ($value ? '/'.$value : '');
            $this->segmentParts[] = ($tmp ?: '/');
        }
        return $this;
    }

    public static function getInstance()
    {
        if (!static::$instance)
        {
            static::$instance = (new self())->init();
        }
        return static::$instance;
    }

    public function setRoute($pattern, $handler, $methods = [])
    {
        //pr($type, $pattern, $methods,static::$instance->method);

        if (['MAP'] === $methods || true === in_array(static::$instance->method, $methods))
        {
            $matchPattern = false;

            if(false !== strpos($pattern, '{'))
            {
                $re = (new \Phalcon\Mvc\Router\Route($pattern, $this->getRewriteUri()))->getCompiledPattern();
                $matchPattern = 1 === preg_match($re, $this->getRewriteUri(), $match) ? true : false;
            }

            if (true === $matchPattern
                || '/' === $pattern
                || ('/' !== ($this->prefix) && 0 === strpos($pattern, $this->prefix)))
            {
                foreach($methods as $method)
                {
                    $this->routes[] = [
                        'method' => $method,
                        'pattern' => $pattern,
                        'handler' => $handler
                    ];
                }
            }
        }
    }

    public function setPattern($type, $pattern, $handler, $methods = [])
    {
        if (['MAP'] === $methods || true === in_array(static::$instance->method, $methods))
        {
            $matchPattern = false;
            if(false !== strpos($pattern, '{'))
            {
                $re = str_replace('$#u','/#u',(new \Phalcon\Mvc\Router\Route($pattern, $this->getRewriteUri()))->getCompiledPattern());
                $matchPattern = 1 === preg_match($re, $this->getRewriteUri().'/', $match) ? true : false;
            }

            if (true === $matchPattern || true === in_array($pattern, $this->segmentParts))
            {
                $this->{$type}[$pattern][] = $handler;
            }
        }
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    public function get($method)
    {
        return $this->{$method};
    }

    public function load($className)
    {
        try
        {
            if (false === isset($this->class[$className]))
            {
                $this->class[$className] = new $className;
            }
            return $this->class[$className];
        }
        catch(\Throwable $e)
        {
            throw $e;
        }
    }

}