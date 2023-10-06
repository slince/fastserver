<?php

namespace Waveman\Server\Worker;

final class FakeWorker extends Worker
{
    /**
     * {@inheritdoc}
     */
    protected function doStart(): void
    {
        $this->run();
    }

    /**
     * {@inheritdoc}
     */
    protected function doClose(bool $graceful = false): void
    {
        $this->handleClose($graceful);
    }

    protected function doAlive(): void
    {
        // ignore this
    }
}