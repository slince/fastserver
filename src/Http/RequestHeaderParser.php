<?php

namespace FastServer\Http;

use FastServer\ProtocolParserInterface;
use React\Http\Io\RequestHeaderParser as BaseRequestHeaderParser;

/**
 * [Internal] Parses an incoming request header from an input stream
 *
 * This is used internally to parse the request header from the connection and
 * then process the remaining connection as the request body.
 *
 * @event headers
 * @event error
 */
class RequestHeaderParser extends  BaseRequestHeaderParser implements ProtocolParserInterface
{
}