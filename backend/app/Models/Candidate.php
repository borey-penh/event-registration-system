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

    /**
     * Next sequential candidate code: C-0001, C-0002, … Zero-padded to 4
     * digits (grows past 4 automatically at 10000+). Uses MAX over a numeric
     * cast so gaps from deletions never cause unique-collision retries.
     */
    public static function nextCandidateCode(): string
    {
        $max = static::where('candidate_code', 'like', 'C-%')
            ->selectRaw('MAX(CAST(SUBSTRING(candidate_code, 3) AS UNSIGNED)) as max_seq')
            ->value('max_seq');

        return 'C-'.str_pad((string) ((int) $max + 1), 4, '0', STR_PAD_LEFT);
    }
}
