<?php

namespace Peanut\Console\Docker;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Ssh extends \Peanut\Console\Command
{

    const v = OutputInterface::VERBOSITY_VERBOSE;
    const vv = OutputInterface::VERBOSITY_VERY_VERBOSE;
    const vvv = OutputInterface::VERBOSITY_DEBUG;
    const n = OutputInterface::VERBOSITY_NORMAL;

    protected function configure()
    {
        $this
            ->setName('ssh')
            ->setDescription('connects to docker machine via SSH')
        ;
    }

    protected function exec()
    {
        $machineName = 'bootappV2';
        system("docker-machine ssh ".$machineName." > `tty`");//$this->command('docker-machine ssh '.$machineName');
    }
}
