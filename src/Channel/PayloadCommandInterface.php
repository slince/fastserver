<?php

namespace Waveman\Channel;

interface PayloadCommandInterface extends CommandInterface
{
    /**
     * Return the command key.
     *
     * @return string
     */
    public function getCommandKey(): string;
}