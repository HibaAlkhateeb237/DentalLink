<?php

namespace App\Models;

use App\Support\EmployeeRoles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'guard_name',
        'lab_id',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions');
    }

    public function users(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'model', 'model_has_roles', 'role_id', 'model_id');
    }

    public function departmentUserRoles(): HasMany
    {
        return $this->hasMany(DepartmentUserRole::class);
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->whereNull('lab_id');
    }

    public function scopeByLab(Builder $query, int $labId): Builder
    {
        return $query->whereNull('lab_id')->orWhere('lab_id', $labId);
    }

    public function scopeEmployeeRoles(Builder $query, int $labId): Builder
    {
        return $query
            ->where('guard_name', 'sanctum')
            ->where(function (Builder $q) use ($labId): void {
                $q->whereNull('lab_id')->whereIn('name', EmployeeRoles::system())
                    ->orWhere('lab_id', $labId);
            });
    }

    public function isCustom(): bool
    {
        return $this->lab_id !== null;
    }
}
