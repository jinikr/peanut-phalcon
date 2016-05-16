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
        // osx인지 확인, docker, vbox, docker-machine, docker-compose 설치 확인,ååå
        $this->writeln('<info>Initializing docker-machine</info>');

        $uname = $this->command('uname -a')->toString();
        if(false === strpos($uname, 'Darwin'))
        {
            $this->writeln('└─ The operating system not supported.');
            exit();
        }

        $brew = $this->command('which brew')->toString();
        if(!$brew)
        {
            $this->command('/usr/bin/ruby -e "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/master/install)"');
        }
        $this->writeln('└─ Brew Ready!');

        $this->command('brew cask install dockertoolbox');
        $this->writeln('└─ Docker Ready!');

        $machineName = 'bootappV2';
        $machineIp = '192.168.66.200/24';

        $domainNginxName = 'dev.local.com';
        $domainMysqlName = 'dev.mysql.local.com';

        $dockerNetwork = '172.26.0.0';
        $dockerNginxIp = '172.26.0.101';
        $dockerMysqlIp = '172.26.0.102';

        //$this->command('docker-machine rm -f '.$machineName);
        $dockerExists = $this->command('docker-machine status '.$machineName.' 2> /dev/null || echo ""')->toString();
        if('Stopped' === $dockerExists)
        {
            $this->writeln('└─ Docker is exist!');
            $this->writeln('');
            $this->writeln("<info>Starting docker-machine</info>");
            $this->command('/usr/local/bin/docker-machine start '.$machineName);
            $this->command('/usr/local/bin/docker-machine regenerate-certs '.$machineName, 'y');
            $this->writeln('└─ Docker is up and running!');
        }
        else if('Saved' === $dockerExists)
        {
            $this->writeln('└─ Docker is exist!');
            $this->writeln('');
            $this->writeln("<info>Starting docker-machine</info>");
            $this->command('/usr/local/bin/docker-machine start '.$machineName);
            $this->command('/usr/local/bin/docker-machine regenerate-certs '.$machineName, 'y');
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
            $this->command('/usr/local/bin/docker-machine create --driver virtualbox --virtualbox-memory 2048 --virtualbox-hostonly-cidr "'.$machineIp.'" '.$machineName);
            $this->writeln('└─ Docker is up and running!');
        }

        foreach($this->command('docker-machine env '.$machineName)->toArray() as $export)
        {
            preg_match('/export (?P<key>.*)="(?P<value>.*)"/', $export, $match);
            if(true === isset($match['key']) && true === isset($match['value']))
            {
                putenv($match['key'].'='.$match['value']);
            }
        }

        // container restart
        $this->writeln('');
        $this->writeln('<info>Check docker-machine IP</info>');
        $dockerIp = $this->command('docker-machine ip '.$machineName);
        $this->writeln('└─ docker-machine IP: '. $dockerIp);

        $this->writeln('');
        $this->writeln('<info>Add static routes</info>');
        $message = $this->command('sudo route -n delete '.$dockerNetwork.'/16 '.$dockerIp);
        $this->writeln('└─ Ok');
        $message = $this->command('sudo route -n add '.$dockerNetwork.'/16 '.$dockerIp);
        $this->writeln('└─ Ok');

        $this->writeln('');
        $this->writeln('<info>Setting hosts</info>');
        $message = $this->command('sudo sed -i -e "/'.$domainNginxName.'/d" /etc/hosts');
        $message = $this->command('sudo sed -i -e "/'.$domainMysqlName.'/d" /etc/hosts');
        $message = $this->command('sudo -- sh -c -e "echo \''.$dockerNginxIp.' '.$domainNginxName.'\' >> /etc/hosts";');
        $message = $this->command('sudo -- sh -c -e "echo \''.$dockerMysqlIp.' '.$domainMysqlName.'\' >> /etc/hosts";');
        $this->writeln('└─ Ok');

        $this->writeln('');
        $this->writeln('<info>Docker compose up</info>');

        $message = $this->command('docker-compose down');
        putenv('COMPOSE_HTTP_TIMEOUT=10');
        $message = $this->command('docker-compose up -d --build --remove-orphans');

        // compose install
        $this->writeln('');
        $this->writeln('<info>Php composer install</info>');

        $chk = $this->command('if [ -d vendor ]; then echo "true"; else echo "false"; fi')->toBool();
        if(true === $chk)
        {
            $message = $this->command('docker exec -i $(docker ps -f name=php -q) sh -c  "cd /var/www/ && composer update"');
        }
        else
        {
            $message = $this->command('docker exec -i $(docker ps -f name=php -q) sh -c  "cd /var/www/ && composer install"');
        }
        $this->writeln('└─ Ok');

    }
}
