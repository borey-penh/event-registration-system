<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('name')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('candidate_account_id')
                ->nullable()
                ->after('candidate_id')
                ->constrained('candidate_accounts')->nullOnDelete();
        });

        // Speed up "find my registration for this event" on login.
        Schema::table('registrations', function (Blueprint $table) {
            $table->index(['candidate_account_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['candidate_account_id', 'event_id']);
            $table->dropConstrainedForeignId('candidate_account_id');
        });

        Schema::dropIfExists('candidate_accounts');
    }
};
