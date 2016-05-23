<?php
namespace Peanut\Phalcon\Mvc;

class Router extends \Phalcon\Mvc\Router
{
    const METHODS = ['POST', 'GET', 'PUT', 'PATCH', 'HEAD', 'DELETE', 'OPTIONS'];
    /**
     * @var array
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
     * @return string
     */
    public function getUri($uri = '')
    {
        $url = '';

        if (true === is_array($this->groupParts) && 0 < count($this->groupParts)) {
            $url .= '/'.implode('/', $this->groupParts);
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
     * @return array
     */
    public function getParam()
    {
        return $this->paramHandler;
    }

    /**
     * @return array
     */
    public function getBefore()
    {
        return $this->beforeHandler;
    }

    /**
     * @return array
     */
    public function getAfter()
    {
        return $this->afterHandler;
    }

    /**
     * @return array
     */
    public function getRoute()
    {
        return $this->routeHandler;
    }

    /**
     * @return array
     */
    public function getMethods($name)
    {
        return self::METHODS;
    }
}
