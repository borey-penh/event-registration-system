<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->index('name');
            $table->index('candidate_code');
            $table->index('phone');
            $table->index('email');
            $table->index('institution');
        });

        // Speeds up paginated ordering + per-event filters + check-in scans.
        Schema::table('registrations', function (Blueprint $table) {
            $table->index(['event_id', 'attendance_status']);
            $table->index('attendance_status');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['candidate_code']);
            $table->dropIndex(['phone']);
            $table->dropIndex(['email']);
            $table->dropIndex(['institution']);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'attendance_status']);
            $table->dropIndex(['attendance_status']);
        });
    }
};
