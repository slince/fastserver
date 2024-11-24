<?php

namespace Viso\Http\Parser;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

final class LimitedLengthBody extends EventEmitter implements ReadableStreamInterface
{
    private ReadableStreamInterface $source;

    private int $length;

    private string $buffer;

    private bool $closed = false;

    public function __construct(ReadableStreamInterface $source, int $length)
    {
        $this->source = $source;
        $this->length = $length;

        $this->source->on('data', [$this, 'handleData']);
        $this->source->on('error', [$this, 'handleError']);
        $this->source->on('end', [$this, 'handleEnd']);
        $this->source->on('close', [$this, 'close']);
    }

    /**
     * @internal
     * @param string $chunk
     * @return void
     */
    public function handleData(string $chunk): void
    {
        $this->buffer .= $chunk;

        if (strlen($this->buffer) >= $this->length) {
            $this->emit('data', [substr($this->buffer, 0, $this->length)]);
            $this->emit('end');
            $this->emit('close');
            $this->source->removeListener('data', [$this, 'handleData']);
        }
    }

    /**
     * @internal
     */
    public function handleError(\Exception $e): void
    {
        $this->emit('error', array($e));
        $this->close();
    }

    /**
     * @internal
     */
    public function handleEnd(): void
    {
        if (!$this->closed) {
            $this->handleError(new \Exception('Unexpected end event'));
        }
    }

    public function isReadable(): bool
    {
        return $this->source->isReadable();
    }

    public function pause(): void
    {
        $this->source->pause();
    }

    public function resume(): void
    {
        $this->source->resume();
    }

    public function pipe(WritableStreamInterface $dest, array $options = array()): WritableStreamInterface
    {
        Util::pipe($this, $dest, $options);
        return $dest;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->emit('close');
        $this->removeAllListeners();
    }
}