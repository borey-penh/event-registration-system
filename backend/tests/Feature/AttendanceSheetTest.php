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
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class AttendanceSheetTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private int $genderQuestionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'event_code' => 'EVT-ATT-1',
            'title' => 'Tech Meetup 2026',
            'location' => 'Phnom Penh',
            'start_date' => '2026-09-16',
            'status' => 'open',
            'registration_token' => strtoupper(Str::random(10)),
        ]);

        $this->genderQuestionId = FormQuestion::create([
            'event_id' => $this->event->id,
            'question' => 'Gender',
            'type' => 'radio',
            'required' => false,
            'options' => ['M', 'F', 'P'],
            'order' => 1,
        ])->id;
    }

    public function test_downloads_xlsx_with_template_layout_and_prefilled_ticks(): void
    {
        $candidate = Candidate::create([
            'candidate_code' => 'C-0001',
            'name' => 'Aisyah Putri',
            'phone' => '081234567890',
            'institution' => 'LLC',
        ]);
        $registration = Registration::create([
            'event_id' => $this->event->id,
            'candidate_id' => $candidate->id,
            'qr_token' => 'REG-ATT-0001',
        ]);
        RegistrationAnswer::create([
            'registration_id' => $registration->id,
            'question_id' => $this->genderQuestionId,
            'answer' => 'F',
        ]);

        $res = $this->actingAs(User::factory()->create())
            ->getJson("/api/events/{$this->event->id}/attendance-sheet");

        $res->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $res->headers->get('Content-Type')
        );
        $this->assertStringContainsString('attendance-evt-att-1.xlsx', (string) $res->headers->get('Content-Disposition'));

        // Read the generated workbook back and assert on real cells.
        $sheet = $this->loadSheet($res->streamedContent());

        $this->assertSame('Attendance List', $sheet->getCell('H1')->getValue());
        $this->assertSame('Activities:', $sheet->getCell('A2')->getValue());
        $this->assertSame('Tech Meetup 2026', $sheet->getCell('B2')->getValue());
        $this->assertSame('Phnom Penh', $sheet->getCell('B3')->getValue());
        $this->assertSame('16/09/2026', $sheet->getCell('B4')->getValue());

        // Table header row: title + 3 info rows + 3 legend rows + 2 spacers.
        $this->assertSame('No.', $sheet->getCell('A10')->getValue());
        $this->assertSame('Name', $sheet->getCell('B10')->getValue());
        $this->assertSame('Signature', $sheet->getCell('L10')->getValue());

        // First candidate row: name prefilled, gender F ticked (☑), others not.
        $this->assertSame(1, $sheet->getCell('A11')->getValue());
        $this->assertSame('Aisyah Putri', $sheet->getCell('B11')->getValue());
        $this->assertSame("\u{2611} F", $this->extractOption($sheet->getCell('C11')->getValue(), 'F'));
        $this->assertSame("\u{2610} M", $this->extractOption($sheet->getCell('C11')->getValue(), 'M'));
        $this->assertSame('081234567890', $sheet->getCell('K11')->getValue());

        // Ruled walk-in padding rows exist and carry the photo checkbox.
        $this->assertSame('☐ Y ☐ N', $sheet->getCell('J12')->getValue());
    }

    public function test_generates_at_least_ten_rows_for_walk_ins(): void
    {
        $res = $this->actingAs(User::factory()->create())
            ->getJson("/api/events/{$this->event->id}/attendance-sheet");

        $sheet = $this->loadSheet($res->streamedContent());

        // 10 numbered rows even with zero registrations (rows 11..20).
        $this->assertSame(10, $sheet->getCell('A20')->getValue());
        $this->assertSame('', (string) $sheet->getCell('B20')->getValue());
    }

    public function test_requires_authentication(): void
    {
        $this->getJson("/api/events/{$this->event->id}/attendance-sheet")->assertUnauthorized();
    }

    /** Pulls "☑ F" / "☐ M" out of a rendered checkbox line. */
    private function extractOption(string $line, string $letter): string
    {
        preg_match_all('/(\x{2611}|\x{2610}) '.preg_quote($letter, '/').'/u', $line, $m);

        return $m[0][0] ?? 'missing:'.$letter;
    }

    /** IOFactory::load() wants a file path, so park the bytes in a temp file. */
    private function loadSheet(string $bytes)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'att').'.xlsx';
        file_put_contents($tmp, $bytes);

        try {
            return IOFactory::load($tmp)->getSheetByNameOrThrow('Attendance List');
        } finally {
            @unlink($tmp);
        }
    }
}
