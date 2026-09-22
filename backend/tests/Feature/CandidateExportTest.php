<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Event;
use App\Models\FormQuestion;
use App\Models\Registration;
use App\Models\RegistrationAnswer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CandidateExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_downloads_csv_with_static_columns_and_answers(): void
    {
        $event = Event::create([
            'event_code' => 'EVT-EXP-1',
            'title' => 'Export test',
            'status' => 'open',
            'registration_token' => strtoupper(Str::random(10)),
        ]);

        $qText = FormQuestion::create([
            'event_id' => $event->id,
            'question' => 'Dietary requirement',
            'type' => 'text',
            'required' => false,
            'order' => 1,
        ]);
        $qChoice = FormQuestion::create([
            'event_id' => $event->id,
            'question' => 'T-shirt size',
            'type' => 'radio',
            'required' => false,
            'options' => ['S', 'M', 'L'],
            'order' => 2,
        ]);

        $candidate = Candidate::create([
            'candidate_code' => 'C-0001',
            'name' => 'Aisyah Putri',
            'email' => 'aisyah@example.com',
            'phone' => '081234567890',
        ]);
        $registration = Registration::create([
            'event_id' => $event->id,
            'candidate_id' => $candidate->id,
            'qr_token' => 'REG-TEST-0001',
            'registered_at' => now(),
            'attendance_status' => 'joined',
            'joined_at' => now(),
        ]);
        RegistrationAnswer::create([
            'registration_id' => $registration->id,
            'question_id' => $qText->id,
            'answer' => 'Vegetarian',
        ]);
        RegistrationAnswer::create([
            'registration_id' => $registration->id,
            'question_id' => $qChoice->id,
            'answer' => 'M',
        ]);

        $res = $this->actingAs(User::factory()->create())
            ->getJson("/api/events/{$event->id}/candidates/export");

        $res->assertOk();
        $this->assertSame('text/csv; charset=UTF-8', $res->headers->get('Content-Type'));
        $this->assertSame(
            'attachment; filename=candidates-EVT-EXP-1.csv',
            $res->headers->get('Content-Disposition')
        );

        $body = $res->streamedContent();
        // The UTF-8 BOM the SPA/Excel expects must open the file.
        $this->assertSame("\xEF\xBB\xBF", substr($body, 0, 3));

        // Parse (not string-match) so auto-quoting of fields containing
        // spaces cannot break the comparison.
        $csv = substr($body, 3); // strip BOM
        $lines = explode("\n", trim($csv));
        $this->assertCount(2, $lines, 'expected exactly one header row + one data row');

        // Header columns in table/form order.
        $this->assertSame([
            'No', 'Code', 'Name', 'Email', 'Phone', 'Telegram', 'Institution',
            'Role', 'Status', 'Registered at', 'Checked in at', 'Check-in code',
            'Dietary requirement', 'T-shirt size',
        ], str_getcsv($lines[0]));

        // Data row: static fields then the two answers, in form order.
        $this->assertSame([
            '1', 'C-0001', 'Aisyah Putri', 'aisyah@example.com', '081234567890',
            '', '', '', 'joined',
            $registration->registered_at->format('Y-m-d H:i:s'),
            $registration->joined_at->format('Y-m-d H:i:s'),
            'REG-TEST-0001', 'Vegetarian', 'M',
        ], str_getcsv($lines[1]));
    }

    public function test_export_escapes_formula_injection_and_commas(): void
    {
        $event = Event::create([
            'event_code' => 'EVT-EXP-2',
            'title' => 'Injection test',
            'status' => 'open',
            'registration_token' => strtoupper(Str::random(10)),
        ]);

        $qCmd = FormQuestion::create([
            'event_id' => $event->id,
            'question' => 'Command, with comma',
            'type' => 'text',
            'required' => false,
            'order' => 1,
        ]);

        $candidate = Candidate::create([
            'candidate_code' => 'C-0002',
            // "=HYPERLINK(...)" must be neutralized so it cannot execute
            // when a manager opens the CSV in Excel.
            'name' => '=HYPERLINK("http://evil.example","click")',
        ]);
        $registration = Registration::create([
            'event_id' => $event->id,
            'candidate_id' => $candidate->id,
            'qr_token' => 'REG-TEST-0002',
        ]);
        RegistrationAnswer::create([
            'registration_id' => $registration->id,
            'question_id' => $qCmd->id,
            'answer' => '=1+1',
        ]);

        $body = $this->actingAs(User::factory()->create())
            ->getJson("/api/events/{$event->id}/candidates/export")
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $body, 'name must be apostrophe-escaped');
        $this->assertStringContainsString("'=1+1", $body, 'answer must be apostrophe-escaped');
        $this->assertStringContainsString('"Command, with comma"', $body, 'header comma must be quoted, not broken');
    }

    public function test_export_requires_authentication(): void
    {
        $event = Event::create([
            'event_code' => 'EVT-EXP-3',
            'title' => 'Auth test',
            'status' => 'open',
            'registration_token' => strtoupper(Str::random(10)),
        ]);

        $this->getJson("/api/events/{$event->id}/candidates/export")->assertUnauthorized();
    }
}
