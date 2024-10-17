<?php

namespace Viso\Cluster\Worker;

enum Type: string
{
    case FORK = 'fork';

    case PROC = 'proc';
}
