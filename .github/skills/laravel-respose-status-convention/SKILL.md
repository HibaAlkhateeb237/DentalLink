---
name: laravel-respose-status-convention
description: "Use this skill when generating or refactoring Laravel API responses so all endpoints follow one status code and JSON envelope convention, including explicit success statuses 200/201 and standard error statuses."
license: MIT
metadata:
  author: dentalink
---

# Laravel Respose Status Convention

Use this skill when creating or refactoring backend API endpoints in Laravel.

## Goal

Enforce one backend API response contract across all endpoints:

- Always return a consistent JSON envelope
- Use explicit success statuses (`200`, `201`, optional `202`, `204`)
- Use predictable error statuses (`400`, `401`, `403`, `404`, `409`, `422`, `429`, `500`)
- Keep localized messages via `__()` / `trans()`

## Standard Envelope

Every API response should follow this shape:

```json
{
  "success": true,
  "status": 200,
  "message": "...",
  "data": {},
  "errors": null
}
```

For error responses:

- `success` must be `false`
- `data` must be `null`
- `errors` should contain validation/domain details when available, otherwise `null`

## Required Status Mapping

### Success

- `200 OK`: Read/retrieve/update succeeded
- `201 Created`: Resource created successfully
- `202 Accepted`: Async processing started (optional)
- `204 No Content`: Successful operation with no payload (optional)

### Errors

- `400 Bad Request`: Invalid request shape/business preconditions
- `401 Unauthorized`: Authentication required or invalid token
- `403 Forbidden`: Authenticated but not allowed (policy/gate failure)
- `404 Not Found`: Missing resource
- `409 Conflict`: Duplicate/conflict state
- `422 Unprocessable Entity`: Validation failure
- `429 Too Many Requests`: Throttling/lockout
- `500 Internal Server Error`: Unexpected server error

## Laravel Implementation Rules

1. Prefer a centralized responder class (for this project: `App\Http\Responses\ApiResponse`) for success/error payloads.
2. Keep controllers thin and return responder output instead of hand-building JSON repeatedly.
3. Use Form Requests for validation; return `422` with validation errors.
4. Let global exception handling in `bootstrap/app.php` normalize uncaught API errors.
5. Use translation keys for all user-facing messages; do not hardcode text in controllers.
6. Keep response status in both HTTP status code and the `status` JSON field.

## Reference Template (Laravel)

```php
<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ExampleController extends Controller
{
    public function __construct(private ApiResponse $apiResponse) {}

    public function store(): JsonResponse
    {
        $payload = [
            'id' => 1,
        ];

        return $this->apiResponse->success(
            data: $payload,
            message: __('messages.created_successfully'),
            status: 201,
        );
    }
}
```

## Notes For The Agent

- Reuse existing resources/response classes before introducing new abstractions.
- Prefer minimal diffs: fix status/envelope inconsistencies without unrelated refactors.
- If an endpoint currently returns raw models, move it to API Resource + standardized responder.
