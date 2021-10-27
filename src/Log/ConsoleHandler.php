<?php

namespace FastServer\Log;

use Monolog\Handler\AbstractProcessingHandler;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleHandler extends AbstractProcessingHandler
{
    /**
     * @var OutputInterface
     */
    protected $output;

    public function __construct(OutputInterface $output, $level = LogLevel::DEBUG, bool $bubble = true)
    {
        $this->output = $output;
        parent::__construct($level, $bubble);
    }

    /**
     * @inheritDoc
     */
    protected function write(array $record): void
    {
        $this->output->write((string) $record['formatted']);
    }
}