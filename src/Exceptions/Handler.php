<?php

namespace Inertia\Exceptions;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Response as InertiaResponse;
use Inertia\ResponseFactory;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler
{
    /**
     * The error view name.
     *
     * @var string
     */
    protected $errorView = 'Error';

    /**
     * The flash error message key name.
     *
     * @var string
     */
    protected $errorMessageKey = 'error';

    /**
     * The error messages transform callback.
     *
     * @var callable|null
     */
    protected $messagesCallback;

    /**
     * The error message transform callback.
     *
     * @var callable|null
     */
    protected $messageCallback;

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
        if ($e instanceof ValidationException && $e->status === 422) {
            return $response;
        }

        if ($e instanceof HttpExceptionInterface) {
            $code = $e->getStatusCode();
        } else {
            $code = $response->getStatusCode();
        }

        $messages = $this->resolveMessages($e);

        $messageContext = $this->resolveMessage($code, $messages, $e);
        $title = $messageContext['title'];
        $message = $messageContext['message'];

        if (! $request->isMethod('GET') && in_array($code, [419, 429])) {
            return back()->with($this->errorMessageKey, $message);
        }

        if (! config('app.debug') && array_key_exists($code, $messages)) {
            $response = $this->inertia->render($this->errorView);

            $response = $this->transformInertiaErrorResponse($response, [
                'title' => $title,
                'message' => $message,
            ]);

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
    protected function messages(): array
    {
        return [
            403 => 'Forbidden',
            404 => 'Not Found',
            419 => 'The page expired, please try again.',
            429 => 'Too Many Requests',
            500 => 'Server Error',
            503 => 'Service Unavailable',
        ];
    }

    /**
     * Regsiter the error messages transform callback.
     *
     * @return $this
     */
    public function withMessages(callable $callback)
    {
        $this->messagesCallback = $callback;

        return $this;
    }

    /**
     * Regsiter the error message transform callback.
     *
     * @return $this
     */
    public function withMessage(callable $callback)
    {
        $this->messageCallback = $callback;

        return $this;
    }

    /**
     * Resolve the error messages.
     *
     * @return array<int, string>
     */
    protected function resolveMessages(Throwable $e): array
    {
        $messages = $this->messages();

        if ($this->messagesCallback) {
            $messages = call_user_func($this->messagesCallback, $messages, $e);
        }

        return $messages;
    }

    /**
     * Resolve the error message.
     *
     * @param  array<int, string>  $messages
     * @return array<string, string>
     */
    protected function resolveMessage(int $code, array $messages, Throwable $e): array
    {
        if ($code === 403 && $message = $e->getMessage()) {
            $message = __($message ?? $messages[403]);
            $title = $messages[403];
        } elseif (array_key_exists($code, $messages)) {
            $message = __($messages[$code]);
            $title = $message;
        } else {
            $message = __($messages[500]);
            $title = $message;
        }

        if ($this->messageCallback) {
            $message = call_user_func($this->messageCallback, $message, $code, $e);
            $title = $message;
        }

        return [
            'title' => $title,
            'message' => $message,
        ];
    }

    /**
     * Transform the inertia error response.
     */
    protected function transformInertiaErrorResponse(InertiaResponse $response, array $params = []): InertiaResponse
    {
        if (InertiaResponse::hasMacro('title')) {
            $response->title($params['title']);
        }

        return $response;
    }
}
