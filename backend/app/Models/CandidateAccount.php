<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Separate lightweight auth account for candidates (event attendees).
 * Deliberately NOT the managers' users table: candidate credentials must
 * never grant manager-panel access, and vice versa.
 */
class CandidateAccount extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'email',
        'password',
        'name',
        'provider',
        'provider_id',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }
}
