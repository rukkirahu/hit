<?php

declare(strict_types=1);

namespace Hit\Exception;

use RuntimeException;

/**
 * Class MiddlewareExhaustedException
 * Thrown when the middleware stack is exhausted without producing a Response.
 * @package Hit\Exception
 */
class MiddlewareExhaustedException extends RuntimeException {}
