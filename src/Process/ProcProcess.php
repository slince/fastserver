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

namespace Wave\Process;

use \Symfony\Component\Process\Process as BaseProcess;
final class ProcProcess extends BaseProcess implements ProcessInterface
{


    public function terminate(int $signal = null)
    {
        // TODO: Implement terminate() method.
    }

    public function getStdin()
    {
        // TODO: Implement getStdin() method.
    }

    public function getStdout()
    {
        // TODO: Implement getStdout() method.
    }

    public function getStderr()
    {
        // TODO: Implement getStderr() method.
    }
}