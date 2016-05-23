<?php
namespace Peanut\Bootstrap;

class Basic
{
    /**
     * @var mixed
     */
    private $di;

    /**
     * @param \Phalcon\DI\FactoryDefault $di
     */
    public function __construct(\Phalcon\DI\FactoryDefault $di)
    {
        $this->setDi($di);
        $this->initRequest(); // request는 config에서 사용하므로 생성자에서 초기화
    }

    /**
     * @param  \Phalcon\Mvc\Micro   $app
     * @return \Phalcon\Mvc\Micro
     */
    public function __invoke(\Phalcon\Mvc\Micro $app)
    {
        $this->initRoute($app);
        $config = $this->getConfigFile(__BASE__.'/app/config/environment.php');

        return $this->run($app, $config);
    }

    /**
     * @param  \Phalcon\Mvc\Micro   $app
     * @param  array                $config
     * @return \Phalcon\Mvc\Micro
     */
    public function run(\Phalcon\Mvc\Micro $app, array $config)
    {
        // $this->initConfig($config);
        $this->initSession($config);
        $this->initDb($config);
        $app->setDI($this->di);

        return $app;
    }

    /**
     * @param \Phalcon\DI\FactoryDefault $di
     */
    private function setDi(\Phalcon\DI\FactoryDefault $di)
    {
        $this->di = $di;
    }

    /**
     * @return \Phalcon\DI\FactoryDefault
     */
    private function getDI()
    {
        return $this->di;
    }

    /**
     * @return string
     */
    private function getHttpHost()
    {
        return $this->getDi()->get('request')->getHttpHost();
    }

    /**
     * @param  $configFile
     * @return mixed
     */
    public function getConfigFile($configFile)
    {
        try {
            if (true === is_file($configFile)) {
                $globalConfig = include $configFile;

                if (true === is_array($globalConfig)
                    && true === isset($globalConfig['domains'])
                    && true === is_array($globalConfig['domains'])) {
                    foreach ($globalConfig['domains'] as $environment => $domain) {
                        if (true === in_array($this->getHttpHost(), $domain)) {
                            $globalConfig['environment'] = $environment;
                            break;
                        }
                    }

                    if (false === isset($globalConfig['environment']) || !$globalConfig['environment']) {
                        throw new \Exception($configFile.' '.$this->getHttpHost().' domains config error');
                    }

                    $envConfigFile = dirname($configFile).'/environment/'.$globalConfig['environment'].'.php';

                    if (true === is_file($envConfigFile)) {
                        $envConfig = include $envConfigFile;

                        if (true === is_array($envConfig)) {
                            $config = array_merge($globalConfig, $envConfig);
                        } else {
                            throw new \Exception($envConfigFile.' config error');
                        }
                    } else {
                        throw new \Exception($envConfigFile.' can\'t be loaded');
                    }
                } else {
                    throw new \Exception($configFile.' domains config error');
                }
            } else {
                throw new \Exception($configFile.' can\'t be loaded.');
            }

            if (false === isset($config) || !$config || false === is_array($config)) {
                throw new \Exception('config error');
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return $config;
    }

    private function initRequest()
    {
        $this->di['request'] = function () {
            return new \Peanut\Phalcon\Http\Request();
        };
    }

    /**
     * @param  array   $config
     * @return mixed
     */
    private function initConfig(array $config)
    {
        $this->di['config'] = function () use ($config) {
            return $config; //(new \Phalcon\Config($config))->toArray();
        };
    }

    /**
     * @param  array   $config
     * @return mixed
     */
    private function initSession(array $config)
    {
        $this->di['session'] = function () use ($config) {
            if (true === isset($config['session'])) {
                $session = new \Phalcon\Session\Adapter\Files();
                $session->start();

                return $session;
            } else {
                throw new \Exception('session config를 확인하세요.');
            }
        };
    }

    /**
     * @param  array   $config
     * @return mixed
     */
    private function initDb(array $config)
    {
        $this->di['databases'] = function () use ($config) {
            if (true === isset($config['databases']) && true === is_array($config['databases'])) {
                return $config['databases'];
            } else {
                throw new \Exception('databases config를 확인하세요.');
            }
        };

        if (true === isset($config['database']['profile'])) {
            $this->dbProfiler($config);
        }
    }

    private function initEventsManager()
    {
        $this->di['eventsManager'] = function () {
            return new \Phalcon\Events\Manager();
        };
    }

    private function initDbProfiler()
    {
        $this->di['profiler'] = function () {
            return new \Phalcon\Db\Profiler();
        };
    }

    /**
     * @param  array  $config
     * @return null
     */
    private function dbProfiler(array $config)
    {
        if ('localhost' !== $config['environment']) {
            return;
        }

        $this->initDbProfiler();
        $eventsManager = $this->di['eventsManager'];
        $eventsManager->attach('db', function ($event, $connection) {
            $profiler = $this->di['profiler'];

            if ($event->getType() == 'beforeQuery') {
                $profiler->startProfile($connection->getSQLStatement(), $connection->getSQLVariables(), $connection->getSQLBindTypes());
            }

            if ($event->getType() == 'afterQuery') {
                $profiler->stopProfile();
            }
        });

        if (true === isset($config['databases'])) {
            foreach ($config['databases'] as $name => $config) {
                \Peanut\Phalcon\Pdo\Mysql::name($name)->setEventsManager($eventsManager);
            }
        }
    }

    /**
     * @param \Phalcon\Mvc\Micro $app
     */
    private function initRoute(\Phalcon\Mvc\Micro $app)
    {
        if (true === is_file(__BASE__.'/app/config/route.php')) {
            include __BASE__.'/app/config/route.php';
        } else {
            throw new \Exception(__BASE__.'/app/config/route.php 을 확인하세요.');
        }
    }
}
