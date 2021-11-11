<?php

declare(strict_types=1);

/*
 * This file is part of the fastserver/fastserver package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FastServer\Process;

use FastServer\Exception\InvalidArgumentException;
use FastServer\Exception\RuntimeException;

final class Fifo
{
    /**
     * @var string
     */
    protected $pathname;

    /**
     * @var int
     */
    protected $permission;

    protected $stream;

    public function __construct($pathname, $permission = 0666)
    {
        if (($exists = file_exists($pathname)) && 'fifo' !== filetype($pathname)) {
            throw new InvalidArgumentException(sprintf('The file "%s" already exists, but is not a valid fifo file', $pathname));
        }
        if (!$exists && !posix_mkfifo($pathname, $permission)) {
            throw new RuntimeException(sprintf('Cannot create the fifo file "%s"', $pathname));
        }
        $this->pathname = $pathname;
        $this->permission = $permission;
    }

    /**
     * Open the fifo.
     *
     * @param string $mode
     * @return false|resource
     */
    public function open(string $mode)
    {
        if (!is_null($this->stream)) {
            return $this->stream;
        }
        return $this->stream = fopen($this->pathname, $mode);
    }

    /**
     * Close the fifo.
     */
    public function close()
    {
        is_resource($this->stream) && fclose($this->stream);
        @unlink($this->pathname);
    }
}