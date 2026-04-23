<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AssignRoleRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\CompleteRegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RequestRegisterOtpRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\VerifyRegisterOtpRequest;
use App\Http\Resources\Auth\AuthUserResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function requestRegisterOtp(RequestRegisterOtpRequest $request): JsonResponse
    {
        $this->applyLocale($request);

        $throttleKey = $this->registerOtpThrottleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            return new JsonResponse([
                'success' => false,
                'status' => 429,
                'message' => __('auth.otp_too_many_send_attempts'),
                'data' => [
                    'retry_after_seconds' => RateLimiter::availableIn($throttleKey),
                ],
                'errors' => null,
            ], 429);
        }

        $result = $this->authService->requestRegistrationOtp(
            $request->string('email')->toString(),
            app()->getLocale(),
        );

        if ($result['status'] === 'email_exists') {
            return new JsonResponse([
                'success' => false,
                'status' => 422,
                'message' => __('auth.email_already_registered'),
                'data' => null,
                'errors' => null,
            ], 422);
        }

        if ($result['status'] === 'cooldown') {
            return new JsonResponse([
                'success' => false,
                'status' => 429,
                'message' => __('auth.otp_resend_cooldown'),
                'data' => [
                    'retry_after_seconds' => $result['retry_after_seconds'],
                ],
                'errors' => null,
            ], 429);
        }

        RateLimiter::hit($throttleKey, 900);

        return new JsonResponse([
            'success' => true,
            'status' => 200,
            'message' => __('auth.otp_sent_successfully'),
            'data' => [
                'email' => $result['email'],
                'expires_in_seconds' => $result['expires_in_seconds'],
            ],
            'errors' => null,
        ]);
    }

    public function verifyRegisterOtp(VerifyRegisterOtpRequest $request): JsonResponse
    {
        $this->applyLocale($request);

        $throttleKey = $this->verifyOtpThrottleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            return new JsonResponse([
                'success' => false,
                'status' => 429,
                'message' => __('auth.otp_too_many_verify_attempts'),
                'data' => [
                    'retry_after_seconds' => RateLimiter::availableIn($throttleKey),
                ],
                'errors' => null,
            ], 429);
        }

        $result = $this->authService->verifyRegistrationOtp(
            $request->string('email')->toString(),
            $request->string('code')->toString(),
        );

        if ($result['status'] === 'verified') {
            RateLimiter::clear($throttleKey);

            return new JsonResponse([
                'success' => true,
                'status' => 200,
                'message' => __('auth.otp_verified_successfully'),
                'data' => [
                    'verification_token' => $result['verification_token'],
                    'expires_in_seconds' => $result['expires_in_seconds'],
                ],
                'errors' => null,
            ]);
        }

        RateLimiter::hit($throttleKey, 600);

        if ($result['status'] === 'too_many_attempts') {
            return new JsonResponse([
                'success' => false,
                'status' => 429,
                'message' => __('auth.otp_too_many_verify_attempts'),
                'data' => [
                    'retry_after_seconds' => $result['retry_after_seconds'],
                ],
                'errors' => null,
            ], 429);
        }

        return new JsonResponse([
            'success' => false,
            'status' => 422,
            'message' => $result['status'] === 'expired'
                ? __('auth.otp_expired')
                : __('auth.otp_invalid'),
            'data' => null,
            'errors' => null,
        ], 422);
    }

    public function completeRegister(CompleteRegisterRequest $request): JsonResponse
    {
        $this->applyLocale($request);

        $result = $this->authService->register(
            $request->validated(),
            $request->file('profile_image'),
        );

        if ($result['status'] === 'invalid_verification_token') {
            return new JsonResponse([
                'success' => false,
                'status' => 422,
                'message' => __('auth.invalid_verification_token'),
                'data' => null,
                'errors' => null,
            ], 422);
        }

        if ($result['status'] === 'email_exists') {
            return new JsonResponse([
                'success' => false,
                'status' => 422,
                'message' => __('auth.email_already_registered'),
                'data' => null,
                'errors' => null,
            ], 422);
        }

        /** @var User $user */
        $user = $result['user'];

        return new JsonResponse([
            'success' => true,
            'status' => 201,
            'message' => __('auth.registered_successfully'),
            'data' => [
                'token' => $result['token'],
                'user' => AuthUserResource::make($user)->resolve(),
            ],
            'errors' => null,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $this->applyLocale($request);

        $credentials = $request->safe()->only(['email', 'password']);
        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return new JsonResponse([
                'success' => false,
                'status' => 429,
                'message' => __('auth.too_many_attempts'),
                'data' => [
                    'retry_after_seconds' => RateLimiter::availableIn($throttleKey),
                ],
                'errors' => null,
            ], 429);
        }

        $result = $this->authService->login($credentials);

        if ($result['status'] === 'locked') {
            return new JsonResponse([
                'success' => false,
                'status' => 429,
                'message' => __('auth.too_many_attempts'),
                'data' => [
                    'retry_after_seconds' => $result['retry_after_seconds'],
                ],
                'errors' => null,
            ], 429);
        }

        if ($result['status'] === 'invalid') {
            RateLimiter::hit($throttleKey, 60);

            return new JsonResponse([
                'success' => false,
                'status' => 422,
                'message' => __('auth.invalid_credentials'),
                'data' => null,
                'errors' => null,
            ], 422);
        }

        RateLimiter::clear($throttleKey);

        /** @var User $user */
        $user = $result['user'];

        return new JsonResponse([
            'success' => true,
            'status' => 200,
            'message' => __('auth.logged_in_successfully'),
            'data' => [
                'token' => $result['token'],
                'user' => AuthUserResource::make($user)->resolve(),
            ],
            'errors' => null,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->applyLocale($request);

        $user = $request->user();

        if ($user !== null) {
            $this->authService->logout($user);
        }

        return new JsonResponse([
            'success' => true,
            'status' => 200,
            'message' => __('auth.logged_out_successfully'),
            'data' => null,
            'errors' => null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $this->applyLocale($request);

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return new JsonResponse([
                'success' => false,
                'status' => 401,
                'message' => __('auth.unauthenticated'),
                'data' => null,
                'errors' => null,
            ], 401);
        }

        $departmentId = $request->integer('department_id') ?: null;

        return new JsonResponse([
            'success' => true,
            'status' => 200,
            'message' => __('messages.success'),
            'data' => [
                'user' => AuthUserResource::make($user)->resolve(),
                'roles' => $user->effectiveRoleNames($departmentId),
                'permissions' => $user->effectivePermissionNames($departmentId),
            ],
            'errors' => null,
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $this->applyLocale($request);

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return new JsonResponse([
                'success' => false,
                'status' => 401,
                'message' => __('auth.unauthenticated'),
                'data' => null,
                'errors' => null,
            ], 401);
        }

        $updatedUser = $this->authService->updateProfile(
            $user,
            $request->validated(),
            $request->file('profile_image'),
        );

        return new JsonResponse([
            'success' => true,
            'status' => 200,
            'message' => __('auth.profile_updated_successfully'),
            'data' => [
                'user' => AuthUserResource::make($updatedUser)->resolve(),
            ],
            'errors' => null,
        ]);
    }

    public function assignRole(AssignRoleRequest $request): JsonResponse
    {
        $this->applyLocale($request);

        $this->authService->assignRole($request->validated());

        return new JsonResponse([
            'success' => true,
            'status' => 200,
            'message' => __('auth.role_assigned_successfully'),
            'data' => null,
            'errors' => null,
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->applyLocale($request);

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return new JsonResponse([
                'success' => false,
                'status' => 401,
                'message' => __('auth.unauthenticated'),
                'data' => null,
                'errors' => null,
            ], 401);
        }

        $this->authService->changePassword($user, $request->string('password')->toString());

        return new JsonResponse([
            'success' => true,
            'status' => 200,
            'message' => __('auth.password_updated_successfully'),
            'data' => null,
            'errors' => null,
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('email')->toString()).'|'.$request->ip());
    }

    private function registerOtpThrottleKey(Request $request): string
    {
        return Str::transliterate('register-otp-send|'.Str::lower($request->string('email')->toString()).'|'.$request->ip());
    }

    private function verifyOtpThrottleKey(Request $request): string
    {
        return Str::transliterate('register-otp-verify|'.Str::lower($request->string('email')->toString()).'|'.$request->ip());
    }

    private function applyLocale(Request $request): void
    {
        $locale = $request->header('X-Locale');

        if (is_string($locale) && in_array($locale, ['ar', 'en'], true)) {
            app()->setLocale($locale);
        }
    }
}
