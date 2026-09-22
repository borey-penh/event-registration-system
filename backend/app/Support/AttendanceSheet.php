<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Renders an event's candidate list as a printable "Attendance List"
 * worksheet (see the organizer's Excel template):
 *
 *   [logo]                                        Attendance List
 *   Activities: <event title>
 *   Location:  <event location>
 *   Date:      <event start date>
 *   Gender:    M: Male   F: Female   P: Prefer not to say
 *   Age:       K: under 18  Y: 18-29  A: 30-60   E: Above 60
 *   Disability: C: ...  H: ...  M: ...  R: ...  S: ...  X: ...
 *   | No. | Name | Gender | Age | Disability | Ethnicity | Institution |
 *   | Business Entity | Position | Photo request and Using | TEL | Signature |
 *
 * The Gender/Age/Disability columns carry printed checkbox squares (☐ M,
 * ☐ F, …) so staff can tick them by hand on the printed sheet; answers the
 * candidate already supplied in the form are ticked (☑) automatically.
 */
class AttendanceSheet
{
    private const COLOR_HEADER_FILL = 'FF334155';
    private const COLOR_WHITE = 'FFFFFFFF';
    private const COLOR_INFO_LABEL = 'FF0F766E';

    private Spreadsheet $book;

    private int $row = 1;

    public function __construct(
        private readonly Event $event,
    ) {}

    public static function filename(Event $event): string
    {
        return 'attendance-'.Str::slug($event->event_code).'.xlsx';
    }

    public function generate(): Spreadsheet
    {
        $this->book = new Spreadsheet();
        $sheet = $this->book->getActiveSheet();
        $sheet->setTitle('Attendance List');
        $sheet->setShowGridLines(false);

        $this->openHeader($sheet);
        $this->writeInfoBlock($sheet);
        $this->writeLegendBlock($sheet);
        $this->writeTable($sheet);

        // Landscape A4 print setup — this sheet exists to be printed.
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.3);

        return $this->book;
    }

    public function save(string $path): void
    {
        (new Xlsx($this->generate()))->save($path);
    }

    // ------------------------------------------------------------------
    // Title + logo
    // ------------------------------------------------------------------

    private function openHeader($sheet): void
    {
        $logo = storage_path('app/branding/llc-logo.png');
        if (is_file($logo)) {
            $drawing = new Drawing();
            $drawing->setPath($logo);
            $drawing->setHeight(60);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(2);
            $drawing->setOffsetY(2);
            $drawing->setWorksheet($sheet);
        }

        // Widen col A to fit the logo, keep the rest tight until the table.
        $sheet->getColumnDimension('A')->setWidth(14);

        $sheet->mergeCells('H1:L1');
        $cell = $sheet->getCell('H1');
        $cell->setValue('Attendance List');
        $cell->getStyle()->applyFromArray([
            'font' => ['bold' => true, 'size' => 22, 'color' => ['rgb' => 'FF111111']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(46);

        $this->row = 2;
    }

    // ------------------------------------------------------------------
    // Activities / Location / Date
    // ------------------------------------------------------------------

    private function writeInfoBlock($sheet): void
    {
        $rows = [
            ['Activities:', $this->event->title],
            ['Location:', $this->event->location ?: '—'],
            ['Date:', $this->event->start_date?->format('d/m/Y') ?? '—'],
        ];

        foreach ($rows as [$label, $value]) {
            $sheet->setCellValue("A{$this->row}", $label);
            $sheet->getStyle("A{$this->row}")->getFont()->applyFromArray([
                'bold' => true,
                'color' => ['rgb' => self::COLOR_INFO_LABEL],
            ]);
            $sheet->setCellValue("B{$this->row}", $value);

            $this->row++;
        }

        $this->row++; // blank spacer
    }

    // ------------------------------------------------------------------
    // Gender / Age / Disability legends (printed reference)
    // ------------------------------------------------------------------

    private function writeLegendBlock($sheet): void
    {
        $legends = [
            ['Gender:', 'M: Male    F: Female    P: Prefer not to say'],
            ['Age:', 'K: under 18    Y: 18-29    A: 30-60    E: Above 60'],
            ['Disability:', 'C: Difficulty Seeing,   H: Difficulty Hearing,   M: Difficulty moving,   '.
                'R: Difficulty Remember,   S: Difficulty with Self Care,   X: Difficulty communication'],
        ];

        foreach ($legends as [$label, $value]) {
            $sheet->setCellValue("A{$this->row}", $label);
            $sheet->getStyle("A{$this->row}")->getFont()->applyFromArray([
                'bold' => true,
                'italic' => true,
            ]);
            $sheet->setCellValue("B{$this->row}", $value);

            $this->row++;
        }

        $this->row++;
    }

    // ------------------------------------------------------------------
    // The candidates table
    // ------------------------------------------------------------------

    private function writeTable($sheet): void
    {
        $headerRow = $this->row;

        // Header cells (one per column, A..K) — styled as a block afterwards.
        $headers = [
            'A' => 'No.',
            'B' => 'Name',
            'C' => 'Gender',
            'D' => 'Age',
            'E' => 'Disability',
            'F' => 'Ethnicity',
            'G' => 'Institution',
            'H' => 'Business Entity',
            'I' => 'Position',
            'J' => 'Photo request and Using',
            'K' => 'TEL',
            'L' => 'Signature',
        ];
        foreach ($headers as $col => $title) {
            $sheet->setCellValue("{$col}{$headerRow}", $title);
        }

        $sheet->getStyle("A{$headerRow}:L{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => self::COLOR_WHITE]],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::COLOR_HEADER_FILL],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(30);

        $this->row = $headerRow + 1;

        $registrations = $this->event->registrations()
            ->with('candidate', 'answers')
            ->orderBy('registrations.id')
            ->get();

        $no = 0;
        foreach ($registrations as $registration) {
            $this->writeCandidateRow($sheet, ++$no, $registration);
        }

        // Print padding: at least 10 blank ruled rows so the sheet can also
        // be used for walk-ins on paper.
        for ($i = 0; $i < max(0, 10 - $registrations->count()); $i++) {
            $this->writeCandidateRow($sheet, ++$no, null);
        }

        $lastRow = $this->row - 1;

        // Column widths tuned for landscape A4. (Col A stays at the width set
        // in openHeader so the logo never overlaps the info block.)
        foreach ([
            'B' => 26,  // Name
            'C' => 9,   // Gender
            'D' => 7,   // Age
            'E' => 15,  // Disability
            'F' => 12,  // Ethnicity
            'G' => 20,  // Institution
            'H' => 16,  // Business Entity
            'I' => 14,  // Position
            'J' => 13,  // Photo request
            'K' => 13,  // TEL
            'L' => 14,  // Signature
        ] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Boxed borders for the whole table incl. header.
        $sheet->getStyle("A{$headerRow}:L{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FF0F172A']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Checkbox cells get tall rows so ☐ options stack readably.
        $sheet->getStyle("A{$headerRow}:L{$lastRow}")
            ->getAlignment()->setWrapText(true);

        $this->row = $lastRow + 1;
    }

    private function writeCandidateRow($sheet, int $no, ?Registration $registration): void
    {
        $candidate = $registration?->candidate;

        $answers = $registration
            ? $registration->answers->mapWithKeys(fn ($a) => [$a->question_id => $a->answer])
            : collect();

        $r = $this->row;

        $sheet->getCell("A{$r}")->setValue($no);
        $sheet->getCell("B{$r}")->setValue($candidate?->name ?? '');
        $sheet->getCell("C{$r}")->setValue($this->genderCell($answers));
        $sheet->getCell("D{$r}")->setValue($this->ageCell($answers));
        $sheet->getCell("E{$r}")->setValue($this->disabilityCell($answers));

        // Free-form columns are prefilled from matching answers when present.
        $sheet->getCell("F{$r}")->setValue($candidate?->role ?? '');
        $sheet->getCell("G{$r}")->setValue($candidate?->institution ?? '');
        $sheet->getCell("H{$r}")->setValue('');
        $sheet->getCell("I{$r}")->setValue('');
        $sheet->getCell("J{$r}")->setValue('☐ Y ☐ N');
        $sheet->getCell("K{$r}")->setValue($candidate?->phone ?? '');

        $sheet->getRowDimension($r)->setRowHeight(46);

        $this->row++;
    }

    // ------------------------------------------------------------------
    // Checkbox helpers
    // ------------------------------------------------------------------

    private static function checkboxLine(string $letters, ?string $picked): string
    {
        $picked = $picked === null ? [] : array_map('trim', explode(',', $picked));

        $cells = [];
        foreach (preg_split('//u', $letters, -1, PREG_SPLIT_NO_EMPTY) as $letter) {
            $cells[] = (in_array($letter, $picked, true) ? "\u{2611}" : "\u{2610}").' '.$letter;
        }

        return implode('   ', $cells);
    }

    private function genderCell($answers): string
    {
        return self::checkboxLine('MFP', $answers->get($this->questionIdByKeyword('gender')));
    }

    private function ageCell($answers): string
    {
        return self::checkboxLine('KYAE', $answers->get($this->questionIdByKeyword('age')));
    }

    private function disabilityCell($answers): string
    {
        return self::checkboxLine('CHMRSX', $answers->get($this->questionIdByKeyword('disab')));
    }

    /**
     * The event form is manager-defined, so we locate the matching question
     * by keyword instead of a hard-coded id.
     */
    private function questionIdByKeyword(string $needle): ?int
    {
        static $cache = [];

        if (! array_key_exists($needle, $cache)) {
            $cache[$needle] = $this->event->questions
                ->first(fn ($q) => Str::contains(Str::lower($q->question), $needle))
                ?->id;
        }

        return $cache[$needle];
    }
}
