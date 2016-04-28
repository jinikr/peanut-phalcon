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
        $this->prefix = true === isset($this->segments[1]) ? $this->segments[1] : '';
        $this->method = strtolower($this->getMethod());
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

    public function setRoute($method, $prefix, $routePattern, $handler)
    {
        $prefix = trim($prefix,'/');
        $routePattern = trim($routePattern,'/');

        if ('map' === $method || $method === static::$instance->method)
        {
            if (true === empty($prefix)
                || (false === empty($this->prefix) && 0 === strpos($prefix, $this->prefix)))
            {
                $this->routes[] = [
                    'method' => $method,
                    'prefix' => ($prefix ? '/'.$prefix : ''),
                    'pattern' => ($routePattern ? '/'.$routePattern : ''),
                    'handler' => $handler
                ];
            }
        }
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    public function setPattern($method, $prefix, $routePattern, $handler)
    {
        $prefix = trim($prefix,'/');
        $routePattern = trim($routePattern,'/');

        $pattern = ($prefix ? '/'.$prefix : '').($routePattern ? '/'.$routePattern : '') ?: '/';
        $matchPattern = false;

        if(false !== strpos($pattern, '{'))
        {
            $re = (new \Phalcon\Mvc\Router\Route($pattern, $this->getRewriteUri()))->getCompiledPattern();
            $matchPattern = 1 === preg_match($re, $this->getRewriteUri(), $match) ? true : false;
        }

        if (true === in_array($pattern, $this->segmentParts) || true === $matchPattern)
        {
            $this->{$method}[$pattern][] = $handler;
        }
    }

    public function set($method, $prefix, $handler)
    {
        $prefix = trim($prefix,'/');
        if (true === in_array('/'.$prefix, $this->segmentParts))
        {
            $this->{$method}[($prefix ? '/'.$prefix : '')][] = $handler;
        }
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