<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Candidate extends Model
{
    protected $fillable = [
        'candidate_code',
        'name',
        'email',
        'phone',
        'telegram_username',
        'institution',
        'role',
    ];

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }
}
