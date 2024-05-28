<?php

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as LaravelResponse;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Inertia\Exceptions\Handler;
use Inertia\ResponseFactory;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

dataset('methods', [
    ['GET'],
    ['POST'],
    ['PUT'],
    ['PATCH'],
    ['DELETE'],
]);

dataset('response_type', [
    'general html (enabled debug)' => [$debug = true, $inertia = false],
    'inertia html (disabled debug)' => [$debug = false, $inertia = false],
    'inertia json (disabled debug)' => [$debug = false, $inertia = true],
]);

describe('http error response', function () {
    dataset('exceptions', [
        [403, 'Forbidden', fn () => new AccessDeniedHttpException('This action is unauthorized.')],
        [404, 'Not Found', fn () => new NotFoundHttpException('The route / could not be found.')],
        [500, 'Server Error', fn () => new ErrorException('Undefined variable $fail')],
        [503, 'Service Unavailable', fn () => new HttpException(503, 'Service Unavailable')],
    ]);

    test('general html (enabled debug)', function (int $code, string $message, Exception $exception, string $method) {
        Config::set('app.debug', true);

        $handler = new Handler(app(ResponseFactory::class));

        $request = Request::create('http://localhost/', $method);

        $response = new LaravelResponse(
            content: "<!DOCTYPE html><html><head><title>{$message}</title></head><body><h1>{$code} {$message}</h1></body></html>",
            status: $code
        );

        $response = $handler->handle($request, $response, $exception);

        expect($response->getStatusCode())->toBe($code);
        expect($response->getContent())->toContain('<!DOCTYPE html>');
        expect($response->getContent())->not->toContain('@inertia');
    })->with('exceptions')->with('methods');

    test('inertia html (disabled debug)', function (int $code, string $message, Exception $exception, string $method) {
        Config::set('app.debug', false);

        $handler = new Handler(app(ResponseFactory::class));

        $request = Request::create('http://localhost/', $method);

        $response = new LaravelResponse(
            content: "<!DOCTYPE html><html><head><title>{$message}</title></head><body><h1>{$code} {$message}</h1></body></html>",
            status: $code
        );

        $response = $handler->handle($request, $response, $exception);

        expect($response->getStatusCode())->toBe($code);
        expect($response->getContent())->toContain('<!DOCTYPE html>');
        expect($response->getContent())->toContain('@inertia');
    })->with('exceptions')->with('methods');

    test('inertia json (disabled debug)', function (int $code, string $message, Exception $exception, string $method) {
        Config::set('app.debug', false);

        $handler = new Handler(app(ResponseFactory::class));

        $request = Request::create('http://localhost/', $method);
        $request->headers->set('X-Inertia', 'true');

        $response = new LaravelResponse(
            content: "<!DOCTYPE html><html><head><title>{$message}</title></head><body><h1>{$code} {$message}</h1></body></html>",
            status: $code
        );

        $response = $handler->handle($request, $response, $exception);

        expect($response->getStatusCode())->toBe($code);
        expect($response->getContent())->toBeJson('{"component":"Error","props":{"code":'.$code.',"message":"'.$message.'"},"url":"\/","version":""}');
    })->with('exceptions')->with('methods');
});

test('419 page expired', function (bool $debug, bool $inertia) {
    Config::set('app.debug', $debug);
    Session::setPreviousUrl('http://localhost/form');

    $handler = new Handler(app(ResponseFactory::class));

    $request = Request::create('http://localhost/save', 'POST');
    if ($inertia) {
        $request->headers->set('X-Inertia', 'true');
    }

    $exception = new TokenMismatchException('CSRF token mismatch.');
    $exception = new HttpException(419, $exception->getMessage(), $exception);

    $response = new LaravelResponse(
        content: '<!DOCTYPE html><html><head><title>Page Expired</title></head><body><h1>419 Page Expired</h1></body></html>',
        status: 419
    );

    /** @var Illuminate\Http\RedirectResponse */
    $response = $handler->handle($request, $response, $exception);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->getStatusCode())->toBe(302);
    expect($response->getContent())->toContain('Redirecting to http://localhost/form');

    $session = $response->getSession();
    expect($session->get('error'))->toBe('The page expired, please try again.');
})->with('response_type');

test('422 validation failed', function (bool $debug, bool $inertia) {
    Config::set('app.debug', $debug);
    Session::setPreviousUrl('http://localhost/form');

    $handler = new Handler(app(ResponseFactory::class));

    $request = Request::create('http://localhost/save', 'POST');
    if ($inertia) {
        $request->headers->set('X-Inertia', 'true');
    }

    $exception = ValidationException::withMessages([
        'name' => 'The name field is required.',
    ]);

    $response = new RedirectResponse('http://localhost/form');
    $response->setSession(app('session.store'));
    $response->withErrors($exception->errors(), $exception->errorBag);

    /** @var Illuminate\Http\RedirectResponse */
    $response = $handler->handle($request, $response, $exception);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->getStatusCode())->toBe(302);
    expect($response->getContent())->toContain('Redirecting to http://localhost/form');

    $session = $response->getSession();
    expect($session->get('errors')->toArray())->toBe([
        'name' => ['The name field is required.'],
    ]);
})->with('response_type');

test('429 too many requests', function (bool $debug, bool $inertia) {
    Config::set('app.debug', $debug);
    Session::setPreviousUrl('http://localhost/form');

    $handler = new Handler(app(ResponseFactory::class));

    $request = Request::create('http://localhost/save', 'POST');
    if ($inertia) {
        $request->headers->set('X-Inertia', 'true');
    }

    $exception = new ThrottleRequestsException('Too Many Attempts.');

    $response = new LaravelResponse(
        content: '<!DOCTYPE html><html><head><title>Too Many Requests</title></head><body><h1>429 Too Many Requests</h1></body></html>',
        status: 429
    );

    /** @var Illuminate\Http\RedirectResponse */
    $response = $handler->handle($request, $response, $exception);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->getStatusCode())->toBe(302);
    expect($response->getContent())->toContain('Redirecting to http://localhost/form');

    $session = $response->getSession();
    expect($session->get('error'))->toBe('Too Many Requests');
})->with('response_type');
