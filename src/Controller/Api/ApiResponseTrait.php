<?php

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

trait ApiResponseTrait
{
    protected function apiSuccess(string $message, mixed $data = null, int $statusCode = 200): JsonResponse
    {
        $payload = [
            'success' => true,
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ];

        return new JsonResponse($payload, $statusCode);
    }

    protected function apiError(string $message, int $statusCode = 400, mixed $data = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'status' => 'error',
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return new JsonResponse($payload, $statusCode);
    }
}
