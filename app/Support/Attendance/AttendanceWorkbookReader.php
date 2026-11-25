<?php

namespace App\Support\Attendance;

use Carbon\CarbonImmutable;
use DomainException;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;
use ZipArchive;

class AttendanceWorkbookReader
{
    private const RelationshipNamespace = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * @return array{
     *     attendance_date: CarbonImmutable,
     *     source_employee_count: int,
     *     skipped_without_punch_count: int,
     *     employees: list<array{
     *         source_user_id: string,
     *         name: string,
     *         department: string,
     *         punches: list<array{
     *             raw_value: string,
     *             punched_at: CarbonImmutable,
     *             is_next_day: bool,
     *             sequence: int
     *         }>
     *     }>
     * }
     */
    public function read(string $path): array
    {
        $archive = new ZipArchive;

        if ($archive->open($path, ZipArchive::RDONLY) !== true) {
            throw new DomainException('File Excel tidak dapat dibuka.');
        }

        try {
            $workbookXml = $archive->getFromName('xl/workbook.xml');
            $relationshipsXml = $archive->getFromName('xl/_rels/workbook.xml.rels');

            if ($workbookXml === false || $relationshipsXml === false) {
                throw new DomainException('Struktur workbook Excel tidak valid.');
            }

            $worksheetPath = $this->worksheetPath($workbookXml, $relationshipsXml);
            $worksheetXml = $archive->getFromName($worksheetPath);

            if ($worksheetXml === false) {
                throw new DomainException('Worksheet absensi tidak ditemukan.');
            }

            $sharedStrings = $this->sharedStrings($archive);
            $worksheet = $this->loadXml($worksheetXml);
            $xpath = new DOMXPath($worksheet);
            $attendanceDate = $this->attendanceDate($xpath, $sharedStrings);
            $blocks = [];
            $currentUserId = null;
            $rowNumber = 0;

            foreach ($xpath->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                if (! $row instanceof DOMElement) {
                    continue;
                }

                $rowNumber++;
                $values = $this->rowValues($xpath, $row, $sharedStrings);
                $sourceUserId = $this->normalizeUserId($values['A'] ?? '');
                $name = trim($values['B'] ?? '');

                if ($sourceUserId !== null && $name !== '' && Str::lower($name) !== 'name') {
                    $currentUserId = $sourceUserId;
                    $blocks[$currentUserId] ??= [
                        'source_user_id' => $currentUserId,
                        'name' => $name,
                        'department' => trim($values['C'] ?? ''),
                        'punches' => [],
                    ];
                }

                $rawPunch = trim($values['D'] ?? '');

                if ($currentUserId === null || $rawPunch === '') {
                    continue;
                }

                $punch = $this->punch($rawPunch, $attendanceDate, $rowNumber);

                if ($punch === null) {
                    continue;
                }

                $blocks[$currentUserId]['punches'][] = $punch;
            }
        } finally {
            $archive->close();
        }

        $employees = [];
        $skippedWithoutPunch = 0;

        foreach ($blocks as $block) {
            if ($block['punches'] === []) {
                $skippedWithoutPunch++;

                continue;
            }

            foreach ($block['punches'] as $index => &$punch) {
                $punch['sequence'] = $index + 1;
            }
            unset($punch);

            $employees[] = $block;
        }

        if ($employees === []) {
            throw new DomainException('File Excel tidak memiliki data karyawan dengan punch.');
        }

        return [
            'attendance_date' => $attendanceDate,
            'source_employee_count' => count($blocks),
            'skipped_without_punch_count' => $skippedWithoutPunch,
            'employees' => $employees,
        ];
    }

    private function worksheetPath(string $workbookXml, string $relationshipsXml): string
    {
        $workbook = $this->loadXml($workbookXml);
        $workbookXPath = new DOMXPath($workbook);
        $sheet = $workbookXPath->query('//*[local-name()="sheets"]/*[local-name()="sheet"]')->item(0);

        if (! $sheet instanceof DOMElement) {
            throw new DomainException('Workbook tidak memiliki worksheet.');
        }

        $relationshipId = $sheet->getAttributeNS(self::RelationshipNamespace, 'id');
        $relationships = $this->loadXml($relationshipsXml);
        $relationshipsXPath = new DOMXPath($relationships);

        foreach ($relationshipsXPath->query('//*[local-name()="Relationship"]') as $relationship) {
            if (! $relationship instanceof DOMElement || $relationship->getAttribute('Id') !== $relationshipId) {
                continue;
            }

            $target = ltrim($relationship->getAttribute('Target'), '/');

            return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
        }

        throw new DomainException('Worksheet utama tidak ditemukan.');
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $archive): array
    {
        $xml = $archive->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $document = $this->loadXml($xml);
        $xpath = new DOMXPath($document);
        $strings = [];

        foreach ($xpath->query('//*[local-name()="si"]') as $item) {
            $value = '';

            foreach ($xpath->query('.//*[local-name()="t"]', $item) as $text) {
                $value .= $text->textContent;
            }

            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    private function attendanceDate(DOMXPath $xpath, array $sharedStrings): CarbonImmutable
    {
        for ($rowNumber = 1; $rowNumber <= 10; $rowNumber++) {
            $row = $xpath->query('//*[local-name()="row"][@r="'.$rowNumber.'"]')->item(0);

            if (! $row instanceof DOMElement) {
                continue;
            }

            foreach ($this->rowValues($xpath, $row, $sharedStrings) as $value) {
                if (! preg_match('/Period\s*:\s*(\d{4}-\d{2}-\d{2})\s*~\s*(\d{4}-\d{2}-\d{2})/iu', $value, $matches)) {
                    continue;
                }

                if ($matches[1] !== $matches[2]) {
                    throw new DomainException('Import saat ini hanya mendukung rekap absensi satu hari.');
                }

                $date = CarbonImmutable::createFromFormat('Y-m-d', $matches[1], config('app.timezone'));

                if ($date === false) {
                    throw new DomainException('Tanggal absensi pada Excel tidak valid.');
                }

                return $date->startOfDay();
            }
        }

        throw new DomainException('Periode absensi tidak ditemukan pada header Excel.');
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return array<string, string>
     */
    private function rowValues(DOMXPath $xpath, DOMElement $row, array $sharedStrings): array
    {
        $values = [];

        foreach ($xpath->query('*[local-name()="c"]', $row) as $cell) {
            if (! $cell instanceof DOMElement) {
                continue;
            }

            $reference = $cell->getAttribute('r');
            $column = preg_match('/^[A-Z]+/', $reference, $matches) ? $matches[0] : '';

            if ($column === '') {
                continue;
            }

            $values[$column] = $this->cellValue($xpath, $cell, $sharedStrings);
        }

        return $values;
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    private function cellValue(DOMXPath $xpath, DOMElement $cell, array $sharedStrings): string
    {
        if ($cell->getAttribute('t') === 'inlineStr') {
            $inline = $xpath->query('*[local-name()="is"]', $cell)->item(0);

            if (! $inline instanceof DOMElement) {
                return '';
            }

            $value = '';

            foreach ($xpath->query('.//*[local-name()="t"]', $inline) as $text) {
                $value .= $text->textContent;
            }

            return $value;
        }

        $valueNode = $xpath->query('*[local-name()="v"]', $cell)->item(0);
        $value = $valueNode?->textContent ?? '';

        if ($cell->getAttribute('t') === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        return $value;
    }

    /**
     * @return array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null
     */
    private function punch(string $rawValue, CarbonImmutable $attendanceDate, int $rowNumber): ?array
    {
        if (preg_match('/^(\d{1,2}):(\d{2})\s*([AP]M)(\+)?$/i', $rawValue, $matches)) {
            $hour = ((int) $matches[1]) % 12;

            if (strtoupper($matches[3]) === 'PM') {
                $hour += 12;
            }

            $isNextDay = ($matches[4] ?? '') === '+';
            $punchedAt = $attendanceDate
                ->addDays($isNextDay ? 1 : 0)
                ->setTime($hour, (int) $matches[2]);

            return [
                'raw_value' => $rawValue,
                'punched_at' => $punchedAt,
                'is_next_day' => $isNextDay,
                'sequence' => 0,
            ];
        }

        if (is_numeric($rawValue)) {
            $serial = (float) $rawValue;
            $dayOffset = (int) floor($serial);
            $minuteOfDay = (int) round(($serial - $dayOffset) * 1440);

            if ($minuteOfDay >= 1440) {
                $dayOffset++;
                $minuteOfDay = 0;
            }

            $hour = intdiv($minuteOfDay, 60);
            $minute = $minuteOfDay % 60;

            return [
                'raw_value' => $rawValue,
                'punched_at' => $attendanceDate->addDays($dayOffset)->setTime($hour, $minute),
                'is_next_day' => $dayOffset > 0,
                'sequence' => 0,
            ];
        }

        throw new DomainException("Format punch pada baris {$rowNumber} tidak dikenali: {$rawValue}");
    }

    private function normalizeUserId(string $value): ?string
    {
        $value = trim($value);

        if ($value === '' || strcasecmp($value, 'User ID') === 0) {
            return null;
        }

        if (! preg_match('/^\d+(?:\.0+)?$/', $value)) {
            return null;
        }

        return ltrim(explode('.', $value, 2)[0], '0') ?: '0';
    }

    private function loadXml(string $xml): DOMDocument
    {
        $document = new DOMDocument;

        if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new DomainException('Isi workbook Excel tidak valid.');
        }

        return $document;
    }
}
