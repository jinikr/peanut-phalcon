<?php
namespace Peanut\Bootstrap;

class Yaml
{
    /**
     * @var mixed
     */
    public $di;
    /**
     * @var string
     */
    public $stageName = '';
    /**
     * @var mixed
     */
    public $debug = false;

    /**
     * @param \Phalcon\DI\FactoryDefault $di
     */
    public function __construct(\Phalcon\DI\FactoryDefault $di)
    {
        $this->setDi($di);
    }

    /**
     * @param \Phalcon\DI\FactoryDefault $di
     */
    public function setDi(\Phalcon\DI\FactoryDefault $di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDI()
    {
        return $this->di;
    }

    /**
     * @return mixed
     */
    public function getHttpHost()
    {
        return $this->getDi()->get('request')->getHttpHost();
    }

    public function initRequest()
    {
        $this->di['request'] = function () {
            return new \Peanut\Phalcon\Http\Request();
        };
    }

    public function initResponse()
    {
        $this->di['response'] = function () {
            return new \Peanut\Phalcon\Http\Response();
        };
    }

    /**
     * @param  array   $config
     * @return mixed
     */
    public function initConfig(array $config)
    {
        $this->di['config'] = function () use ($config) {
            return $config; //(new \Phalcon\Config($config))->toArray();
        };
    }

    /**
     * @param  array   $config
     * @return mixed
     */
    public function initSession(array $config)
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

    public function initEventsManager()
    {
        $this->di['eventsManager'] = function () {
            return new \Phalcon\Events\Manager();
        };
    }

    public function initDbProfiler()
    {
        $this->di['profiler'] = function () {
            return new \Phalcon\Db\Profiler();
        };
    }

    /**
     * @param  array  $config
     * @return null
     */
    public function dbProfiler(array $config)
    {
        if ('local' !== $this->stageName) {
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

        if (true === isset($config['stages'][$this->stageName]['database']['server'])) {
            $dbNames = array_keys($config['stages'][$this->stageName]['database']['server']);

            foreach ($dbNames as $name) {
                \Peanut\Phalcon\Pdo\Mysql::name($name)->setEventsManager($eventsManager);
            }
        }
    }

    /**
     * @param  $configFile
     * @return mixed
     */
    public function getConfigFile($configFile)
    {
        try {
            $config = yaml_parse_file($configFile);

            if (false === is_array($config)) {
                throw new \Exception($configFile.' can\'t be loaded.');
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return $config;
    }

    /**
     * @param  \Phalcon\Mvc\Micro $app
     * @return mixed
     */
    public function __invoke(\Phalcon\Mvc\Micro $app)
    {
        $config = $this->getConfigFile(__BASE__.'/env.yml');

        return $this->run($app, $config);
    }

    /**
     * @param $config
     */
    public function init($config)
    {
        $this->initRequest();
        $this->initResponse();
        $this->initConfig($config);
        $this->initEnvironment($config);
        $this->initDebug($config);
        $this->initRouter($config);
        $this->initSession($config);
        $this->initDatabase($config);
    }

    /**
     * @param  \Phalcon\Mvc\Micro $app
     * @param  array              $config
     * @return mixed
     */
    public function run(\Phalcon\Mvc\Micro $app, array $config)
    {
        $this->init($config);
        $app->setDI($this->di);
        $app->notFound(
            function () use ($app) {
                $app->response->setStatusCode(404, 'Not Found');
                $app->response->setContent('404 Page or File Not Found');

                return $app->response;
            }
        );
        $app->error(
            function ($e) use ($app) {
                pr($e);
            }
        );
        $app->get('/', function () {
            echo '/';
        });
        $app->get('/info', function () {
            phpinfo();
        });

        return $app;
    }

    /**
     * @param  $config
     * @return mixed
     */
    public function initDatabase($config)
    {
        $stageName = $this->stageName;
        $debug     = $this->debug;

        $database              = $config['stages'][$stageName]['services']['mysql'];
        $this->di['databases'] = function () use ($database, $debug) {
            if (true === isset($database) && true === is_array($database)) {
                $databaseConfig = [];
                $serverList     = $database['server'];
                unset($database['server']);

                foreach ($serverList as $server => $dsn) {
                    $databaseConfig[$server] = array_merge(['dsn' => $dsn], $database);
                }

                return $databaseConfig;
            } else {
                throw new \Exception('databases config를 확인하세요.');
            }
        };

        if (true === $debug) {
            $this->dbProfiler($config);
        }
    }

    /**
     * @param $config
     */
    public function initEnvironment($config)
    {
        $host = $this->getHttpHost();
        $env  = '';

        foreach ($config['stages'] as $stageName => $stage) {
            foreach ($stage['services']['nginx']['vhosts'] as $vhost) {
                if (true === in_array($host, array_merge([$vhost['server_name']], $vhost['server_alias']))) {
                    $env = $stageName;
                    break;
                }
            }
        }

        if (!$env) {
            throw new \Exception('stage를 확인할수 없습니다.');
        }

        $this->stageName = $env;
    }

    /**
     * @param  $config
     * @return null
     */
    public function initDebug($config)
    {
        return;

        if (true === isset($config['stages'][$this->stageName]['app']['debug'])) {
            $this->debug = $config['stages'][$this->stageName]['app']['debug'];
        }

        if ($this->debug) {
            include_once __BASE__.'/app/helpers/debug.php';
        }
    }

    /**
     * @param  $config
     * @return mixed
     */
    public function initRouter($config)
    {
        $this->di['router'] = function () use ($config) {
            $router = new \Peanut\Phalcon\Mvc\Router\Rules\Hash();
            $router->group($config['routes']);

            return $router;
        };
    }
}
