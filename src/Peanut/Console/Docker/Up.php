<?php

namespace Peanut\Console\Docker;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;

class Up extends \Peanut\Console\Command
{

    protected function configure()
    {
        $this
            ->setName('up')
            ->setDescription('starts and provisions the docker environment')
            ->addArgument(
                'enviroment',
                InputArgument::REQUIRED,
                'enviroment [ local | development | staging | production ]'
            )
        ;
    }

    public function exec()
    {
        // config
        {

            $enviroment = $this->input->getArgument('enviroment');
            $config = yaml_parse_file(getcwd() . '/env.yml');
            $projectName = $config['project'];
            $machineName = 'apiserver';
            strtolower($projectName);

            //print_r($config);
            foreach ($config['stages'][$enviroment]['services']['nginx']['vhosts'] as $key => $value) {
                if (isset($value['server_alias']) && is_array($value['server_alias'])) {
                    $alias = $value['server_alias'];
                } else {
                    $alias = [];
                }
                $serverConfig[] = [
                    'server_name' => array_merge([$value['server_name']], $alias),
                    'document_root' => $value['document_root'],
                    'port' => $value['port'],
                ];
            }

            $_bootappconf = file_get_contents(getcwd() . '/.docker/nginx/bootapp.conf.tmpl');
            $nginx = '';
            foreach ($serverConfig as $key => $value) {
                $nginx .= strtr($_bootappconf, [
                    '{{project}}' => $projectName . '-' . $value['server_name'][0],
                    '{{document_root}}' => $value['document_root'],
                    '{{server_name}}' => implode(' ', $value['server_name']),
                ]);
            }
            file_put_contents(getcwd() . '/.docker/nginx/bootapp.conf', $nginx);

            $_dockerfile = file_get_contents(getcwd() . '/.docker/nginx/Dockerfile.tmpl');
            $dockerfile = strtr($_dockerfile, [
                '{{php-server}}' => $projectName . '-' . 'php-fpm',
            ]);
            file_put_contents(getcwd() . '/.docker/nginx/Dockerfile', $dockerfile);

            $_php = file_get_contents(getcwd() . '/.docker/php-fpm/bootapp.pool.conf.tmpl');
            $php = strtr($_php, [
                '{{project}}' => $projectName,
            ])
                . PHP_EOL . 'env[ENVIRONMENT] = ' . $enviroment
                . PHP_EOL . 'env[PROJECT_NAME] = ' . $projectName
            ;
            file_put_contents(getcwd() . '/.docker/php-fpm/bootapp.pool.conf', $php);

            //$_compose = file_get_contents(getcwd() . '/docker-compose.yml.tmpl');

            $compose = [
                'version' => "2",
                'services' => [
                    $projectName . '-' . 'application' => [
                        'image' => 'busybox',
                        'volumes' => ['.:/var/www'],
                        'tty' => true,
                    ],
                ],
            ];
            $sed = $config['stages'][$enviroment]['services'];
            unset($sed['php-fpm'], $sed['nginx'], $sed['mysql']);
            $servicesNames = array_map(function ($serviceName) use ($config) {
                return $projectName . '-' . $serviceName;
            }, array_keys($sed));

            foreach ($config['stages'][$enviroment]['services'] as $key => $value) {
                if ($key == 'nginx') {
                    $compose['services'][$projectName . '-' . $key] = [
                        'build' => './.docker/nginx',
                        'expose' => ['80'],
                        'links' => [$projectName . '-' . 'php-fpm'],
                        'volumes_from' => [$projectName . '-' . 'application'],
                        'volumes' => ['./var/log:/var/log/nginx'],
                        'environment' => [
                            'project-name' => $projectName,
                        ],
                    ];
                } else if ($key == 'php-fpm') {
                    $compose['services'][$projectName . '-' . $key] = [
                        'build' => './.docker/php-fpm',
                        'expose' => ['9000'],
                        'links' => $servicesNames,
                        'volumes_from' => [$projectName . '-' . 'application'],
                        'volumes' => ['./var/log:/var/log'],
                        'environment' => [
                            'project-name' => $projectName,
                        ],
                    ];
                } else if ($key == 'mysql') {
                    foreach ($value['server'] as $name => $dsn) {
                        $tmp = explode(':', $dsn);
                        parse_str(str_replace(';', '&', $tmp[1]), $p);
                        $p = array_merge(['prefix' => $tmp[0]], $p);

                        $compose['services'][$projectName . '-' . $p['host']] = [
                            'image' => 'mysql',
                            'expose' => ['3306'],
                            'environment' => [
                                'MYSQL_ROOT_PASSWORD' => $value['root_password'],
                                'MYSQL_DATABASE' => $p['dbname'],
                                'MYSQL_USER' => $value['username'],
                                'MYSQL_PASSWORD' => $value['password'],
                            ],
                        ];
                    }
                }
            }

            yaml_emit_file(getcwd() . '/docker-compose.yml', $compose);

            if (posix_getpwuid(posix_getuid())['name'] == 'root') {
                $this->writeln('└─ Non-root users to run Bootapp');
                exit();
            }
        }

        // osx인지 확인, docker, vbox, docker-machine, docker-compose 설치 확인
        {

            $this->writeln('<info>Initializing docker-machine</info>');

            // osx
            $uname = $this->command('uname -a')->toString();
            if (false === strpos($uname, 'Darwin')) {
                $this->writeln('└─ The operating system not supported.');
                exit();
            }

            // brew
            $brewBinary = $this->command('which brew')->toString();
            if (!$brewBinary) {
                $this->command('/usr/bin/ruby -e "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/master/install)"');
            }
            $this->writeln('└─ Brew Ready!');

            // docker-machine
            $machineBinary = $this->command('which docker-machine')->toString();
            if (!$machineBinary) {
                $this->command($brewBinary . ' cask install dockertoolbox');
                $machineBinary = $this->command('which docker-machine')->toString();
            }
            $this->writeln('└─ Docker toolbox Ready!');

            $dockerBinary = $this->command('which docker')->toString();
            $composeBinary = $this->command('which docker-compose')->toString();

            //osx 에 설치하면 compose관리 가능한데 추천하지 않음.
            //brew install composer
            //brew install php56-phalcon
            //brew install php56
            //brew install php56-yaml

        }

        // docker
        {

            $dockerExists = $this->command($machineBinary . ' status ' . $machineName . ' 2> /dev/null || echo ""')->toString();
            if ('Stopped' === $dockerExists) {
                $this->writeln('└─ Docker is exist!');
                $this->writeln('');
                $this->writeln("<info>Starting docker-machine</info>");
                $this->command($machineBinary . ' start ' . $machineName);
                $this->command($machineBinary . ' regenerate-certs ' . $machineName, 'y');
                $this->writeln('└─ Docker is up and running!');
            } else if ('Saved' === $dockerExists) {
                $this->writeln('└─ Docker is exist!');
                $this->writeln('');
                $this->writeln("<info>Starting docker-machine</info>");
                $this->command($machineBinary . ' start ' . $machineName);
                $this->command($machineBinary . ' regenerate-certs ' . $machineName, 'y');
                $this->writeln('└─ Docker is up and running!');
            } else if ('Error' === $dockerExists) {
                $this->writeln('└─ Docker is exist!');
                $this->writeln('');
                $this->writeln("<info>Starting docker-machine</info>");
                $this->command($machineBinary . ' rm -f ' . $machineName);
                $this->command($machineBinary . ' create --driver virtualbox --virtualbox-memory 2048 ' . $machineName);
                $this->writeln('└─ Docker is up and running!');
            } else if ('Running' === $dockerExists) {
                $this->writeln('└─ Docker is up and running!');
            } else {
                $this->writeln('');
                $this->writeln("<info>Creating docker-machine</info>");
                $this->command($machineBinary . ' create --driver virtualbox --virtualbox-memory 2048 ' . $machineName);
                $this->writeln('└─ Docker is up and running!');
            }

            foreach ($this->command($machineBinary . ' env ' . $machineName)->toArray() as $export) {
                if (1 === preg_match('/export (?P<key>.*)="(?P<value>.*)"/', $export, $match)) {
                    putenv($match['key'] . '=' . $match['value']);
                    $this->writeln('PUTENV: ' . $match['key'] . '=' . $match['value']);
                }
            }

        }

        // compose up
        {

            $this->writeln('');
            $this->writeln('<info>Docker compose</info>');

            //  $message = $this->command($composeBinary.' -p '.$projectName.' down');
            //putenv('COMPOSE_HTTP_TIMEOUT=0');
            // --remove-orphans docker-compose.yml 에 없는 설정 강제로 지우기
            $compose = $this->command($composeBinary . ' -p ' . $projectName . ' up -d --build');

            if (1 === preg_match('#Creating network "([^"]+)" with#', $compose, $match)) {
                $networkName = $match[1];
            } else {
                $networkName = $machineName . '_' . 'default';
            }

        }

        // route 설정
        {

            // docker machine ip
            $this->writeln('');
            $this->writeln('<info>Check docker-machine IP</info>');
            $dockerMachineIp = $this->command($machineBinary . ' ip ' . $machineName);
            $this->writeln('└─ docker-machine IP: ' . $dockerMachineIp);

            // container network
            $containerSubnet = $this->command($dockerBinary . " network inspect --format='{{range .IPAM.Config}}{{.Subnet}}{{end}}' " . $networkName)->toString();

            // route
            $this->writeln('');
            $this->writeln('<info>Add static routes</info>');
            $message = $this->command('sudo route -n delete ' . $containerSubnet . ' ' . $dockerMachineIp);
            $this->writeln('└─ Ok');
            $message = $this->command('sudo route -n add ' . $containerSubnet . ' ' . $dockerMachineIp);
            $this->writeln('└─ Ok');

        }

        // hosts 설정
        {

            $svrs = [];
            foreach ($serverConfig as $v) {
                foreach ($v['server_name'] as $server) {
                    $svrs[] = $server;
                }
            }
            $this->writeln('');
            $this->writeln('<info>Setting hosts</info>');

            $containerIp = $this->command($dockerBinary . " inspect --format='{{.Name}} - {{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $(docker ps -aq)")->toArray();

            foreach ($containerIp as $key => $value) {
                if (false === strpos($value, '/' . $projectName . '-')) {
                    continue;
                }
                if (1 === preg_match('#([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})#', $value, $match)) {
                    $ip = $match[1];
                    $message = $this->command('sudo sed -i -e "/' . $ip . '/d" /etc/hosts');
                    if (false !== strpos($value, 'nginx')) {
                        foreach ($svrs as $server) {
                            $message = $this->command('sudo sed -i -e "/' . $server . '/d" /etc/hosts');
                            $message = $this->command('sudo -- sh -c -e "echo \'' . $ip . ' ' . $server . '\' >> /etc/hosts";');
                        }
                    } else if (false !== strpos($value, 'mysql')) {
                        preg_match('#([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})#', $value, $match);
                        $message = $this->command('sudo -- sh -c -e "echo \'' . $ip . ' ' . $server . '\' >> /etc/hosts";');
                    }
                }
            }
            $this->writeln('└─ Ok');

        }

        // compose install
        {

            $chk = $this->command('if [ -d vendor ]; then echo "true"; else echo "false"; fi')->toBool();
            //if(false === $chk)
            {
                $this->writeln('');
                $this->writeln('<info>Php composer install</info>');

                if (true === $chk) {
                    //$message = $this->command('docker exec -i $(docker ps -f name=php -q) sh -c  "cd /var/www/ && composer update --prefer-dist -vvv --profile"');
                    $this->writeln("Please run './vendor/bin/bootapp composer update -vvv'");
                } else {
                    $message = $this->command($dockerBinary . ' exec -i $(docker ps -f name=php -q) sh -c  "cd /var/www/ && composer install --prefer-dist -vvv --profile"');
                    $this->writeln('└─ Ok');
                }
            }

        }
    }

}
