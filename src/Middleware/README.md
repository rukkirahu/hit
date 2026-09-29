# Middleware

PSR-15 middleware stack with a tiny container-aware base class.

## Usage

```php
use Hit\Middleware\Middleware;
use Hit\Middleware\Runner;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LogMiddleware extends Middleware {
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        // ... before ...
        $response = $handler->handle($request);
        // ... after ...
        return $response;
    }
}

$runner = new Runner($container, [
    LogMiddleware::class,
    AuthMiddleware::class,
    // ... innermost should produce a Response
]);

$response = $runner->handle($request);
```

## Classes

| Class | Description |
| --- | --- |
| `Middleware` | Abstract PSR-15 middleware that receives a `Container`. Default `process()` passes the request through. |
| `Runner` | PSR-15 request handler that walks the stack, instantiating each middleware class with the container until one produces a response. |

## Exceptions

`Runner::handle()` throws `MiddlewareExhaustedException` if the stack runs out without returning a response.
