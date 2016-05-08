<?php

namespace Peanut\Phalcon\Mvc;

class Router extends \Phalcon\Mvc\Router
{

    public $groupParts = [];
    const methods = ['POST', 'GET', 'PUT', 'PATCH', 'HEAD', 'DELETE', 'OPTIONS'];
    public $methods = self::methods;
    public $ROUTE = [];

    public function getUri($uri='')
    {
        $url = '/';
        if(true === is_array($this->groupParts) && 0 < count($this->groupParts))
        {
            $url .= implode("/",$this->groupParts);
        }
        if($uri)
        {
            $url .= '/'.$uri;
        }
        return $url;
    }

}
