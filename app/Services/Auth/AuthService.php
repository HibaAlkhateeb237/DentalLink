<?php

namespace App\Services\Auth;

use App\Models\DepartmentUserRole;
use App\Models\RegistrationOtp;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Auth\RegisterOtpNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    private const LOGIN_MAX_FAILED_ATTEMPTS = 5;

    private const LOGIN_LOCK_SECONDS = 900;

    private const OTP_EXPIRES_IN_SECONDS = 600;

    private const OTP_RESEND_COOLDOWN_SECONDS = 60;

    private const OTP_MAX_VERIFY_ATTEMPTS = 5;

    private const VERIFICATION_TOKEN_EXPIRES_IN_SECONDS = 1800;

    /**
     * @return array{status:'sent',email:string,expires_in_seconds:int}|array{status:'cooldown',retry_after_seconds:int}|array{status:'email_exists'}
     */
    public function requestRegistrationOtp(string $email, string $locale): array
    {
        if (User::query()->where('email', $email)->exists()) {
            return [
                'status' => 'email_exists',
            ];
        }

        $registrationOtp = RegistrationOtp::query()->firstOrNew([
            'email' => $email,
        ]);

        if ($registrationOtp->exists && $registrationOtp->last_sent_at !== null) {
            $cooldownEndsAt = $registrationOtp->last_sent_at->copy()->addSeconds(self::OTP_RESEND_COOLDOWN_SECONDS);

            if ($cooldownEndsAt->isFuture()) {
                return [
                    'status' => 'cooldown',
                    'retry_after_seconds' => max(now()->diffInSeconds($cooldownEndsAt), 1),
                ];
            }
        }

        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $registrationOtp->fill([
            'otp_hash' => Hash::make($otpCode),
            'expires_at' => now()->addSeconds(self::OTP_EXPIRES_IN_SECONDS),
            'verify_attempts' => 0,
            'last_sent_at' => now(),
            'verified_at' => null,
            'verification_token' => null,
            'verification_token_expires_at' => null,
            'consumed_at' => null,
        ])->save();

        Notification::route('mail', $email)
            ->notify((new RegisterOtpNotification($otpCode))->locale($locale));

        return [
            'status' => 'sent',
            'email' => $email,
            'expires_in_seconds' => self::OTP_EXPIRES_IN_SECONDS,
        ];
    }

    /**
     * @return array{status:'verified',verification_token:string,expires_in_seconds:int}|array{status:'invalid'|'expired'}|array{status:'too_many_attempts',retry_after_seconds:int}
     */
    public function verifyRegistrationOtp(string $email, string $code): array
    {
        $registrationOtp = RegistrationOtp::query()
            ->where('email', $email)
            ->first();

        if ($registrationOtp === null || $registrationOtp->consumed_at !== null) {
            return [
                'status' => 'invalid',
            ];
        }

        if ($registrationOtp->expires_at->isPast()) {
            return [
                'status' => 'expired',
            ];
        }

        if ($registrationOtp->verify_attempts >= self::OTP_MAX_VERIFY_ATTEMPTS) {
            return [
                'status' => 'too_many_attempts',
                'retry_after_seconds' => max(now()->diffInSeconds($registrationOtp->expires_at), 1),
            ];
        }

        if (! Hash::check($code, $registrationOtp->otp_hash)) {
            $registrationOtp->increment('verify_attempts');
            $registrationOtp->refresh();

            if ($registrationOtp->verify_attempts >= self::OTP_MAX_VERIFY_ATTEMPTS) {
                return [
                    'status' => 'too_many_attempts',
                    'retry_after_seconds' => max(now()->diffInSeconds($registrationOtp->expires_at), 1),
                ];
            }

            return [
                'status' => 'invalid',
            ];
        }

        $verificationToken = Str::uuid()->toString();

        $registrationOtp->update([
            'verified_at' => now(),
            'verification_token' => $verificationToken,
            'verification_token_expires_at' => now()->addSeconds(self::VERIFICATION_TOKEN_EXPIRES_IN_SECONDS),
        ]);

        return [
            'status' => 'verified',
            'verification_token' => $verificationToken,
            'expires_in_seconds' => self::VERIFICATION_TOKEN_EXPIRES_IN_SECONDS,
        ];
    }

    /**
     * @param  array{verification_token:string,name:string,password:string,phone?:string|null,birthdate?:string|null,location?:string|null,location_lat?:float|int|string|null,location_lng?:float|int|string|null}  $validated
     * @return array{status:'completed',user:User,token:string}|array{status:'invalid_verification_token'|'email_exists'}
     */
    public function register(array $validated, ?UploadedFile $profileImage = null): array
    {
        $registrationOtp = RegistrationOtp::query()
            ->where('verification_token', $validated['verification_token'])
            ->whereNotNull('verified_at')
            ->whereNull('consumed_at')
            ->first();

        if ($registrationOtp === null || $registrationOtp->verification_token_expires_at === null || $registrationOtp->verification_token_expires_at->isPast()) {
            return [
                'status' => 'invalid_verification_token',
            ];
        }

        if (User::query()->where('email', $registrationOtp->email)->exists()) {
            return [
                'status' => 'email_exists',
            ];
        }

        $profileImagePath = $profileImage?->store('users/profile-images', 'public');

        try {
            $user = DB::transaction(function () use ($validated, $profileImagePath): User {
                $registrationOtp = RegistrationOtp::query()
                    ->where('verification_token', $validated['verification_token'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $user = User::query()->create([
                    'name' => $validated['name'],
                    'email' => $registrationOtp->email,
                    'password' => $validated['password'],
                    'phone' => $validated['phone'] ?? null,
                    'birthdate' => $validated['birthdate'] ?? null,
                    'location' => $validated['location'] ?? null,
                    'location_lat' => $validated['location_lat'] ?? null,
                    'location_lng' => $validated['location_lng'] ?? null,
                    'profile_image' => $profileImagePath,
                ]);

                $user->forceFill([
                    'email_verified_at' => now(),
                ])->save();

                $defaultRoleId = Role::query()
                    ->where('name', 'doctor')
                    ->where('guard_name', 'sanctum')
                    ->value('id');

                if ($defaultRoleId !== null) {
                    $user->roles()->syncWithoutDetaching([$defaultRoleId]);
                }

                $registrationOtp->update([
                    'consumed_at' => now(),
                    'verification_token' => null,
                    'verification_token_expires_at' => null,
                ]);

                return $user;
            });
        } catch (\Throwable $exception) {
            if ($profileImagePath !== null) {
                Storage::disk('public')->delete($profileImagePath);
            }

            throw $exception;
        }

        $token = $user->createToken('api-token', ['*'])->plainTextToken;

        return [
            'status' => 'completed',
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * @param  array{email:string,password:string}  $credentials
     * @return array{status:'authenticated',user:User,token:string}|array{status:'invalid_email'|'invalid_password'}|array{status:'locked',retry_after_seconds:int}
     */
    public function login(array $credentials): array
    {
        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if ($user === null) {
            return [
                'status' => 'invalid_email',
            ];
        }

        if ($user->locked_until !== null && $user->locked_until->isFuture()) {
            return [
                'status' => 'locked',
                'retry_after_seconds' => max(now()->diffInSeconds($user->locked_until), 1),
            ];
        }

        if (! Hash::check($credentials['password'], (string) $user->password)) {
            $failedAttempts = $user->failed_login_attempts + 1;

            $user->forceFill([
                'failed_login_attempts' => $failedAttempts,
                'locked_until' => $failedAttempts >= self::LOGIN_MAX_FAILED_ATTEMPTS
                    ? now()->addSeconds(self::LOGIN_LOCK_SECONDS)
                    : null,
            ])->save();

            return [
                'status' => 'invalid_password',
            ];
        }

        Auth::login($user);

        if ($user->failed_login_attempts > 0 || $user->locked_until !== null) {
            $user->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ])->save();
        }

        $token = $user->createToken('api-token', ['*'])->plainTextToken;

        return [
            'status' => 'authenticated',
            'user' => $user,
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $currentToken = $user->currentAccessToken();

        if ($currentToken instanceof PersonalAccessToken) {
            $currentToken->delete();

            return;
        }

        $user->tokens()->delete();
    }

    public function changePassword(User $user, string $newPassword): void
    {
        DB::transaction(function () use ($user, $newPassword): void {
            $user->forceFill([
                'password' => $newPassword,
            ])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateProfile(User $user, array $validated, ?UploadedFile $profileImage = null): User
    {
        $currentProfileImage = $user->profile_image;
        $newProfileImagePath = $profileImage?->store('users/profile-images', 'public');
        $removeProfileImage = ($validated['remove_profile_image'] ?? false) === true;

        if ($removeProfileImage && $newProfileImagePath !== null) {
            Storage::disk('public')->delete($newProfileImagePath);
            $newProfileImagePath = null;
        }

        if ($newProfileImagePath !== null) {
            $validated['profile_image'] = $newProfileImagePath;
        }

        if ($removeProfileImage) {
            $validated['profile_image'] = null;
        }

        unset($validated['remove_profile_image']);

        try {
            DB::transaction(function () use ($user, $validated): void {
                $user->fill($validated);
                $user->save();
            });
        } catch (\Throwable $exception) {
            if ($newProfileImagePath !== null) {
                Storage::disk('public')->delete($newProfileImagePath);
            }

            throw $exception;
        }

        if (! $removeProfileImage && $newProfileImagePath !== null && $currentProfileImage !== null && $currentProfileImage !== $newProfileImagePath) {
            Storage::disk('public')->delete($currentProfileImage);
        }

        if ($removeProfileImage && $currentProfileImage !== null) {
            Storage::disk('public')->delete($currentProfileImage);
        }

        return $user->fresh();
    }

    /**
     * @param  array{user_id:int,role:string,department_id?:int|null}  $validated
     */
    public function assignRole(array $validated): void
    {
        DB::transaction(function () use ($validated): void {
            /** @var User $targetUser */
            $targetUser = User::query()->findOrFail($validated['user_id']);

            /** @var Role $role */
            $role = Role::query()
                ->where('name', $validated['role'])
                ->where('guard_name', 'sanctum')
                ->firstOrFail();

            $departmentId = $validated['department_id'] ?? null;

            if ($departmentId !== null) {
                DepartmentUserRole::query()->firstOrCreate([
                    'user_id' => $targetUser->id,
                    'role_id' => $role->id,
                    'department_id' => $departmentId,
                ]);

                return;
            }

            $targetUser->roles()->syncWithoutDetaching([$role->id]);
        });
    }
}
