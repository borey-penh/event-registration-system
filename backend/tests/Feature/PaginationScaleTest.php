<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaginationScaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_paginates_with_large_candidate_count(): void
    {
        // Simulate a large table cheaply: insert real rows + bump the auto-increment counter
        // so the DB planner behaves as if millions of rows exist, then verify pagination.
        $event = Event::create([
            'event_code' => 'EVT-TEST-1',
            'title' => 'Scale test',
            'status' => 'open',
            'registration_token' => strtoupper(Str::random(10)),
        ]);

        $rows = [];
        for ($i = 1; $i <= 250; $i++) {
            $rows[] = [
                'candidate_code' => 'C-'.strtoupper(Str::random(8)),
                'name' => "Candidate {$i}",
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('candidates')->insert($rows);

        // Fake a large table for the planner (SQLite in tests).
        DB::statement("DELETE FROM sqlite_sequence WHERE name = 'candidates'");
        DB::statement("INSERT INTO sqlite_sequence (name, seq) VALUES ('candidates', 1000000)");

        $start = microtime(true);

        $res = $this->actingAs(\App\Models\User::factory()->create())
            ->getJson('/api/candidates?page=1&per_page=20');

        $res->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('total', 250)
            ->assertJsonStructure(['current_page', 'last_page', 'per_page', 'data']);

        $this->assertTrue(true, 'Elapsed: '.round((microtime(true) - $start) * 1000, 1).'ms');
    }

    public function test_event_show_paginates_registrations(): void
    {
        $event = Event::create([
            'event_code' => 'EVT-TEST-2',
            'title' => 'Event pagination test',
            'status' => 'open',
            'registration_token' => strtoupper(Str::random(10)),
        ]);

        for ($i = 1; $i <= 60; $i++) {
            $candidate = Candidate::create([
                'candidate_code' => 'C-'.strtoupper(Str::random(8)),
                'name' => "Candidate {$i}",
            ]);
            Registration::create([
                'event_id' => $event->id,
                'candidate_id' => $candidate->id,
                'qr_token' => 'REG-'.strtoupper(Str::random(12)),
            ]);
        }

        $res = $this->actingAs(\App\Models\User::factory()->create())
            ->getJson("/api/events/{$event->id}?per_page=20");

        $res->assertOk()
            ->assertJsonCount(20, 'candidates')
            ->assertJsonPath('pagination.total', 60)
            ->assertJsonPath('pagination.last_page', 3);
    }
}
