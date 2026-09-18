<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_accounts', function (Blueprint $table) {
            // Social-only accounts (created via Google/Facebook) have no
            // password; email accounts keep theirs.
            $table->string('password')->nullable()->change();

            // 'google' | 'facebook' when the account came from social login,
            // null for plain email + password accounts.
            $table->string('provider', 20)->nullable()->after('password');
            $table->string('provider_id')->nullable()->after('provider');
            $table->string('avatar')->nullable()->after('name');

            // Look up social accounts fast: (provider, provider_id) is the
            // natural key for an OAuth identity.
            $table->unique(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::table('candidate_accounts', function (Blueprint $table) {
            $table->dropUnique(['provider', 'provider_id']);
            $table->dropColumn(['provider', 'provider_id', 'avatar']);
        });
    }
};
