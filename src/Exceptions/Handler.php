<?php

namespace Inertia\Exceptions;

use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Inertia\ResponseFactory;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler
{
    /**
     * Returns the error view name.
     *
     * @var string
     */
    protected $errorView = 'Error';

    /**
     * Returns the flash error message key name.
     *
     * @var string
     */
    protected $errorMessageKey = 'error';

    /**
     * Returns the messages transform callback.
     *
     * @var callable|null
     */
    protected $messagesCallback;

    /**
     * Create a new inertia exception handler instance.
     *
     * @return void
     */
    public function __construct(protected ResponseFactory $inertia)
    {
        //
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function handle(Request $request, SymfonyResponse $response, Throwable $e): SymfonyResponse
    {
        $code = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

        $messages = $this->resolveMessages($e);

        $message = array_key_exists($code, $messages)
            ? $messages[$code]
            : $messages[500];

        if (! $request->isMethod('GET') && in_array($code, [419, 429])) {
            return back()
                ->setStatusCode($code)
                ->with($this->errorMessageKey, $message);
        }

        if (! config('app.debug') && array_key_exists($code, $messages)) {
            $response = $this->inertia->render($this->errorView);
            $response = $this->transformInertiaErrorResponse($response, compact('message'));

            return $response
                ->with('code', $code)
                ->with('message', $message)
                ->toResponse($request)
                ->setStatusCode($code);
        }

        return $response;
    }

    /**
     * Set the error view name.
     *
     * @return $this
     */
    public function errorView(string $view)
    {
        $this->errorView = $view;

        return $this;
    }

    /**
     * Set the flash error message key name.
     *
     * @return $this
     */
    public function errorMessageKey(string $key)
    {
        $this->errorMessageKey = $key;

        return $this;
    }

    /**
     * Return a list of error messages.
     *
     * @return array<int, string>
     */
    protected function messages(Throwable $e): array
    {
        return [
            401 => 'Unauthorized',
            403 => $e->getMessage() ?: 'Forbidden',
            404 => 'Not Found',
            419 => 'The page expired, please try again.',
            429 => $e->getMessage() ?: 'Too Many Requests',
            500 => 'Server Error',
            503 => $e->getMessage() ?: 'Service Unavailable',
        ];
    }

    /**
     * Regsiter the messages transform callback.
     *
     * @return $this
     */
    public function withMessages(callable $callback)
    {
        $this->messagesCallback = $callback;

        return $this;
    }

    /**
     * Resolve the error message.
     *
     * @return array<int, string>
     */
    protected function resolveMessages(Throwable $e): array
    {
        $messages = $this->messages($e);

        if ($this->messagesCallback) {
            $messages = call_user_func($this->messagesCallback, $messages, $e);
        }

        return $messages;
    }

    /**
     * Transform the inertia error response.
     */
    protected function transformInertiaErrorResponse(InertiaResponse $response, array $params = []): InertiaResponse
    {
        if (InertiaResponse::hasMacro('title')) {
            $response->title($params['message']);
        }

        return $response;
    }
}
