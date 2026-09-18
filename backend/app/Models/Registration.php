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
        'candidate_account_id',
        'qr_token',
        'registered_at',
        'attendance_status',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            // Plain "2026-09-16 01:41:00" instead of the ISO
            // "2026-09-16T01:41:00.000000Z" the SPA used to display raw.
            'registered_at' => 'datetime:Y-m-d H:i:s',
            'joined_at' => 'datetime:Y-m-d H:i:s',
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

    public function candidateAccount(): BelongsTo
    {
        return $this->belongsTo(CandidateAccount::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(RegistrationAnswer::class);
    }
}
