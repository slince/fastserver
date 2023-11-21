<?php

include __DIR__ . '/../vendor/autoload.php';

use Viso\Channel\Frame;
use Viso\Channel\FrameParser;

$parser = new FrameParser();

$frame = new Frame(1, 4, ['id' => 'hello']);

$message = Frame::pack($frame);

$parser->push($message);

var_dump($parser->evaluate());