<?php

declare(strict_types=1);

namespace Hit\Middleware;

use Hit\Container\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class Middleware
 * @package Hit\Middleware\Abstract
 */
abstract class Middleware implements MiddlewareInterface {
	/**
	 * @var Container
	 */
	protected Container $container;

	/**
	 * MiddlewareAbstract constructor.
	 * @param Container $container
	 */
	public function __construct(Container $container) {
		$this->container = $container;
	}

	/**
	 * @param  ServerRequestInterface $request
	 * @param  RequestHandlerInterface $handler
	 * @return ResponseInterface
	 */
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
		return $handler->handle($request);
	}
}
