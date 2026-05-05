<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiExceptionResponseTest extends TestCase
{
    public function test_api_exception_response_includes_the_exception_reason(): void
    {
        Route::get('/api/test-exception-response', function (): never {
            throw new RuntimeException('Database connection timed out.');
        });

        $this->getJson('/api/test-exception-response')
            ->assertInternalServerError()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 500)
            ->assertJsonPath('message', __('messages.error'))
            ->assertJsonPath('errors.reason', 'Database connection timed out.');
    }
}
