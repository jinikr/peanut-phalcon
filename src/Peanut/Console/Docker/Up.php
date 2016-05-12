<?php

namespace Peanut\Console\Docker;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
        $machineName = 'bootappV2';
        $machineIp = '192.168.66.200/24';
        $domainNginxName = 'dev.local.com';
        $domainMysqlName = 'dev.mysql.local.com';
        $dockerNginxIp = '172.26.0.101';
        $dockerMysqlIp = '172.26.0.102';

        $this->writeln('<info>Initializing docker-machine</info>');
        $this->command('docker-machine rm -f '.$machineName);

        $dockerExists = $this->command('docker-machine status '.$machineName.' 2> /dev/null || echo ""')->toString();
        if('Stopped' === $dockerExists)
        {
            $this->writeln('└─ Docker is exist!');
            $this->writeln('');
            $this->writeln("<info>Restarting docker-machine</info>");
            $this->command('/usr/local/bin/docker-machine restart '.$machineName);
            $this->writeln('└─ Docker is up and running!');
        }
        else if('Running' === $dockerExists)
        {
            //$this->writeln('└─ Docker is exist!');
            //$this->writeln('');
            //$this->writeln("<info>Restarting docker-machine</info>");
            //$this->command('/usr/local/bin/docker-machine restart '.$machineName);
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

        $this->writeln('');
        $this->writeln('<info>Check docker-machine IP</info>');
        $dockerIp = $this->command('docker-machine ip '.$machineName);
        $this->writeln('└─ docker-machine IP: '. $dockerIp);

        $this->writeln('');
        $this->writeln('<info>Add static routes</info>');
        $message = $this->command('sudo route -n delete 172.26.0.0/16 '.$dockerIp);
        $this->writeln('└─ Ok');
        $message = $this->command('sudo route -n add 172.26.0.0/16 '.$dockerIp);
        $this->writeln('└─ Ok');

        $this->writeln('');
        $this->writeln('<info>Setting hosts</info>');
        $message = $this->command('sudo sed -i -e "/'.$domainNginxName.'/d" /etc/hosts');
        $message = $this->command('sudo sed -i -e "/'.$domainMysqlName.'/d" /etc/hosts');
        $message = $this->command('sudo -- sh -c -e "echo \''.$dockerNginxIp.' '.$domainNginxName.'\' >> /etc/hosts";');
        $message = $this->command('sudo -- sh -c -e "echo \''.$dockerMysqlIp.' '.$domainMysqlName.'\' >> /etc/hosts";');
        $this->writeln('└─ Ok');

        $message = $this->command('docker-compose up -d');

        $message = $this->command('docker exec -ti $(docker ps -f name=php -q) sh -c  "cd /var/www/ && composer install"');

    }
}
