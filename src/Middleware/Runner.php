<?php

declare(strict_types=1);

namespace Hit\Middleware;

use Hit\Container\Container;
use Hit\Exception\MiddlewareExhaustedException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class Runner
 * Middleware stack runner
 * @package Hit\Stack
 */
class Runner implements RequestHandlerInterface {
	/**
	 * @var Container
	 */
	protected Container $container;
	/**
	 * @var string[]
	 */
	protected array $stack = [];

	/**
	 * StackRunner constructor.
	 * @param Container $container
	 * @param string[] $stack Middleware classnames
	 */
	public function __construct(Container $container, array $stack) {
		$this->container = $container;
		foreach ($stack as $class) {
			if (!is_a($class, Middleware::class, true)) {
				throw new InvalidArgumentException('Stack entries must be class strings extending ' . Middleware::class . ', got: ' . $class);
			}
		}
		$this->stack = $stack;
	}

	/**
	 * @param  ServerRequestInterface $request
	 * @return ResponseInterface
	 * @throws MiddlewareExhaustedException
	 */
	public function handle(ServerRequestInterface $request): ResponseInterface {
		$class = array_shift($this->stack);
		if (is_string($class)) {
			// @mago-expect analysis:unknown-class-instantiation
			$instance = new $class($this->container);
			if ($instance instanceof Middleware) {
				return $instance->process($request, $this);
			}
		}

		throw new MiddlewareExhaustedException(
			'Middleware stack exhausted without producing a Response' . ($class !== null ? ' (last entry: ' . $class . ')' : ''),
			1,
		);
	}
}
