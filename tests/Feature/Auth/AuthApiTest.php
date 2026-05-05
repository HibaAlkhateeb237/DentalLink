<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\RegisterOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
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
            'status',
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
            'status',
            'message',
            'data' => ['verification_token', 'expires_in_seconds'],
        ]);

        $response = $this->postJson('/api/auth/register/complete', [
            'verification_token' => $verifyOtp->json('data.verification_token'),
            'name' => 'Doctor One',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'birthdate' => '1990-05-10',
            'location' => 'Cairo',
            'location_lat' => 30.0444200,
            'location_lng' => 31.2357100,
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure(['success', 'status', 'message', 'data' => ['token', 'user' => ['id', 'name', 'email'], 'roles']])
            ->assertJsonPath('data.user.location', 'Cairo');

        $this->assertStringStartsWith('1990-05-10', (string) $response->json('data.user.birthdate'));

        $this->assertDatabaseHas('users', [
            'email' => 'doctor1@example.com',
            'location' => 'Cairo',
        ]);

        $registeredUser = User::query()->where('email', 'doctor1@example.com')->firstOrFail();
        $this->assertNotNull($registeredUser->email_verified_at);
    }

    public function test_user_can_complete_registration_with_verification_token_in_payload(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register/request-otp', [
            'email' => 'doctor-payload-token@example.com',
        ])->assertOk();

        $otpCode = null;

        Notification::assertSentOnDemand(RegisterOtpNotification::class, function (RegisterOtpNotification $notification) use (&$otpCode): bool {
            $otpCode = $notification->code;

            return true;
        });

        $verifyOtp = $this->postJson('/api/auth/register/verify-otp', [
            'email' => 'doctor-payload-token@example.com',
            'code' => $otpCode,
        ]);

        $response = $this->postJson('/api/auth/register/complete', [
            'verification_token' => $verifyOtp->json('data.verification_token'),
            'name' => 'Doctor Payload Token',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 201)
            ->assertJsonPath('data.user.email', 'doctor-payload-token@example.com');
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

    public function test_request_otp_with_invalid_email_returns_bad_request(): void
    {
        $response = $this->postJson('/api/auth/register/request-otp', [
            'email' => 'asdsad',
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 400)
            ->assertJsonPath('message', __('messages.validation_failed'))
            ->assertJsonStructure([
                'errors' => ['email'],
            ]);
    }

    public function test_request_otp_with_existing_email_returns_conflict(): void
    {
        User::factory()->create([
            'email' => 'existing-register@example.com',
        ]);

        $response = $this->postJson('/api/auth/register/request-otp', [
            'email' => 'existing-register@example.com',
        ]);

        $response
            ->assertConflict()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 409)
            ->assertJsonPath('message', __('auth.email_already_registered'));
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

        $response = $this
            ->withHeader('Accept', 'application/json')
            ->post('/api/auth/register/complete', [
                'verification_token' => $verifyOtp->json('data.verification_token'),
                'name' => 'Doctor Image',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'profile_image' => UploadedFile::fake()->image('avatar.jpg'),
            ]);

        $response->assertCreated();

        $user = User::query()->where('email', 'doctor-image@example.com')->firstOrFail();

        $this->assertNotNull($user->profile_image);
        $this->assertTrue(Storage::disk('public')->exists((string) $user->profile_image));
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

        $login->assertOk()->assertJsonStructure(['success', 'status', 'message', 'data' => ['token', 'user', 'roles']]);

        $token = $login->json('data.token');

        $logout = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout');

        $logout->assertOk();
    }

    public function test_login_with_invalid_email_format_returns_bad_request(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'safa',
            'password' => '0000',
        ]);

        $response
            ->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 400)
            ->assertJsonPath('message', __('messages.validation_failed'))
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_returns_email_error_when_email_is_not_registered(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 422)
            ->assertJsonPath('message', __('auth.login_email_not_found'))
            ->assertJsonPath('errors.email.0', __('auth.login_email_not_found'));
    }

    public function test_login_returns_password_error_when_password_is_wrong(): void
    {
        User::factory()->create([
            'email' => 'password-check@example.com',
            'password' => 'correct-password-123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'password-check@example.com',
            'password' => 'wrong-password-123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 422)
            ->assertJsonPath('message', __('auth.login_password_incorrect'))
            ->assertJsonPath('errors.password.0', __('auth.login_password_incorrect'));
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

    public function test_authenticated_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Doctor Before',
            'email' => 'doctor-before@example.com',
            'phone' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/me', [
            'name' => 'Doctor After',
            'phone' => '0501234567',
            'location' => 'Cairo',
            'location_lat' => 30.0444200,
            'location_lng' => 31.2357100,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('data.user.name', 'Doctor After')
            ->assertJsonPath('data.user.phone', '0501234567')
            ->assertJsonPath('data.user.location', 'Cairo')
            ->assertJsonPath('data.user.location_lat', '30.0444200')
            ->assertJsonPath('data.user.location_lng', '31.2357100');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Doctor After',
            'phone' => '0501234567',
            'location' => 'Cairo',
            'location_lat' => '30.0444200',
            'location_lng' => '31.2357100',
        ]);
    }

    public function test_authenticated_user_can_replace_profile_image(): void
    {
        Storage::fake('public');

        $oldImage = UploadedFile::fake()->image('old-avatar.jpg')->store('users/profile-images', 'public');

        $user = User::factory()->create([
            'profile_image' => $oldImage,
        ]);

        Sanctum::actingAs($user);

        $response = $this->post('/api/auth/me', [
            'profile_image' => UploadedFile::fake()->image('new-avatar.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk();

        $user->refresh();

        $this->assertNotNull($user->profile_image);
        $this->assertNotSame($oldImage, $user->profile_image);
        $this->assertFalse(Storage::disk('public')->exists($oldImage));
        $this->assertTrue(Storage::disk('public')->exists((string) $user->profile_image));
    }

    public function test_authenticated_user_can_remove_profile_image(): void
    {
        Storage::fake('public');

        $imagePath = UploadedFile::fake()->image('avatar.jpg')->store('users/profile-images', 'public');

        $user = User::factory()->create([
            'profile_image' => $imagePath,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/me/profile-image');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('message', __('auth.profile_image_removed_successfully'))
            ->assertJsonPath('data.user.profile_image', null);

        $user->refresh();

        $this->assertNull($user->profile_image);
        $this->assertFalse(Storage::disk('public')->exists($imagePath));
    }

    public function test_remove_profile_image_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/auth/me/profile-image');

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_update_profile_with_multipart_post_body(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'name' => 'Before Post Multipart',
            'profile_image' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->post('/api/auth/me', [
            'name' => 'After Post Multipart',
            'profile_image' => UploadedFile::fake()->image('avatar.png'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'After Post Multipart');

        $user->refresh();

        $this->assertSame('After Post Multipart', $user->name);
        $this->assertNotNull($user->profile_image);
        $this->assertTrue(Storage::disk('public')->exists((string) $user->profile_image));
        $this->assertTrue(Str::endsWith((string) $response->json('data.user.profile_image'), (string) $user->profile_image));
    }

    public function test_update_profile_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/me', [
            'name' => 'No Auth',
        ]);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password-123',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/change-password', [
            'current_password' => 'old-password-123',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('message', __('auth.password_updated_successfully'));

        $user->refresh();

        $this->assertTrue(Hash::check('new-password-123', (string) $user->password));
    }

    public function test_change_password_requires_valid_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password-123',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/change-password', [
            'current_password' => 'wrong-password-123',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonValidationErrors(['current_password']);

        $user->refresh();

        $this->assertTrue(Hash::check('old-password-123', (string) $user->password));
    }

    public function test_change_password_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/change-password', [
            'current_password' => 'old-password-123',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

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

    public function test_user_account_is_locked_after_five_failed_login_attempts_even_with_different_ip(): void
    {
        User::factory()->create([
            'email' => 'account-lock@example.com',
            'password' => 'password123',
        ]);

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/auth/login', [
                'email' => 'account-lock@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $lockedAttemptFromDifferentIp = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '10.10.10.10',
            ])
            ->postJson('/api/auth/login', [
                'email' => 'account-lock@example.com',
                'password' => 'password123',
            ]);

        $lockedAttemptFromDifferentIp
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 429)
            ->assertJsonPath('message', __('auth.too_many_attempts'));

        $this->assertDatabaseHas('users', [
            'email' => 'account-lock@example.com',
            'failed_login_attempts' => 5,
        ]);
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
