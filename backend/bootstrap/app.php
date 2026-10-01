<?php

use App\Exceptions\Auth\InvalidVerificationLinkException;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureIdentityConfirmed;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->throttleApi();
        $middleware->prependToGroup('api', ForceJsonResponse::class);
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'verified' => EnsureEmailIsVerified::class,
            'identity.confirmed' => EnsureIdentityConfirmed::class,
        ]);
        $middleware->appendToPriorityList(Authorize::class, EnsureIdentityConfirmed::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'payment.number',
            'payment.cvc',
        ]);

        $exceptions->render(fn (ThrottleRequestsException $e) => response()->json([
            'message' => 'Troppi tentativi. Riprova tra '.$e->getHeaders()['Retry-After'].' secondi.',
        ], Response::HTTP_TOO_MANY_REQUESTS, $e->getHeaders()));

        $exceptions->render(fn (InvalidSignatureException $e) => response()->json([
            'message' => InvalidVerificationLinkException::MESSAGE,
        ], Response::HTTP_FORBIDDEN));

        $exceptions->render(fn (AuthenticationException $e) => response()->json([
            'message' => 'Devi accedere per continuare.',
        ], Response::HTTP_UNAUTHORIZED));

        $exceptions->render(fn (AccessDeniedHttpException $e) => response()->json([
            'message' => $e->getMessage() === 'This action is unauthorized.' || $e->getMessage() === ''
                ? 'Non hai i permessi per questa operazione.'
                : $e->getMessage(),
        ], Response::HTTP_FORBIDDEN));

        $exceptions->render(fn (NotFoundHttpException $e) => response()->json([
            'message' => 'La risorsa richiesta non esiste.',
        ], Response::HTTP_NOT_FOUND));

        $exceptions->render(fn (MethodNotAllowedHttpException $e) => response()->json([
            'message' => 'Metodo non consentito per questo indirizzo.',
        ], Response::HTTP_METHOD_NOT_ALLOWED, $e->getHeaders()));
    })->create();
