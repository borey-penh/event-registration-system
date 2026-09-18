<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'event_code',
        'title',
        'description',
        'location',
        'start_date',
        'start_time',
        'end_date',
        'status',
        'registration_token',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            // Serialize as plain "2026-09-16" — the default ISO format
            // ("2026-09-16T00:00:00.000000Z") leaks into the SPA otherwise.
            'start_date' => 'date:Y-m-d',
            'start_time' => 'datetime:H:i:s',
            'end_date' => 'date:Y-m-d',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(FormQuestion::class)->orderBy('order');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /** Candidates may edit while the manager keeps the event open. */
    public function canCandidateEdit(): bool
    {
        // The end date is event information, not a reliable close timestamp.
        // The manager explicitly closes the event when submissions must stop.
        return $this->status === 'open';
    }

    /**
     * Generate the next sequential event code, formatted EVT-YYYY-NN
     * (e.g. EVT-2026-01). The number resets each year.
     */
    public static function nextEventCode(): string
    {
        $year = now()->format('Y');
        $prefix = "EVT-{$year}-";

        $max = static::where('event_code', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(event_code, ?) AS UNSIGNED)) as max_seq', [strlen($prefix) + 1])
            ->value('max_seq');

        return $prefix.str_pad((string) ($max + 1), 2, '0', STR_PAD_LEFT);
    }
}
