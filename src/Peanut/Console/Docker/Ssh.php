<?php

namespace Peanut\Console\Docker;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

class Ssh extends \Peanut\Console\Command
{

    const V = OutputInterface::VERBOSITY_VERBOSE;
    const VV = OutputInterface::VERBOSITY_VERY_VERBOSE;
    const VVV = OutputInterface::VERBOSITY_DEBUG;
    const N = OutputInterface::VERBOSITY_NORMAL;

    protected function configure()
    {
        $this
            ->setName('ssh')
            ->setDescription('connects to docker machine via SSH')
        ;
    }

    protected function exec()
    {
        $machineName = 'apiserver';
        system("docker-machine ssh " . $machineName . " > `tty`"); //$this->command('docker-machine ssh '.$machineName');
    }

}
