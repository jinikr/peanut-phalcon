<?php
namespace Peanut\Console\Docker;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;

class Composer extends \Peanut\Console\Command
{
    protected function configure()
    {
        $this
            ->setName('composer')
            ->setDescription('php composer')
            ->addArgument(
                'options',
                InputArgument::REQUIRED,
                'install | update'
            );
    }

    public function exec()
    {
        // config
        {
            //$machineName = strtolower('bootappV3');
            $options = $this->input->getArgument('options');
        }

        // compose install
        {
            $this->writeln('<info>Php composer install</info>');

            if ('update' === $options) {
                $message = $this->command('docker exec -i $(docker ps -f name=php -q) sh -c  "cd /var/www/ && composer update --prefer-dist -vvv --profile"');
            } else {
                $message = $this->command('docker exec -i $(docker ps -f name=php -q) sh -c  "cd /var/www/ && composer install --prefer-dist -vvv --profile"');
            }

            $this->writeln('└─ Ok');
        }
    }
}
