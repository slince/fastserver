<?php

namespace Viso\Cluster\Command;

interface PayloadCommandInterface extends CommandInterface
{
    /**
     * Returns the payload of the command.
     *
     * @return mixed
     */
    public function getPayload(): mixed;

    /**
     * Create command instance from payload.
     *
     * @param mixed $payload
     * @return static
     */
    public static function create(mixed $payload): static;
}