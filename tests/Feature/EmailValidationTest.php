<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class EmailValidationTest extends TestCase
{
    public function test_email_rfc_rejects_invalid_tld(): void
    {
        $validator = Validator::make(
            ['email' => 'test@gmail.co'],
            ['email' => 'email:rfc']
        );

        $this->assertTrue($validator->fails(), 'email:rfc should reject gmail.co');
    }

    public function test_email_rfc_accepts_valid_tld(): void
    {
        $validator = Validator::make(
            ['email' => 'test@gmail.com'],
            ['email' => 'email:rfc']
        );

        $this->assertFalse($validator->fails(), 'email:rfc should accept gmail.com');
    }

    public function test_request_otp_rejects_invalid_email(): void
    {
        $response = $this->postJson('/api/auth/register/request-otp', [
            'email' => 'hibboalk@gmail.co',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.email.0', 'The email must be a valid email address.');
    }

    public function test_request_otp_accepts_valid_email(): void
    {
        $response = $this->postJson('/api/auth/register/request-otp', [
            'email' => 'hibboalk@gmail.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'hibboalk@gmail.com');
    }
}
