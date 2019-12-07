<?php

namespace FastServer\Process;

interface ProcessInterface
{
    /**
     * Send signal to the process.
     *
     * @param int $signal
     */
    public function signal($signal);

    /**
     * Register signal handler.
     *
     * @param $signal
     * @param callable $handler
     */
    public function onSignal($signal, callable $handler);

    /**
     * Get input stream.
     *
     * @return resource
     */
    public function getInput();

    /**
     * Gets the output stream.
     *
     * @return resource
     */
    public function getOutput();

    /**
     * Starts the process.
     *
     * @param bool $blocking
     */
    public function start($blocking = true);

    /**
     * Wait for the process exit.
     */
    public function wait();

    /**
     * Closes the process.
     */
    public function stop();

    /**
     * Gets the process id.
     *
     * @return int
     */
    public function getPid();
}