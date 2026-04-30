<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'profile_image',
        'birthdate',
        'location',
        'lab_id',
        'location_lat',
        'location_lng',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birthdate' => 'date',
            'lab_id' => 'integer',
            'location_lat' => 'decimal:7',
            'location_lng' => 'decimal:7',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function deliveryTasks(): HasMany
    {
        return $this->hasMany(DeliveryTask::class);
    }

    public function departmentUserRoles(): HasMany
    {
        return $this->hasMany(DepartmentUserRole::class);
    }

    public function roles(): MorphToMany
    {
        return $this->morphToMany(Role::class, 'model', 'model_has_roles', 'model_id', 'role_id');
    }

    public function permissions(): MorphToMany
    {
        return $this->morphToMany(Permission::class, 'model', 'model_has_permissions', 'model_id', 'permission_id');
    }

    public function hasRole(string|array $roleNames, ?int $departmentId = null): bool
    {
        $requestedRoles = collect((array) $roleNames)
            ->filter(static fn (string $roleName): bool => $roleName !== '')
            ->map(static fn (string $roleName): string => trim($roleName));

        if ($requestedRoles->isEmpty()) {
            return false;
        }

        return $this->effectiveRoleNames($departmentId)
            ->intersect($requestedRoles)
            ->isNotEmpty();
    }

    public function hasPermission(string|array $permissionNames, ?int $departmentId = null): bool
    {
        $requestedPermissions = collect((array) $permissionNames)
            ->filter(static fn (string $permissionName): bool => $permissionName !== '')
            ->map(static fn (string $permissionName): string => trim($permissionName));

        if ($requestedPermissions->isEmpty()) {
            return false;
        }

        return $this->effectivePermissionNames($departmentId)
            ->intersect($requestedPermissions)
            ->isNotEmpty();
    }

    public function hasRoleInDepartment(string|array $roleNames, int $departmentId): bool
    {
        return $this->hasRole($roleNames, $departmentId);
    }

    public function hasPermissionInDepartment(string|array $permissionNames, int $departmentId): bool
    {
        return $this->hasPermission($permissionNames, $departmentId);
    }

    public function hasDepartmentAccess(int $departmentId): bool
    {
        $this->loadMissing('departmentUserRoles:department_id,user_id');

        return $this->departmentUserRoles
            ->pluck('department_id')
            ->contains($departmentId);
    }

    /**
     * @return Collection<int, string>
     */
    public function effectiveRoleNames(?int $departmentId = null): Collection
    {
        $this->loadMissing('roles:id,name', 'departmentUserRoles.role:id,name');

        $globalRoleNames = $this->roles->pluck('name');

        $departmentRoleNames = $this->departmentUserRoles
            ->when($departmentId !== null, static fn (Collection $roles): Collection => $roles->where('department_id', $departmentId))
            ->pluck('role.name')
            ->filter();

        return $globalRoleNames
            ->merge($departmentRoleNames)
            ->map(static fn (string $roleName): string => trim($roleName))
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function effectivePermissionNames(?int $departmentId = null): Collection
    {
        $this->loadMissing('roles.permissions:id,name', 'departmentUserRoles.role.permissions:id,name');

        $globalRolePermissions = $this->roles
            ->flatMap(static fn (Role $role): Collection => $role->permissions->pluck('name'));

        $departmentRolePermissions = $this->departmentUserRoles
            ->when($departmentId !== null, static fn (Collection $roles): Collection => $roles->where('department_id', $departmentId))
            ->flatMap(static fn (DepartmentUserRole $departmentUserRole): Collection => $departmentUserRole->role?->permissions?->pluck('name') ?? collect());

        return $globalRolePermissions
            ->merge($departmentRolePermissions)
            ->filter(static fn (?string $permissionName): bool => filled($permissionName))
            ->map(static fn (string $permissionName): string => trim($permissionName))
            ->unique()
            ->values();
    }
}
