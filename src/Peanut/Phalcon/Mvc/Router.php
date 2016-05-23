<?php
namespace Peanut\Phalcon\Mvc;

class Router extends \Phalcon\Mvc\Router
{
    const METHODS = ['POST', 'GET', 'PUT', 'PATCH', 'HEAD', 'DELETE', 'OPTIONS'];
    /**
     * @var mixed
     */
    protected $methods = self::METHODS;
    /**
     * @var array
     */
    protected $groupParts = [];
    /**
     * @var array
     */
    protected $paramHandler = [];
    /**
     * @var array
     */
    protected $beforeHandler = [];
    /**
     * @var array
     */
    protected $afterHandler = [];
    /**
     * @var array
     */
    protected $routeHandler = [];

    /**
     * @param  $uri
     * @return mixed
     */
    public function getUri($uri = '')
    {
        $url = '';

        if (true === is_array($this->groupParts) && 0 < count($this->groupParts)) {
            $url .= '/'.implode("/", $this->groupParts);
        }

        if ($uri) {
            $url .= '/'.$uri;
        }

        if (!$url) {
            $url = '/';
        }

        return $url;
    }

    /**
     * @return mixed
     */
    public function getParam()
    {
        return $this->paramHandler;
    }

    /**
     * @return mixed
     */
    public function getBefore()
    {
        return $this->beforeHandler;
    }

    /**
     * @return mixed
     */
    public function getAfter()
    {
        return $this->afterHandler;
    }

    /**
     * @return mixed
     */
    public function getRoute()
    {
        return $this->routeHandler;
    }
}
