<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Container\Container;
use Hit\Exception\MiddlewareExhaustedException;
use Hit\Middleware\Middleware;
use Hit\Middleware\Runner;
use InvalidArgumentException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionProperty;

// --- Test fixtures -------------------------------------------------------

class MwPassThrough extends Middleware {
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
		return $handler->handle($request);
	}
}

class MwShortCircuit extends Middleware {
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
		return new Response(200, ['X-MW' => 'shortcircuit'], 'short-circuited');
	}
}

class MwDecorator extends Middleware {
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
		$response = $handler->handle($request);

		return $response->withHeader('X-Decorated', 'true');
	}
}

class MwMutatesRequest extends Middleware {
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
		return $handler->handle($request->withAttribute('seen', true));
	}
}

class MwReadsAttribute extends Middleware {
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
		$seen = $request->getAttribute('seen', false);

		return new Response(200, [], $seen ? 'saw it' : 'missed it');
	}
}

class NotMiddleware {
	public function __construct(
		public Container $c,
	) {}
}

// --- Runner construction ------------------------------------------------

test('Runner implements PSR-15 RequestHandlerInterface', function () {
	$runner = new Runner(new Container(), []);
	expect($runner)->toBeInstanceOf(RequestHandlerInterface::class);
});

test('Runner accepts a stack of Middleware subclass strings', function () {
	$runner = new Runner(new Container(), [MwPassThrough::class, MwShortCircuit::class]);
	expect($runner)->toBeInstanceOf(Runner::class);
});

test('Runner rejects non-Middleware class strings', function () {
	expect(fn() => new Runner(new Container(), [NotMiddleware::class]))
		->toThrow(InvalidArgumentException::class);
});

test('Runner rejects non-existent class strings', function () {
	expect(fn() => new Runner(new Container(), ['NoSuchClassExists']))
		->toThrow(InvalidArgumentException::class);
});

test('Runner rejects non-string stack entries', function () {
	expect(fn() => new Runner(new Container(), [42]))
		->toThrow(InvalidArgumentException::class);
});

test('Runner accepts an empty stack', function () {
	$runner = new Runner(new Container(), []);
	expect($runner)->toBeInstanceOf(Runner::class);
});

// --- handle(): execution ------------------------------------------------

test('handle throws MiddlewareExhaustedException on empty stack', function () {
	$runner = new Runner(new Container(), []);
	$runner->handle(new ServerRequest('GET', '/'));
})->throws(MiddlewareExhaustedException::class);

test('short-circuiting middleware returns its response without touching the rest of the stack', function () {
	$runner = new Runner(new Container(), [MwShortCircuit::class, MwPassThrough::class]);
	$response = $runner->handle(new ServerRequest('GET', '/'));

	expect($response->getStatusCode())->toBe(200);
	expect($response->getHeaderLine('X-MW'))->toBe('shortcircuit');
	expect((string) $response->getBody())->toBe('short-circuited');
});

test('pass-through middleware delegates down the stack', function () {
	$runner = new Runner(new Container(), [MwPassThrough::class, MwShortCircuit::class]);
	$response = $runner->handle(new ServerRequest('GET', '/'));

	expect($response->getStatusCode())->toBe(200);
	expect($response->getHeaderLine('X-MW'))->toBe('shortcircuit');
});

test('decorator middleware wraps the response of the next handler', function () {
	$runner = new Runner(new Container(), [MwDecorator::class, MwShortCircuit::class]);
	$response = $runner->handle(new ServerRequest('GET', '/'));

	expect($response->getHeaderLine('X-MW'))->toBe('shortcircuit');
	expect($response->getHeaderLine('X-Decorated'))->toBe('true');
});

test('middleware can mutate the request for downstream handlers', function () {
	$runner = new Runner(new Container(), [MwMutatesRequest::class, MwReadsAttribute::class]);
	$response = $runner->handle(new ServerRequest('GET', '/'));

	expect((string) $response->getBody())->toBe('saw it');
});

test('downstream handler sees unmodified request when no mutation occurs', function () {
	$runner = new Runner(new Container(), [MwReadsAttribute::class]);
	$response = $runner->handle(new ServerRequest('GET', '/'));

	expect((string) $response->getBody())->toBe('missed it');
});

test('a single middleware that short-circuits works end-to-end', function () {
	$runner = new Runner(new Container(), [MwShortCircuit::class]);
	$response = $runner->handle(new ServerRequest('POST', '/submit'));

	expect($response->getStatusCode())->toBe(200);
});

test('handle returns a PSR-7 ResponseInterface', function () {
	$runner = new Runner(new Container(), [MwShortCircuit::class]);
	$response = $runner->handle(new ServerRequest('GET', '/'));

	expect($response)->toBeInstanceOf(ResponseInterface::class);
});

// --- Middleware base class ----------------------------------------------

test('Middleware implements PSR-15 MiddlewareInterface', function () {
	$mw = new MwPassThrough(new Container());
	expect($mw)->toBeInstanceOf(\Psr\Http\Server\MiddlewareInterface::class);
});

test('Middleware default process delegates to the handler', function () {
	$mw = new MwPassThrough(new Container());
	$expected = new Response(204);
	$handler = new class($expected) implements RequestHandlerInterface {
		public function __construct(
			private ResponseInterface $response,
		) {}

		public function handle(ServerRequestInterface $request): ResponseInterface {
			return $this->response;
		}
	};

	$response = $mw->process(new ServerRequest('GET', '/'), $handler);

	expect($response)->toBe($expected);
});

test('Middleware receives the container via constructor', function () {
	$container = new Container();
	$mw = new MwPassThrough($container);

	$reflection = new ReflectionProperty(Middleware::class, 'container');

	expect($reflection->getValue($mw))->toBe($container);
});
