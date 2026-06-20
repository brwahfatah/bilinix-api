<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [];

    public function render($request, Throwable $e): mixed
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->jsonError($request, $e);
        }

        return parent::render($request, $e);
    }

    private function jsonError(Request $request, Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return response()->json([
                'data'    => null,
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'data'    => null,
                'message' => 'Unauthenticated',
                'errors'  => null,
            ], 401);
        }

        if ($e instanceof ModelNotFoundException) {
            $model = class_basename($e->getModel());
            return response()->json([
                'data'    => null,
                'message' => "{$model} not found",
                'errors'  => null,
            ], 404);
        }

        if ($e instanceof HttpException) {
            return response()->json([
                'data'    => null,
                'message' => $e->getMessage() ?: 'HTTP error',
                'errors'  => null,
            ], $e->getStatusCode());
        }

        $message = config('app.debug')
            ? $e->getMessage()
            : 'An unexpected error occurred. Please try again.';

        return response()->json([
            'data'    => null,
            'message' => $message,
            'errors'  => null,
        ], 500);
    }
}
