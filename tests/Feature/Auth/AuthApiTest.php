<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\RegisterOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_through_otp_flow(): void
    {
        Notification::fake();

        $requestOtp = $this->postJson('/api/auth/register/request-otp', [
            'email' => 'doctor1@example.com',
        ]);

        $requestOtp->assertOk()->assertJsonStructure([
            'success',
            'message',
            'data' => ['email', 'expires_in_seconds'],
        ]);

        $otpCode = null;

        Notification::assertSentOnDemand(RegisterOtpNotification::class, function (RegisterOtpNotification $notification, array $channels, object $notifiable) use (&$otpCode): bool {
            $otpCode = $notification->code;

            return in_array('mail', $channels, true);
        });

        $verifyOtp = $this->postJson('/api/auth/register/verify-otp', [
            'email' => 'doctor1@example.com',
            'code' => $otpCode,
        ]);

        $verifyOtp->assertOk()->assertJsonStructure([
            'success',
            'message',
            'data' => ['verification_token', 'expires_in_seconds'],
        ]);

        $response = $this->postJson('/api/auth/register/complete', [
            'verification_token' => $verifyOtp->json('data.verification_token'),
            'name' => 'Doctor One',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure(['success', 'message', 'data' => ['token', 'user' => ['id', 'name', 'email']]]);
    }

    public function test_cannot_verify_registration_with_invalid_code(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register/request-otp', [
            'email' => 'doctor2@example.com',
        ])->assertOk();

        $response = $this->postJson('/api/auth/register/verify-otp', [
            'email' => 'doctor2@example.com',
            'code' => '000000',
        ]);

        $response->assertStatus(422);
    }

    public function test_request_otp_is_blocked_by_resend_cooldown(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register/request-otp', [
            'email' => 'doctor3@example.com',
        ])->assertOk();

        $secondRequest = $this->postJson('/api/auth/register/request-otp', [
            'email' => 'doctor3@example.com',
        ]);

        $secondRequest->assertStatus(429);
    }

    public function test_cannot_complete_registration_with_expired_verification_token(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register/request-otp', [
            'email' => 'doctor4@example.com',
        ])->assertOk();

        $otpCode = null;

        Notification::assertSentOnDemand(RegisterOtpNotification::class, function (RegisterOtpNotification $notification) use (&$otpCode): bool {
            $otpCode = $notification->code;

            return true;
        });

        $verifyOtp = $this->postJson('/api/auth/register/verify-otp', [
            'email' => 'doctor4@example.com',
            'code' => $otpCode,
        ]);

        $verificationToken = $verifyOtp->json('data.verification_token');

        Carbon::setTestNow(now()->addMinutes(31));

        $response = $this->postJson('/api/auth/register/complete', [
            'verification_token' => $verificationToken,
            'name' => 'Doctor Four',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        Carbon::setTestNow();

        $response->assertStatus(422);
    }

    public function test_user_can_complete_registration_with_profile_image(): void
    {
        Notification::fake();
        Storage::fake('public');

        $this->postJson('/api/auth/register/request-otp', [
            'email' => 'doctor-image@example.com',
        ])->assertOk();

        $otpCode = null;

        Notification::assertSentOnDemand(RegisterOtpNotification::class, function (RegisterOtpNotification $notification) use (&$otpCode): bool {
            $otpCode = $notification->code;

            return true;
        });

        $verifyOtp = $this->postJson('/api/auth/register/verify-otp', [
            'email' => 'doctor-image@example.com',
            'code' => $otpCode,
        ]);

        $response = $this->withHeader('Accept', 'application/json')->post('/api/auth/register/complete', [
            'verification_token' => $verifyOtp->json('data.verification_token'),
            'name' => 'Doctor Image',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'profile_image' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $response->assertCreated();

        $user = User::query()->where('email', 'doctor-image@example.com')->firstOrFail();

        $this->assertNotNull($user->profile_image);
        Storage::disk('public')->assertExists((string) $user->profile_image);
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $login->assertOk()->assertJsonStructure(['success', 'message', 'data' => ['token']]);

        $token = $login->json('data.token');

        $logout = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout');

        $logout->assertOk();
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertUnauthorized();
    }

    public function test_me_requires_authentication_without_json_accept_header(): void
    {
        $response = $this->get('/api/auth/me');

        $response->assertUnauthorized();
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        User::factory()->create([
            'email' => 'locked@example.com',
            'password' => 'password123',
        ]);

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/auth/login', [
                'email' => 'locked@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $sixthAttempt = $this->postJson('/api/auth/login', [
            'email' => 'locked@example.com',
            'password' => 'wrong-password',
        ]);

        $sixthAttempt->assertStatus(429);
    }

    public function test_verify_otp_is_rate_limited_after_five_invalid_attempts(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register/request-otp', [
            'email' => 'doctor5@example.com',
        ])->assertOk();

        foreach (range(1, 4) as $_) {
            $this->postJson('/api/auth/register/verify-otp', [
                'email' => 'doctor5@example.com',
                'code' => '111111',
            ])->assertStatus(422);
        }

        $fifthAttempt = $this->postJson('/api/auth/register/verify-otp', [
            'email' => 'doctor5@example.com',
            'code' => '111111',
        ]);

        $fifthAttempt->assertStatus(429);

        $sixthAttempt = $this->postJson('/api/auth/register/verify-otp', [
            'email' => 'doctor5@example.com',
            'code' => '111111',
        ]);

        $sixthAttempt->assertStatus(429);
    }
}
