<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ModelHasRole extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = [
        'role_id',
        'model_type',
        'model_id',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
