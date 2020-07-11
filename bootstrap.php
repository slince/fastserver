<?php

if (!extension_loaded('pcntl')) {
    define ('SIGCHLD', 17);
    define ('SIGTERM', 15);
    define ('SIGKILL', 9);
    define ('SIGUSR1', 10);
}