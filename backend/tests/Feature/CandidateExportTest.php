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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class CandidateExportTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private array $headers = [
        'No', 'Code', 'Name', 'Gender', 'Email', 'Phone', 'Telegram',
        'Institution', 'Role', 'Status', 'Registered at', 'Checked in at',
        'Check-in code',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'event_code' => 'EVT-EXP-1',
            'title' => 'Export test',
            'status' => 'open',
            'registration_token' => strtoupper(Str::random(10)),
        ]);

        FormQuestion::create([
            'event_id' => $this->event->id,
            'question' => 'Dietary requirement',
            'type' => 'text',
            'required' => false,
            'order' => 1,
        ]);
        FormQuestion::create([
            'event_id' => $this->event->id,
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
            'event_id' => $this->event->id,
            'candidate_id' => $candidate->id,
            'qr_token' => 'REG-TEST-0001',
            'registered_at' => now(),
            'attendance_status' => 'joined',
            'joined_at' => now(),
        ]);
        RegistrationAnswer::create([
            'registration_id' => $registration->id,
            'question_id' => FormQuestion::where('event_id', $this->event->id)->orderBy('order')->skip(0)->first()->id,
            'answer' => 'Vegetarian',
        ]);
        RegistrationAnswer::create([
            'registration_id' => $registration->id,
            'question_id' => FormQuestion::where('event_id', $this->event->id)->orderBy('order')->skip(1)->first()->id,
            'answer' => 'M',
        ]);
    }

    private function download(string $url): array
    {
        $res = $this->actingAs(User::factory()->create())->getJson($url);
        $res->assertOk();

        // Parse the real workbook — assertions run against cells, not bytes.
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $res->streamedContent());
        $book = IOFactory::load($tmp);
        unlink($tmp);

        return [$book->getSheet(0), $res];
    }

    public function test_full_list_export_contains_static_columns_answers_and_leading_zeros(): void
    {
        [$sheet, $res] = $this->download("/api/events/{$this->event->id}/candidates/export");

        $res->headers->set('X-Test-Check-Content-Type', $res->headers->get('Content-Type'));
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $res->headers->get('Content-Type'),
        );
        $this->assertMatchesRegularExpression(
            '/attachment; filename=candidates-evt-exp-1-full-list\.xlsx/',
            (string) $res->headers->get('Content-Disposition'),
        );

        // Title block occupies rows 1-2, header band is row 4.
        $headerRow = 4;
        foreach ($this->headers as $i => $title) {
            $this->assertSame($title, $sheet->getCell([$i + 1, $headerRow])->getValue(), "header column {$i}");
        }
        // Form questions appended after the static columns, in form order.
        $this->assertSame('Dietary requirement', $sheet->getCell([14, $headerRow])->getValue());
        $this->assertSame('T-shirt size', $sheet->getCell([15, $headerRow])->getValue());

        // Data row.
        $dataRow = $headerRow + 1;
        $this->assertSame(1, $sheet->getCell([1, $dataRow])->getValue()); // numeric No
        $this->assertSame('C-0001', $sheet->getCell([2, $dataRow])->getValue());
        $this->assertSame('Aisyah Putri', $sheet->getCell([3, $dataRow])->getValue());
        $this->assertSame('aisyah@example.com', $sheet->getCell([5, $dataRow])->getValue());
        // Phone keeps its leading zero: written as an explicit text cell.
        $this->assertSame('081234567890', $sheet->getCell([6, $dataRow])->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell([6, $dataRow])->getDataType());
        $this->assertSame('joined', $sheet->getCell([10, $dataRow])->getValue());
        $this->assertSame('REG-TEST-0001', $sheet->getCell([13, $dataRow])->getValue());
        $this->assertSame('Vegetarian', $sheet->getCell([14, $dataRow])->getValue());
        $this->assertSame('M', $sheet->getCell([15, $dataRow])->getValue());
    }

    public function test_contact_list_style_has_only_the_six_contact_columns(): void
    {
        [$sheet] = $this->download("/api/events/{$this->event->id}/candidates/export?style=contacts");

        $headerRow = 4;
        $this->assertSame(
            ['No', 'Name', 'Email', 'Phone', 'Institution', 'Status'],
            array_map(
                fn ($i) => $sheet->getCell([$i, $headerRow])->getValue(),
                range(1, 6),
            ),
        );
        $this->assertSame('Aisyah Putri', $sheet->getCell([2, $headerRow + 1])->getValue());
        $this->assertSame('081234567890', $sheet->getCell([4, $headerRow + 1])->getValue());
        // No form-answer columns in this style.
        $this->assertSame(null, $sheet->getCell([7, $headerRow])->getValue());
    }

    public function test_export_neutralizes_formula_injection_as_text_cells(): void
    {
        $qCmd = FormQuestion::create([
            'event_id' => $this->event->id,
            'question' => 'Command, with comma',
            'type' => 'text',
            'required' => false,
            'order' => 3,
        ]);

        $candidate = Candidate::create([
            'candidate_code' => 'C-0002',
            // "=HYPERLINK(...)" must become a plain text cell so it cannot
            // execute when a manager opens the workbook in Excel.
            'name' => '=HYPERLINK("http://evil.example","click")',
        ]);
        $registration = Registration::create([
            'event_id' => $this->event->id,
            'candidate_id' => $candidate->id,
            'qr_token' => 'REG-TEST-0002',
        ]);
        RegistrationAnswer::create([
            'registration_id' => $registration->id,
            'question_id' => $qCmd->id,
            'answer' => '=1+1',
        ]);

        [$sheet] = $this->download("/api/events/{$this->event->id}/candidates/export");

        // The injected candidate is the SECOND registration → row 6
        // (row 4 header, row 5 the setUp candidate). With 3 questions the
        // "Command, with comma" answers live in column 16.
        $nameCell = $sheet->getCell([3, 6]);
        $this->assertSame('=HYPERLINK("http://evil.example","click")', $nameCell->getValue());
        $this->assertSame(DataType::TYPE_STRING, $nameCell->getDataType(), 'name must be a text cell, not a formula');

        $answerCell = $sheet->getCell([16, 6]);
        $this->assertSame('=1+1', $answerCell->getValue());
        $this->assertSame(DataType::TYPE_STRING, $answerCell->getDataType(), 'answer must be a text cell, not a formula');
    }

    public function test_export_requires_authentication(): void
    {
        $this->getJson("/api/events/{$this->event->id}/candidates/export")->assertUnauthorized();
    }

    public function test_empty_event_exports_header_only_with_a_note(): void
    {
        $empty = Event::create([
            'event_code' => 'EVT-EXP-4',
            'title' => 'Empty event',
            'status' => 'open',
            'registration_token' => strtoupper(Str::random(10)),
        ]);

        [$sheet] = $this->download("/api/events/{$empty->id}/candidates/export");

        $this->assertSame('No', $sheet->getCell([1, 4])->getValue());
        $this->assertSame('No registrations yet.', $sheet->getCell([1, 5])->getValue());
    }
}
