<?php

namespace App\Services\Auth;

use App\Models\DepartmentUserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    /**
     * @param  array{name:string,email:string,password:string,phone?:string|null}  $validated
     * @return array{user:User,token:string}
     */
    public function register(array $validated, string $tokenName): array
    {
        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create($validated);

            $defaultRoleId = Role::query()
                ->where('name', 'doctor')
                ->where('guard_name', 'sanctum')
                ->value('id');

            if ($defaultRoleId !== null) {
                $user->roles()->syncWithoutDetaching([$defaultRoleId]);
            }

            return $user;
        });

        $token = $user->createToken($tokenName !== '' ? $tokenName : 'api-token', ['*'])->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * @param  array{email:string,password:string}  $credentials
     * @return array{user:User,token:string}|null
     */
    public function login(array $credentials, string $tokenName): ?array
    {
        if (! Auth::attempt($credentials)) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        $token = $user->createToken($tokenName !== '' ? $tokenName : 'api-token', ['*'])->plainTextToken;

        return [
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
