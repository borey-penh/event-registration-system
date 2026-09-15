<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Registration extends Model
{
    protected $fillable = [
        'event_id',
        'candidate_id',
        'qr_token',
        'registered_at',
        'attendance_status',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'joined_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(RegistrationAnswer::class);
    }
}
