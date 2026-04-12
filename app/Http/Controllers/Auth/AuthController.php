<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AssignRoleRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
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

    public function register(RegisterRequest $request): JsonResponse
    {
        $this->applyLocale($request);

        $result = $this->authService->register(
            $request->validated(),
            $request->string('device_name')->toString(),
        );

        /** @var User $user */
        $user = $result['user'];

        return new JsonResponse([
            'success' => true,
            'message' => __('auth.registered_successfully'),
            'data' => [
                'token' => $result['token'],
                'user' => AuthUserResource::make($user)->resolve(),
            ],
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
                'message' => __('auth.too_many_attempts'),
                'data' => [
                    'retry_after_seconds' => RateLimiter::availableIn($throttleKey),
                ],
            ], 429);
        }

        $result = $this->authService->login(
            $credentials,
            $request->string('device_name')->toString(),
        );

        if ($result === null) {
            RateLimiter::hit($throttleKey, 60);

            return new JsonResponse([
                'success' => false,
                'message' => __('auth.invalid_credentials'),
            ], 422);
        }

        RateLimiter::clear($throttleKey);

        /** @var User $user */
        $user = $result['user'];

        return new JsonResponse([
            'success' => true,
            'message' => __('auth.logged_in_successfully'),
            'data' => [
                'token' => $result['token'],
                'user' => AuthUserResource::make($user)->resolve(),
            ],
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
            'message' => __('auth.logged_out_successfully'),
            'data' => null,
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
                'message' => __('auth.unauthenticated'),
            ], 401);
        }

        $departmentId = $request->integer('department_id') ?: null;

        return new JsonResponse([
            'success' => true,
            'message' => __('messages.success'),
            'data' => [
                'user' => AuthUserResource::make($user)->resolve(),
                'roles' => $user->effectiveRoleNames($departmentId),
                'permissions' => $user->effectivePermissionNames($departmentId),
            ],
        ]);
    }

    public function assignRole(AssignRoleRequest $request): JsonResponse
    {
        $this->applyLocale($request);

        $this->authService->assignRole($request->validated());

        return new JsonResponse([
            'success' => true,
            'message' => __('auth.role_assigned_successfully'),
            'data' => null,
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('email')->toString()).'|'.$request->ip());
    }

    private function applyLocale(Request $request): void
    {
        $locale = $request->header('X-Locale');

        if (is_string($locale) && in_array($locale, ['ar', 'en'], true)) {
            app()->setLocale($locale);
        }
    }
}
