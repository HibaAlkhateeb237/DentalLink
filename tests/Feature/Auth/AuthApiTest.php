<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use App\Notifications\Auth\RegisterOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$verifyOtp->json('data.verification_token'))
            ->postJson('/api/auth/register/complete', [
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
            ->assertJsonStructure(['success', 'status', 'message', 'data' => ['token', 'user' => ['id', 'name', 'email']]])
            ->assertJsonPath('data.user.location', 'Cairo');

        $this->assertStringStartsWith('1990-05-10', (string) $response->json('data.user.birthdate'));

        $this->assertDatabaseHas('users', [
            'email' => 'doctor1@example.com',
            'location' => 'Cairo',
        ]);

        $registeredUser = User::query()->where('email', 'doctor1@example.com')->firstOrFail();
        $this->assertNotNull($registeredUser->email_verified_at);
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

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$verificationToken)
            ->postJson('/api/auth/register/complete', [
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
            ->withHeader('Authorization', 'Bearer '.$verifyOtp->json('data.verification_token'))
            ->post('/api/auth/register/complete', [
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

        $login->assertOk()->assertJsonStructure(['success', 'status', 'message', 'data' => ['token']]);

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

    public function test_authenticated_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Doctor Before',
            'email' => 'doctor-before@example.com',
            'phone' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/auth/me', [
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

        $response = $this->patch('/api/auth/me', [
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

    public function test_update_profile_requires_authentication(): void
    {
        $response = $this->patchJson('/api/auth/me', [
            'name' => 'No Auth',
        ]);

        $response->assertUnauthorized();
    }

    public function test_doctor_cannot_update_lab_name(): void
    {
        $user = User::factory()->create();

        $doctorRole = Role::query()->create([
            'name' => 'doctor',
            'guard_name' => 'sanctum',
        ]);

        $user->roles()->syncWithoutDetaching([$doctorRole->id]);

        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/auth/me', [
            'lab_name' => 'My Lab',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lab_name']);

        $this->assertNull($user->fresh()?->lab_name);
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
