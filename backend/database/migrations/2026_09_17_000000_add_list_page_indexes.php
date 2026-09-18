<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Matches the descending, per-event registration page query.
        Schema::table('registrations', function (Blueprint $table) {
            $table->index(['event_id', 'id']);
        });

        // Keeps the common newest-events list ordered without a filesort.
        Schema::table('events', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'id']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
