<?php

namespace Waveman\Channel;

interface PayloadCommandInterface
{
    /**
     * Return the command key.
     *
     * @return string
     */
    public function getCommandKey(): string;
}