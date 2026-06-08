<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_id',
        'name',
        'sort_order',
        'description',
        'is_management',
        'time_allowed',
    ];

    protected $casts = [
        'time_allowed' => 'integer',
    ];

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function departmentUserRoles(): HasMany
    {
        return $this->hasMany(DepartmentUserRole::class);
    }
}
