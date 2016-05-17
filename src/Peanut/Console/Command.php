<?php

namespace Peanut\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class Command extends \Symfony\Component\Console\Command\Command
{

    const v = OutputInterface::VERBOSITY_VERBOSE;
    const vv = OutputInterface::VERBOSITY_VERY_VERBOSE;
    const d = OutputInterface::VERBOSITY_DEBUG;
    const n = OutputInterface::VERBOSITY_NORMAL;

    public $input;
    public $output;

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        return $this->exec();
        exit();
    }

    public function command($command, $input = null)
    {
        $timeout = 600;
        $command = str_replace(PHP_EOL, '', $command);
        if ($this->output->getVerbosity()) {
            $this->output->writeln("Run: \e[1m".$command."\e[0m");
        }

        $process = new Process($command);
        $process->setTimeout($timeout);
        if($input)
        {
            $process->setInput($input);
        }
        $callback = function ($type, $buffer)
        {
            if (OutputInterface::VERBOSITY_DEBUG <= $this->output->getVerbosity()) {
                if (Process::ERR === $type) {
                    $msg = implode(PHP_EOL,array_map(function ($line) {
                        return "<fg=red>></fg=red> \033[1;30m".$line."\033[0m";
                    }, explode(PHP_EOL, trim($buffer))));
                } else {
                    $msg = implode(PHP_EOL,array_map(function ($line) {
                        return "<info>></info> \033[1;30m".$line."\033[0m";
                    }, explode(PHP_EOL, trim($buffer))));
                }
                $this->output->write($msg, OutputInterface::OUTPUT_RAW);
            }
        };
        $callback = $callback->bindTo($this);
        $process->run($callback);

        if (!$process->isSuccessful() && $process->getErrorOutput()) {
            throw new \Peanut\Console\RuntimeException($process->getErrorOutput());
        }
        return new \Peanut\Console\Command\Result($process->getErrorOutput().$process->getOutput());
    }

    public function isFile($file)
    {
        return $this->runLocal('if [ -f '.$file.' ]; then echo "true"; else echo "false"; fi')->toBool();
    }

    public function write($s)
    {
        return $this->output->write($s);
    }

    public function writeln($s)
    {
        return $this->output->writeln($s);
    }

}
