<?php

namespace Peanut\Phalcon\Mvc\Router;

class RulesArray extends \Peanut\Phalcon\Mvc\Router
{

    public function getArgs($key)
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