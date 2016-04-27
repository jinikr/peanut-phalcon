<?php

namespace Peanut\Bootstrap;

class Basic
{
    private $di;

    public function __construct(\Phalcon\DI\FactoryDefault $di)
    {
        $this->setDi($di);
        $this->initRequest(); // request는 config에서 사용하므로 생성자에서 초기화
    }

    public function __invoke(\Phalcon\Mvc\Micro $app)
    {
        $this->initRoute($app);
        $config = $this->getConfigFile(__BASE__.'/app/config/environment.php');
        return $this->run($app, $config);
    }

    public function run(\Phalcon\Mvc\Micro $app, array $config)
    {
        // $this->initConfig($config);
        $this->initSession($config);
        $this->initPeanutDb($config);

        $app->setDI($this->di);
        return $app;
    }

    private function setDi(\Phalcon\DI\FactoryDefault $di)
    {
        $this->di = $di;
    }

    private function getDI()
    {
        return $this->di;
    }

    private function getHttpHost()
    {
        return $this->getDi()->get('request')->getHttpHost();
    }

    public function getConfigFile($configFile)
    {
        try
        {
            if (true === is_file($configFile))
            {
                $globalConfig = include $configFile;
                if (true === is_array($globalConfig)
                    && true === isset($globalConfig['domains'])
                    && true === is_array($globalConfig['domains']))
                {
                    foreach ($globalConfig['domains'] as $environment => $domain)
                    {
                        if (true === in_array($this->getHttpHost(), $domain))
                        {
                            $globalConfig['environment'] = $environment;
                            break;
                        }
                    }
                    if (false === isset($globalConfig['environment']) || !$globalConfig['environment'])
                    {
                        throw new \Exception('Configuration file '.$configFile.' '.$this->getHttpHost().'에 해당하는 domains 설정이 있는지 확인하세요.');
                    }
                    $envConfigFile = dirname($configFile).'/environment/'.$globalConfig['environment'].'.php';
                    if(true === is_file($envConfigFile))
                    {
                        $envConfig = include $envConfigFile;
                        if (true === is_array($envConfig))
                        {
                            $config = array_merge($globalConfig, $envConfig);
                        }
                        else
                        {
                            throw new \Exception('Configuration file '.$envConfig.' array 형식으로 설정하세요..');
                        }
                    }
                    else
                    {
                        throw new \Exception('Configuration file '.$envConfig.' can\'t be loaded');
                    }
                }
                else
                {
                    throw new \Exception('Configuration file '.$configFile.' domains 설정이 잘못 되었습니다.');
                }
            }
            else
            {
                throw new \Exception('Configuration file '.$configFile.' can\'t be loaded.');
            }
            if (false === isset($config) || !$config || false === is_array($config))
            {
                throw new \Exception($configFile.'을 확인하세요.');
            }
        }
        catch(\Exception $e)
        {
            throw $e;
        }
        return $config;
    }

    private function initRequest()
    {
        $this->di['request'] = function ()
        {
            return new \Peanut\Phalcon\Http\Request();
        };
    }

    private function initConfig(array $config)
    {
        $this->di['config'] = function () use ($config)
        {
            return $config;//(new \Phalcon\Config($config))->toArray();
        };
    }

    private function initSession(array $config)
    {
        $this->di['session'] = function () use ($config)
        {
            if(true === isset($config['session']))
            {
                $session = new \Phalcon\Session\Adapter\Files();
                $session->start();
                return $session;
            }
            else
            {
                throw new \Exception('session config를 확인하세요.');
            }
        };
    }

    private function initPeanutDb(array $config)
    {
        if (true === isset($config['databases']))
        {
            \Peanut\Db\Driver::setConnectInfo($config['databases']);
        }
    }

    private function initRoute(\Phalcon\Mvc\Micro $app)
    {
        if(true === is_file(__BASE__.'/app/config/route.php'))
        {
            include __BASE__.'/app/config/route.php';
        }
        else
        {
            throw new \Exception(__BASE__.'/app/config/route.php 을 확인하세요.');
        }
    }

}
