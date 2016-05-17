<?php

namespace Peanut\Console\Docker;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\InputStream;

class Up extends \Peanut\Console\Command
{

    protected function configure()
    {
        $this
            ->setName('up')
            ->setDescription('starts and provisions the docker environment')
        ;
    }

    public function exec()
    {
        // config
        {

            $machineName = strtolower('bootappV3');
            $domainNginxName = 'dev.local.com';
            $domainMysqlName = 'dev.mysql.local.com';

        }

        // osx인지 확인, docker, vbox, docker-machine, docker-compose 설치 확인
        {

            $this->writeln('<info>Initializing docker-machine</info>');

            // osx
            $uname = $this->command('uname -a')->toString();
            if(false === strpos($uname, 'Darwin'))
            {
                $this->writeln('└─ The operating system not supported.');
                exit();
            }

            // brew
            $brewBinary = $this->command('which brew')->toString();
            if(!$brewBinary)
            {
                $this->command('/usr/bin/ruby -e "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/master/install)"');
            }
            $this->writeln('└─ Brew Ready!');

            // docker-machine
            $machineBinary = $this->command('which docker-machine')->toString();
            if(!$machineBinary)
            {
                $this->command($brewBinary.' cask install dockertoolbox');
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

            $dockerExists = $this->command($machineBinary.' status '.$machineName.' 2> /dev/null || echo ""')->toString();
            if('Stopped' === $dockerExists)
            {
                $this->writeln('└─ Docker is exist!');
                $this->writeln('');
                $this->writeln("<info>Starting docker-machine</info>");
                $this->command($machineBinary.' start '.$machineName);
                $this->command($machineBinary.' regenerate-certs '.$machineName, 'y');
                $this->writeln('└─ Docker is up and running!');
            }
            else if('Saved' === $dockerExists)
            {
                $this->writeln('└─ Docker is exist!');
                $this->writeln('');
                $this->writeln("<info>Starting docker-machine</info>");
                $this->command($machineBinary.' start '.$machineName);
                $this->command($machineBinary.' regenerate-certs '.$machineName, 'y');
                $this->writeln('└─ Docker is up and running!');
            }
            else if('Running' === $dockerExists)
            {
                $this->writeln('└─ Docker is up and running!');
            }
            else
            {
                $this->writeln('');
                $this->writeln("<info>Creating docker-machine</info>");
                $this->command($machineBinary.' create --driver virtualbox --virtualbox-memory 2048 '.$machineName);
                $this->writeln('└─ Docker is up and running!');
            }

            foreach($this->command($machineBinary.' env '.$machineName)->toArray() as $export)
            {
                if(1 === preg_match('/export (?P<key>.*)="(?P<value>.*)"/', $export, $match))
                {
                    putenv($match['key'].'='.$match['value']);
                }
            }

        }

        // compose up
        {

            $this->writeln('');
            $this->writeln('<info>Docker compose</info>');

            $message = $this->command($composeBinary.' -p '.$machineName.' down');
            putenv('COMPOSE_HTTP_TIMEOUT=10');
            $compose = $this->command($composeBinary.' -p '.$machineName.' up -d --build --remove-orphans');

            if(1 === preg_match('#Creating network "('.$machineName.'[^"]+)" with#', $compose, $match))
            {
                $networkName = $match[1];
            }
            else
            {
                $networkName = $machineName.'_'.'default';
            }

        }

        // route 설정
        {

            // docker machine ip
            $this->writeln('');
            $this->writeln('<info>Check docker-machine IP</info>');
            $dockerMachineIp = $this->command($machineBinary.' ip '.$machineName);
            $this->writeln('└─ docker-machine IP: '. $dockerMachineIp);

            // container network
            $containerSubnet = $this->command($dockerBinary." network inspect --format='{{range .IPAM.Config}}{{.Subnet}}{{end}}' ".$networkName)->toString();

            // route
            $this->writeln('');
            $this->writeln('<info>Add static routes</info>');
            $message = $this->command('sudo route -n delete '.$containerSubnet.' '.$dockerMachineIp);
            $this->writeln('└─ Ok');
            $message = $this->command('sudo route -n add '.$containerSubnet.' '.$dockerMachineIp);
            $this->writeln('└─ Ok');

        }

        // hosts 설정
        {

            $this->writeln('');
            $this->writeln('<info>Setting hosts</info>');

            $containerIp = $this->command($dockerBinary." inspect --format='{{.Name}} - {{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $(docker ps -aq)")->toArray();

            $message = $this->command('sudo sed -i -e "/'.$domainNginxName.'/d" /etc/hosts');
            $message = $this->command('sudo sed -i -e "/'.$domainMysqlName.'/d" /etc/hosts');

            foreach($containerIp as $key => $value)
            {
                if(1 === preg_match('#([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})#', $value, $match))
                {
                    $ip = $match[1];
                    if(false !== strpos($value, 'nginx'))
                    {
                        $message = $this->command('sudo -- sh -c -e "echo \''.$ip.' '.$domainNginxName.'\' >> /etc/hosts";');
                    }
                    else if(false !== strpos($value, 'mysql'))
                    {
                        preg_match('#([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})#', $value, $match);
                        $message = $this->command('sudo -- sh -c -e "echo \''.$ip.' '.$domainMysqlName.'\' >> /etc/hosts";');
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

                if(true === $chk)
                {
                    //$message = $this->command('docker exec -i $(docker ps -f name=php -q) sh -c  "cd /var/www/ && composer update --prefer-dist -vvv --profile"');
                    $this->writeln("Please run './vendor/bin/bootapp composer update -vvv'");
                }
                else
                {
                    $message = $this->command($dockerBinary.' exec -i $(docker ps -f name=php -q) sh -c  "cd /var/www/ && composer install --prefer-dist -vvv --profile"');
                    $this->writeln('└─ Ok');
                }
            }
        }
    }
}
