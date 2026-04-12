<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ModelHasPermission extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = [
        'permission_id',
        'model_type',
        'model_id',
    ];

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
