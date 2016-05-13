<?php

namespace Peanut\Phalcon\Mvc\Router\Rules;

class Hash extends \Peanut\Phalcon\Mvc\Router
{

    private function getArgs($key)
    {
        if(1 === preg_match('#(?P<left>[^\s]+)(\s+(?P<middle>.*))?(\s+(?P<right>.*))?(\s+(?P<last>.*))?#', $key, $matches))
        {
            $count = count($matches);
            $type = strtoupper($matches['left']);

            if($type == 'PARAM')
            {
                return [
                    $type
                    , ''
                    , isset($matches['right']) ? array_map(explode('|', $matches['right']), 'strtoupper') : ['MAP']
                    , $matches['middle']
                ];
            }
            else if($type == 'BEFORE')
            {
                return [
                    $type
                    , ''
                    , isset($matches['middle']) ? array_map('strtoupper', explode('|', $matches['middle'])) : ['MAP']
                    , ''
                ];
            }
            else if($type == 'AFTER')
            {
                return [
                    $type
                    , ''
                    , isset($matches['middle']) ? array_map('strtoupper', explode('|', $matches['middle'])) : ['MAP']
                    , ''
                ];
            }
            else if($type == 'ANY')
            {
                return [
                    $type
                    , isset($matches['right']) ? trim($matches['right'], '/') : ''
                    , array_map('strtoupper', explode('|', $matches['middle']))
                    , ''
                ];
            }
            else
            {
                return [
                    $type
                    , isset($matches['middle']) ? trim($matches['middle'], '/') : ''
                    , [$type]
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
                    case 'GROUP':
                        array_push($this->groupParts, $uri);
                        $this->group($value);
                        array_pop($this->groupParts);
                        break;
                    case 'BEFORE':
                        $url = $this->getUri();
                        foreach($methods as $method)
                        {
                            $this->{$type}[$method][$url] = $value;
                        }
                        break;
                    case 'AFTER':
                        $url = $this->getUri();
                        foreach($methods as $method)
                        {
                            $this->{$type}[$method][$url] = $value;
                        }
                        break;
                    case 'PARAM':
                        $url = $this->getUri();
                        foreach($methods as $method)
                        {
                            $this->{$type}[$method][$url][$param] = $value;
                        }
                        break;
                    case 'ANY':
                        $url = $this->getUri();
                        foreach($methods as $method)
                        {
                            $this->ROUTE[$method][$url] = $value;
                        }
                        break;
                    default:
                        $url = $this->getUri($uri);
                        $this->ROUTE[$type][$url] = $value;

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
    before get|post: \App\Controllers\V1->before
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