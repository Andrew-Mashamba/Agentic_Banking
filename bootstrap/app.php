<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        // Set HOME route for authenticated users
        then: function () {
            // This sets the default home route after login
            config(['app.home' => '/dashboard']);
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'api.role' => \App\Http\Middleware\ApiCheckRole::class,
            'log.api' => \App\Http\Middleware\LogApiRequests::class,
            'api.internal' => \App\Http\Middleware\InternalApiMiddleware::class,
        ]);

        // Log all API requests
        $middleware->api(append: [
            \App\Http\Middleware\LogApiRequests::class,
        ]);

        // Fix Livewire update URI for sub-directory deployment
        $middleware->web(append: [
            \App\Http\Middleware\FixLivewireSubdirectory::class,
        ]);

        // Redirect authenticated users trying to access guest routes to /dashboard
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');

        // Exempt WhatsApp and Stripe webhooks from CSRF protection
        $middleware->validateCsrfTokens(except: [
            'webhooks/whatsapp',
            'webhooks/stripe',
            'api/webhooks/stripe',
            'api/webhooks/mpesa',
        ]);

        // API rate limiting: 60 requests per minute
        $middleware->throttleApi('60,1');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Custom exception handling for API and web requests
        $exceptions->render(function (Throwable $e, $request) {
            // Handle API requests with JSON responses
            if ($request->is('api/*') || $request->expectsJson()) {
                // Handle authentication exceptions
                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json([
                        'message' => 'Unauthenticated.',
                    ], 401);
                }

                // Handle authorization exceptions
                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    return response()->json([
                        'message' => $e->getMessage() ?: 'This action is unauthorized.',
                    ], 403);
                }

                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage() ?: 'An error occurred',
                    'errors' => $e instanceof \Illuminate\Validation\ValidationException
                        ? $e->errors()
                        : null,
                ], $e instanceof \Illuminate\Validation\ValidationException
                    ? 422
                    : ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException
                        ? $e->getStatusCode()
                        : 500)
                );
            }

            // Let Laravel handle web requests (will use custom error views)
            return null;
        });

        // Report critical exceptions to specific log channel
        $exceptions->report(function (\App\Exceptions\OrderWorkflowException $e) {
            \Log::channel('critical')->critical($e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        });

        $exceptions->report(function (\App\Exceptions\PaymentException $e) {
            \Log::channel('critical')->critical($e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        });

        $exceptions->report(function (\App\Exceptions\InventoryException $e) {
            \Log::channel('critical')->critical($e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        });
    })->create();
