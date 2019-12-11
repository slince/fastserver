<?php

namespace FastServer\Process;

class FakeProcess implements ProcessInterface
{
    /**
     * @var callable
     */
    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * {@inheritdoc }
     */
    public function start($blocking = true)
    {
        call_user_func($this->callback);
    }

    /**
     * {@inheritdoc }
     */
    public function stop()
    {
    }

    /**
     * {@inheritdoc }
     */
    public function wait()
    {
    }

    /**
     * {@inheritdoc }
     */
    public function signal($signal)
    {
    }

    public function getPid()
    {
        return getmygid();
    }


}