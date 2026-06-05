<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AssignRoleRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\CompleteRegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RequestRegisterOtpRequest;
use App\Http\Requests\Auth\RoleIndexRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\VerifyRegisterOtpRequest;
use App\Http\Resources\Auth\AuthUserResource;
use App\Http\Resources\RoleResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\RoleService;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly RoleService $roleService,
        private readonly ApiResponse $apiResponse,
    ) {}

    public function requestRegisterOtp(RequestRegisterOtpRequest $request): JsonResponse
    {
        $this->applyLocale($request);

        $throttleKey = $this->registerOtpThrottleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            return $this->apiResponse->error(
                __('auth.otp_too_many_send_attempts'),
                429,
                null,
                [
                    'retry_after_seconds' => RateLimiter::availableIn($throttleKey),
                ],
            );
        }

        $result = $this->authService->requestRegistrationOtp(
            $request->string('email')->toString(),
            app()->getLocale(),
        );

        if ($result['status'] === 'email_exists') {
            return $this->apiResponse->error(__('auth.email_already_registered'), 409);
        }

        if ($result['status'] === 'cooldown') {
            return $this->apiResponse->error(
                __('auth.otp_resend_cooldown'),
                429,
                null,
                [
                    'retry_after_seconds' => $result['retry_after_seconds'],
                ],
            );
        }

        RateLimiter::hit($throttleKey, 900);

        return $this->apiResponse->success(
            [
                'email' => $result['email'],
                'expires_in_seconds' => $result['expires_in_seconds'],
            ],
            __('auth.otp_sent_successfully'),
            200,
        );
    }

    public function verifyRegisterOtp(VerifyRegisterOtpRequest $request): JsonResponse
    {
        $this->applyLocale($request);

        $throttleKey = $this->verifyOtpThrottleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            return $this->apiResponse->error(
                __('auth.otp_too_many_verify_attempts'),
                429,
                null,
                [
                    'retry_after_seconds' => RateLimiter::availableIn($throttleKey),
                ],
            );
        }

        $result = $this->authService->verifyRegistrationOtp(
            $request->string('email')->toString(),
            $request->string('code')->toString(),
        );

        if ($result['status'] === 'verified') {
            RateLimiter::clear($throttleKey);

            return $this->apiResponse->success(
                [
                    'verification_token' => $result['verification_token'],
                    'expires_in_seconds' => $result['expires_in_seconds'],
                ],
                __('auth.otp_verified_successfully'),
                200,
            );
        }

        RateLimiter::hit($throttleKey, 600);

        if ($result['status'] === 'too_many_attempts') {
            return $this->apiResponse->error(
                __('auth.otp_too_many_verify_attempts'),
                429,
                null,
                [
                    'retry_after_seconds' => $result['retry_after_seconds'],
                ],
            );
        }

        return $this->apiResponse->error(
            $result['status'] === 'expired'
                ? __('auth.otp_expired')
                : __('auth.otp_invalid'),
            422,
        );
    }

    public function completeRegister(CompleteRegisterRequest $request): JsonResponse
    {
        $this->applyLocale($request);

        $result = $this->authService->register(
            $request->validated(),
            $request->file('profile_image'),
        );

        if ($result['status'] === 'invalid_verification_token') {
            return $this->apiResponse->error(__('auth.invalid_verification_token'), 422);
        }

        if ($result['status'] === 'email_exists') {
            return $this->apiResponse->error(__('auth.email_already_registered'), 409);
        }

        /** @var User $user */
        $user = $result['user'];

        return $this->apiResponse->success(
            [
                'token' => $result['token'],
                'user' => AuthUserResource::make($user)->resolve(),
                'roles' => $user->effectiveRoleNames(),
            ],
            __('auth.registered_successfully'),
            201,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $this->applyLocale($request);

        $credentials = $request->safe()->only(['email', 'password']);
        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return $this->apiResponse->error(
                __('auth.too_many_attempts'),
                429,
                null,
                [
                    'retry_after_seconds' => RateLimiter::availableIn($throttleKey),
                ],
            );
        }

        $result = $this->authService->login($credentials);

        if ($result['status'] === 'locked') {
            return $this->apiResponse->error(
                __('auth.too_many_attempts'),
                429,
                null,
                [
                    'retry_after_seconds' => $result['retry_after_seconds'],
                ],
            );
        }

        if ($result['status'] === 'invalid_email' || $result['status'] === 'invalid_password') {
            RateLimiter::hit($throttleKey, 60);

            $failedField = $result['status'] === 'invalid_email' ? 'email' : 'password';
            $messageKey = $result['status'] === 'invalid_email' ? 'auth.login_email_not_found' : 'auth.login_password_incorrect';

            return $this->apiResponse->error(
                __($messageKey),
                422,
                [
                    $failedField => [__($messageKey)],
                ],
            );
        }

        RateLimiter::clear($throttleKey);

        /** @var User $user */
        $user = $result['user'];

        $user->loadMissing('departmentUserRoles.department.lab');

        $labId = $user->departmentUserRoles
            ->map(static fn($departmentUserRole): ?int => $departmentUserRole->department?->lab_id ?? $departmentUserRole->department?->lab?->id)
            ->filter()
            ->unique()
            ->values()
            ->first();

        $departments = $user->departmentUserRoles
            ->map(static fn($departmentUserRole): array => [
                'id' => $departmentUserRole->department?->id,
                'name' => $departmentUserRole->department?->name,
                'lab_id' => $departmentUserRole->department?->lab_id,
            ])
            ->filter(static fn(array $department): bool => $department['id'] !== null)
            ->unique('id')
            ->values()
            ->all();

        $responseData = [
            'token' => $result['token'],
            'user' => AuthUserResource::make($user)->resolve(),
            'roles' => $user->effectiveRoleNames(),
            'lab_id' => $labId,
        ];

        if ($departments !== []) {
            $responseData['departments'] = $departments;
        }

        return $this->apiResponse->success(
            $responseData,
            __('auth.logged_in_successfully'),
            200,
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->applyLocale($request);

        $user = $request->user();

        if ($user !== null) {
            $this->authService->logout($user);
        }

        return $this->apiResponse->success(null, __('auth.logged_out_successfully'), 200);
    }

    public function me(Request $request): JsonResponse
    {
        $this->applyLocale($request);

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $departmentId = $request->integer('department_id') ?: null;

        return $this->apiResponse->success(
            [
                'user' => AuthUserResource::make($user)->resolve(),
                'roles' => $user->effectiveRoleNames($departmentId),
                'permissions' => $user->effectivePermissionNames($departmentId),
            ],
            __('messages.success'),
            200,
        );
    }

    public function roles(RoleIndexRequest $request): JsonResponse
    {
        $this->applyLocale($request);

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $roles = $this->roleService->listRoles();

        // Exclude certain roles for lab_manager
        if ($user->hasRole('lab_manager')) {
            $roles = $roles->whereNotIn('name', ['system_admin', 'lab_manager', 'doctor'])->values();
        }

        return $this->apiResponse->success(
            [
                'roles' => RoleResource::collection($roles)->resolve(),
            ],
            __('auth.roles_retrieved_successfully'),
            200,
        );
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $this->applyLocale($request);

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $updatedUser = $this->authService->updateProfile(
            $user,
            $request->validated(),
            $request->file('profile_image'),
        );

        return $this->apiResponse->success(
            [
                'user' => AuthUserResource::make($updatedUser)->resolve(),
            ],
            __('auth.profile_updated_successfully'),
            200,
        );
    }

    public function removeProfileImage(Request $request): JsonResponse
    {
        $this->applyLocale($request);

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $updatedUser = $this->authService->updateProfile($user, [
            'remove_profile_image' => true,
        ]);

        return $this->apiResponse->success(
            [
                'user' => AuthUserResource::make($updatedUser)->resolve(),
            ],
            __('auth.profile_image_removed_successfully'),
            200,
        );
    }

    public function assignRole(AssignRoleRequest $request): JsonResponse
    {
        $this->applyLocale($request);

        $this->authService->assignRole($request->validated());

        return $this->apiResponse->success(null, __('auth.role_assigned_successfully'), 200);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->applyLocale($request);

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $this->authService->changePassword($user, $request->string('password')->toString());

        return $this->apiResponse->success(null, __('auth.password_updated_successfully'), 200);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('email')->toString()) . '|' . $request->ip());
    }

    private function registerOtpThrottleKey(Request $request): string
    {
        return Str::transliterate('register-otp-send|' . Str::lower($request->string('email')->toString()) . '|' . $request->ip());
    }

    private function verifyOtpThrottleKey(Request $request): string
    {
        return Str::transliterate('register-otp-verify|' . Str::lower($request->string('email')->toString()) . '|' . $request->ip());
    }

    private function applyLocale(Request $request): void
    {
        $locale = $request->header('X-Locale');

        if (is_string($locale) && in_array($locale, ['ar', 'en'], true)) {
            app()->setLocale($locale);
        }
    }
}
