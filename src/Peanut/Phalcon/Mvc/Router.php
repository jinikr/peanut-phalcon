<?php

namespace Peanut\Phalcon\Mvc;

class Router extends \Phalcon\Mvc\Router
{

    const methods = ['POST', 'GET', 'PUT', 'PATCH', 'HEAD', 'DELETE', 'OPTIONS'];
    protected $groupParts = [];
    protected $methods = self::methods;
    protected $PARAM = [];
    protected $BEFORE = [];
    protected $AFTER = [];
    protected $ROUTE = [];

    public function getUri($uri='')
    {
        $url = '';
        if(true === is_array($this->groupParts) && 0 < count($this->groupParts))
        {
            $url .= '/'.implode("/",$this->groupParts);
        }
        if($uri)
        {
            $url .= '/'.$uri;
        }
        if(!$url)
        {
            $url = '/';
        }
        return $url;
    }

    public function getParam()
    {
        return $this->PARAM;
    }

    public function getBefore()
    {
        return $this->BEFORE;
    }

    public function getAfter()
    {
        return $this->AFTER;
    }

    public function getRoute()
    {
        return $this->ROUTE;
    }

}
