<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'success', int $status = 200): JsonResponse
    {
        return response()->json([
            'data'    => $data,
            'message' => $message,
            'errors'  => null,
        ], $status);
    }

    protected function created(mixed $data = null, string $message = 'Created successfully'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function noContent(string $message = 'success'): JsonResponse
    {
        return $this->success(null, $message, 200);
    }

    protected function error(string $message, mixed $errors = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'data'    => null,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    protected function validationError(mixed $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->error($message, $errors, 422);
    }

    protected function unauthorized(string $message = 'Unauthenticated'): JsonResponse
    {
        return $this->error($message, null, 401);
    }

    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, null, 403);
    }

    protected function notFound(string $message = 'Not found'): JsonResponse
    {
        return $this->error($message, null, 404);
    }

    protected function serverError(string $message = 'Internal server error'): JsonResponse
    {
        return $this->error($message, null, 500);
    }
}
