<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationAnswer;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Renders an event's candidate list as a styled Excel workbook.
 *
 * Two styles are available:
 *
 *  - style 'full'  — every column (code, email, telegram, timestamps,
 *    check-in code) plus one column per form question, in form order.
 *    Replaces the old CSV export.
 *
 *  - style 'contacts' — just No / Name / Email / Phone / Institution /
 *    Status, for sign-in sheets and quick phone lists.
 *
 * Both share the same visual language as the printable attendance sheet:
 * dark slate header band, banded rows, boxed borders, frozen header,
 * print-friendly A4 setup.
 */
class CandidateListExport
{
    private const COLOR_HEADER_FILL = 'FF334155';
    private const COLOR_WHITE = 'FFFFFFFF';
    private const COLOR_BAND = 'FFF1F5F9';
    private const COLOR_BORDER = 'FFCBD5E1';
    private const COLOR_JOINED = 'FF065F46';
    private const COLOR_REGISTERED = 'FF1E40AF';

    public function __construct(
        private readonly Event $event,
        private readonly string $style = 'full',
    ) {
        if (! in_array($this->style, ['full', 'contacts'], true)) {
            $this->style = 'full';
        }
    }

    /** Form questions in form order; fetched once per exporter instance. */
    private $questions;

    private function questions()
    {
        return $this->questions ??= $this->event->questions()->orderBy('order')->get();
    }

    public static function filename(Event $event, string $style): string
    {
        // e.g. candidates-EVT-2026-03-full-list.xlsx
        return 'candidates-'.Str::slug($event->event_code).'-'.($style === 'contacts' ? 'contacts' : 'full-list').'.xlsx';
    }

    public function save(string $path): void
    {
        (new Xlsx($this->generate()))->save($path);
    }

    public function generate(): Spreadsheet
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle($this->style === 'contacts' ? 'Contact List' : 'Candidate List');

        $headers = $this->headers();
        $this->writeTitle($sheet, count($headers));
        $headerRow = $this->writeHeader($sheet, $headers);

        $this->writeRows($sheet, $headers, $headerRow);

        // Column widths tuned per style.
        foreach ($this->widths() as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $lastRow = $sheet->getHighestRow();

        // Keep the header visible while scrolling long lists.
        $sheet->freezePane("A{$headerRow}1" === "A{$headerRow}1" ? 'A'.($headerRow + 1) : 'A2');

        // Print setup: portrait A4, header row repeats on every printed page.
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.4)->setRight(0.4);

        return $book;
    }

    // ------------------------------------------------------------------
    // Column definitions
    // ------------------------------------------------------------------

    /** Header labels in final column order. */
    private function headers(): array
    {
        if ($this->style === 'contacts') {
            return ['No', 'Name', 'Email', 'Phone', 'Institution', 'Status'];
        }

        $headers = [
            'No', 'Code', 'Name', 'Gender', 'Email', 'Phone', 'Telegram',
            'Institution', 'Role', 'Status', 'Registered at', 'Checked in at',
            'Check-in code',
        ];

        // One extra column per form question, in form order.
        foreach ($this->questions() as $q) {
            $headers[] = $q->question;
        }

        return $headers;
    }

    private function widths(): array
    {
        if ($this->style === 'contacts') {
            return ['A' => 6, 'B' => 28, 'C' => 30, 'D' => 16, 'E' => 24, 'F' => 12];
        }

        return [
            'A' => 6,   // No
            'B' => 10,  // Code
            'C' => 26,  // Name
            'D' => 10,  // Gender
            'E' => 28,  // Email
            'F' => 16,  // Phone
            'G' => 16,  // Telegram
            'H' => 22,  // Institution
            'I' => 16,  // Role
            'J' => 12,  // Status
            'K' => 19,  // Registered at
            'L' => 19,  // Checked in at
            'M' => 16,  // Check-in code
            // Form-answer columns keep PhpSpreadsheet's default width.
        ];
    }

    // ------------------------------------------------------------------
    // Rows
    // ------------------------------------------------------------------

    private function writeTitle($sheet, int $columnCount): void
    {
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);

        $sheet->mergeCells("A1:{$lastCol}1");
        $cell = $sheet->getCell('A1');
        $cell->setValue($this->event->title.' — '.($this->style === 'contacts' ? 'Contact List' : 'Candidate List'));
        $cell->getStyle()->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FF0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sub = $sheet->getCell('A2');
        $sub->setValue(implode('    ', array_filter([
            $this->event->event_code,
            $this->event->location,
            $this->event->start_date?->format('d/m/Y'),
            'Registered: '.$this->event->registrations()->count(),
        ])));
        $sub->getStyle()->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => 'FF475569']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(18);
    }

    private function writeHeader($sheet, array $headers): int
    {
        $row = 4; // rows 1-2 title, row 3 spacer

        foreach ($headers as $index => $title) {
            $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1).$row)->setValue($title);
        }

        $sheet->getStyle("A{$row}:".\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)).$row)
            ->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => self::COLOR_WHITE]],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => self::COLOR_HEADER_FILL],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
        $sheet->getRowDimension($row)->setRowHeight(24);

        return $row;
    }

    private function writeRows($sheet, array $headers, int $headerRow): void
    {
        $columnCount = count($headers);
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);

        $no = 0;
        $row = $headerRow;

        $this->event->registrations()
            ->with('candidate', 'answers')
            ->orderBy('registrations.id')
            ->chunk(500, function ($registrations) use ($sheet, &$no, &$row, $columnCount, $lastCol, $headerRow) {
                foreach ($registrations as $registration) {
                    $no++;
                    $row++;

                    $values = $this->style === 'contacts'
                        ? $this->contactRow($no, $registration)
                        : $this->fullRow($no, $registration);

                    foreach ($values as $index => $value) {
                        $cell = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1).$row);
                        // Strings are written as explicit text cells so that
                        //  - "=1+1" / "=HYPERLINK(...)" from user input can
                        //    never execute as a formula on open, and
                        //  - leading zeros survive (phone "0812…" must not
                        //    become the number 812…).
                        // Only the numeric No column stays a real number.
                        if (is_string($value)) {
                            $cell->setValueExplicit($value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        } else {
                            $cell->setValue($value);
                        }
                    }

                    // Banded rows for readability across wide tables.
                    if ($no % 2 === 0) {
                        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_BAND]],
                        ]);
                    }

                    // Status column gets the same colors as the web list.
                    $statusCol = $this->style === 'contacts' ? 'F' : 'J';
                    $joined = $registration->attendance_status === 'joined';
                    $sheet->getStyle("{$statusCol}{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => $joined ? self::COLOR_JOINED : self::COLOR_REGISTERED]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }
            });

        if ($row > $headerRow) {
            $sheet->getStyle("A{$headerRow}:{$lastCol}{$row}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_BORDER]]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
        } else {
            // No registrations yet: leave one friendly note instead of nothing.
            $sheet->getCell("A".($headerRow + 1))->setValue('No registrations yet.');
        }
    }

    private function fullRow(int $no, Registration $registration): array
    {
        $candidate = $registration->candidate;

        $answers = $registration->answers->mapWithKeys(
            fn (RegistrationAnswer $a) => [$a->question_id => $a->answer]
        );

        $row = [
            $no,
            $candidate->candidate_code,
            $candidate->name,
            (string) $candidate->gender,
            (string) $candidate->email,
            (string) $candidate->phone,
            (string) $candidate->telegram_username,
            (string) $candidate->institution,
            (string) $candidate->role,
            $registration->attendance_status,
            $registration->registered_at?->format('Y-m-d H:i') ?? '',
            $registration->joined_at?->format('Y-m-d H:i') ?? '',
            $registration->qr_token,
        ];

        foreach ($this->questions() as $q) {
            $row[] = (string) ($answers[$q->id] ?? '');
        }

        return $row;
    }

    private function contactRow(int $no, Registration $registration): array
    {
        $candidate = $registration->candidate;

        return [
            $no,
            $candidate->name,
            (string) $candidate->email,
            (string) $candidate->phone,
            (string) $candidate->institution,
            $registration->attendance_status,
        ];
    }
}
