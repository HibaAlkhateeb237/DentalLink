<?php

namespace App\Models;

use Database\Factories\PortfolioCaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioCase extends Model
{
    /** @use HasFactory<PortfolioCaseFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'case_name',
        'before_image_path',
        'after_image_path',
        'duration_minutes',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
