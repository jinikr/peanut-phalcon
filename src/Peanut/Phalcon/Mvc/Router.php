<?php

namespace Peanut\Phalcon\Mvc;

class Router extends \Phalcon\Mvc\Router
{

    const METHODS = ['POST', 'GET', 'PUT', 'PATCH', 'HEAD', 'DELETE', 'OPTIONS'];
    protected $methods = self::METHODS;
    protected $groupParts = [];
    protected $paramHandler = [];
    protected $beforeHandler = [];
    protected $afterHandler = [];
    protected $routeHandler = [];

    public function getUri($uri = '')
    {
        $url = '';
        if (true === is_array($this->groupParts) && 0 < count($this->groupParts)) {
            $url .= '/' . implode("/", $this->groupParts);
        }
        if ($uri) {
            $url .= '/' . $uri;
        }
        if (!$url) {
            $url = '/';
        }
        return $url;
    }

    public function getParam()
    {
        return $this->paramHandler;
    }

    public function getBefore()
    {
        return $this->beforeHandler;
    }

    public function getAfter()
    {
        return $this->afterHandler;
    }

    public function getRoute()
    {
        return $this->routeHandler;
    }

}
