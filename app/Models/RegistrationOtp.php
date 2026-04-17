<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationOtp extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'otp_hash',
        'expires_at',
        'verify_attempts',
        'last_sent_at',
        'verified_at',
        'verification_token',
        'verification_token_expires_at',
        'consumed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'verified_at' => 'datetime',
            'verification_token_expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
