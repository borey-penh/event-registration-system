<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('event_code', 20)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('draft'); // draft | open | closed
            $table->string('registration_token', 32)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('form_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->text('question');
            $table->string('type', 20)->default('text'); // text | textarea | radio | checkbox | select | date
            $table->boolean('required')->default(false);
            $table->json('options')->nullable(); // list of options for radio/checkbox/select
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('candidate_code', 20)->unique();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('telegram_username', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('qr_token', 40)->unique();
            $table->timestamp('registered_at')->useCurrent();
            $table->string('attendance_status', 20)->default('registered'); // registered | joined
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
        });

        Schema::create('registration_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('form_questions')->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_answers');
        Schema::dropIfExists('registrations');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('form_questions');
        Schema::dropIfExists('events');
    }
};
