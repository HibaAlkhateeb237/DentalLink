<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public function success(mixed $data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $status);
    }

    public function error(string $message = 'Error', int $status = 400, mixed $errors = null, mixed $data = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        } else {
            $payload['errors'] = null;
        }

        return response()->json($payload, $status);
    }
}
