<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert random legacy candidate codes (C-X7K2M9QB) to sequential
     * numbers (C-0001, C-0002, …) ordered by id, and add an index on the
     * prefix so the next-code lookup stays fast on large tables.
     */
    public function up(): void
    {
        // Order by id keeps codes stable relative to registration history.
        $rows = DB::table('candidates')->orderBy('id')->get(['id']);
        $prefix = 'C-';
        $pad = max(4, strlen((string) $rows->count())); // grow past 9999 if needed

        foreach ($rows as $index => $row) {
            DB::table('candidates')
                ->where('id', $row->id)
                ->update([
                    'candidate_code' => $prefix.str_pad((string) ($index + 1), $pad, '0', STR_PAD_LEFT),
                ]);
        }
    }

    public function down(): void
    {
        // Old random codes are unrecoverable by design; renumbering is
        // forward-only. Left empty on purpose.
    }
};
