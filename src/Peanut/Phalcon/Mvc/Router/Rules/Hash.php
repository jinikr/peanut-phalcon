<?php

namespace Peanut\Phalcon\Mvc\Router\Rules;

class Hash extends \Peanut\Phalcon\Mvc\Router
{

    private function getArgs($key)
    {
        if(1 === preg_match('#(?P<type>[^\s]+)(\s+(?P<left>.*))?(\s+(?P<middle>.*))?(\s+(?P<right>.*))?#', $key, $matches))
        {
            $type = strtolower($matches['type']);
            switch ($type)
            {
                case 'param':
                    return [
                        $type
                        , isset($matches['right']) ? trim($matches['right'], '/') : ''
                        , isset($matches['middle']) ? array_map('strtoupper', explode('|', $matches['middle'])) : ['MAP']
                        , $matches['left']
                    ];
                case 'before':
                case 'after':
                case 'any':
                    return [
                        $type
                        , isset($matches['middle']) ? trim($matches['middle'], '/') : ''
                        , isset($matches['left']) ? array_map('strtoupper', explode('|', $matches['left'])) : ['MAP']
                        , ''
                    ];
                default:
                    return [
                        $type
                        , isset($matches['left']) ? trim($matches['left'], '/') : ''
                        , [strtoupper($type)]
                        , ''
                    ];
            }
        }
    }

    public function group($config)
    {
        foreach($config as $key => $value)
        {
            if(list($type, $uri, $methods, $param) = $this->getArgs($key))
            {
                switch ($type)
                {
                    case 'group':
                        array_push($this->groupParts, $uri);
                        $this->group($value);
                        array_pop($this->groupParts);
                        break;
                    case 'param':
                        $url = $this->getUri($uri);
                        foreach($methods as $method)
                        {
                            $this->{$type.'Handler'}[$method][$url][$param] = $value;
                        }
                        break;
                    case 'before':
                    case 'after':
                        $url = $this->getUri($uri);
                        foreach($methods as $method)
                        {
                            $this->{$type.'Handler'}[$method][$url] = $value;
                        }
                        break;
                    case 'any':
                    default:
                        $url = $this->getUri($uri);
                        foreach($methods as $method)
                        {
                            $this->routeHandler[$method][$url] = $value;
                        }
                        break;
                }
            }
            else
            {
            }
        }
    }

}

/*

routes:
  param name: \App\Controllers\V1->checkId
  group {huga:[huga|huga]{4}}:
    before map test/adf: \App\Controllers\V1->before
    after: \App\Controllers\V1->after
    param name: \App\Controllers\V1->checkId2
    get {name}: \App\Controllers\V1->getInfo
    get view/{view_id:[0-9]+}: \App\Controllers\V1->view
    group bbs:
      get: \App\Controllers\V1->index
    group add:
      get: \App\Controllers\V1->index
    group {boardId:[0-9]+}:
      any post|get: \App\Controllers\V1->getNumber
      group {boardId:[0-9]+}:
        before: \App\Controllers\V1->index
        get: \App\Controllers\V1->index
  group huganew:
    get: \App\Controllers\V1->index
  get: /index page

$router = new \Peanut\Phalcon\Mvc\Router\RulesArray();
$router->group(yaml_parse_file('evn.yaml'));
return $router;

*/