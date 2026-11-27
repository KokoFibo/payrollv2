<?php

namespace App\Support\Attendance;

use Carbon\CarbonImmutable;
use DomainException;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;
use ZipArchive;

class SpecialWorkScheduleWorkbookReader
{
    private const RelationshipNamespace = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const HeaderAliases = [
        'name' => ['namaperiode', 'periode'],
        'starts_on' => ['tanggalmulai', 'mulai'],
        'ends_on' => ['tanggalselesai', 'selesai'],
        'schedule_profile' => ['profile', 'profil'],
        'day_type' => ['tipehari', 'harikerja'],
        'shift' => ['shift'],
        'check_in' => ['jammasuk', 'masuk'],
        'check_out' => ['jamkeluar', 'keluar'],
        'check_out_day_offset' => ['akhirshift'],
        'standard_work_hours' => ['jamkerja', 'jamkerjareguler'],
        'expected_punch_count' => ['jumlahpunch', 'punch'],
        'company_name' => ['company', 'perusahaan'],
        'notes' => ['catatan', 'keterangan'],
    ];

    /**
     * @return list<array{
     *     row_number: int,
     *     name: string,
     *     starts_on: string,
     *     ends_on: string,
     *     schedule_profile: string,
     *     day_type: string,
     *     shift: string,
     *     check_in: string,
     *     check_out: string,
     *     check_out_day_offset: string,
     *     standard_work_hours: string,
     *     expected_punch_count: string,
     *     company_name: string,
     *     notes: string
     * }>
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
                throw new DomainException('Tab Jadwal Kerja tidak ditemukan pada workbook.');
            }

            $sharedStrings = $this->sharedStrings($archive);
            $worksheet = $this->loadXml($worksheetXml);
            $xpath = new DOMXPath($worksheet);
            $rows = [];
            $headerMap = null;

            foreach ($xpath->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                if (! $row instanceof DOMElement) {
                    continue;
                }

                $rowNumber = (int) $row->getAttribute('r');
                $values = $this->rowValues($xpath, $row, $sharedStrings);

                if ($headerMap === null) {
                    $candidateHeaders = $this->headerMap($values);

                    if ($this->hasRequiredHeaders($candidateHeaders)) {
                        $headerMap = $candidateHeaders;
                    }

                    continue;
                }

                $schedule = $this->scheduleRow($values, $headerMap, $rowNumber);

                if ($schedule !== null) {
                    $rows[] = $schedule;
                }

                if (count($rows) > 1000) {
                    throw new DomainException('File jadwal melebihi batas 1.000 baris.');
                }
            }
        } finally {
            $archive->close();
        }

        if ($headerMap === null) {
            throw new DomainException('Bagian Jadwal Perubahan / Ramadan pada tab Jadwal Kerja tidak ditemukan.');
        }

        if ($rows === []) {
            throw new DomainException('Bagian Jadwal Perubahan / Ramadan pada tab Jadwal Kerja belum memiliki data.');
        }

        return $rows;
    }

    private function worksheetPath(string $workbookXml, string $relationshipsXml): string
    {
        $workbook = $this->loadXml($workbookXml);
        $workbookXPath = new DOMXPath($workbook);
        $sheet = null;

        foreach ($workbookXPath->query('//*[local-name()="sheets"]/*[local-name()="sheet"]') as $candidate) {
            if (! $candidate instanceof DOMElement) {
                continue;
            }

            if (Str::lower(trim($candidate->getAttribute('name'))) === 'jadwal kerja') {
                $sheet = $candidate;

                break;
            }
        }

        if (! $sheet instanceof DOMElement) {
            throw new DomainException('Tab Jadwal Kerja tidak ditemukan pada workbook.');
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

        throw new DomainException('Worksheet Jadwal Kerja tidak ditemukan.');
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

            return trim($value);
        }

        $valueNode = $xpath->query('*[local-name()="v"]', $cell)->item(0);
        $value = $valueNode?->textContent ?? '';

        if ($cell->getAttribute('t') === 's') {
            return trim($sharedStrings[(int) $value] ?? '');
        }

        return trim($value);
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private function headerMap(array $values): array
    {
        $normalizedValues = [];

        foreach ($values as $column => $value) {
            $normalizedValues[$column] = $this->normalizeHeader($value);
        }

        $headerMap = [];

        foreach (self::HeaderAliases as $key => $aliases) {
            foreach ($normalizedValues as $column => $normalizedHeader) {
                if (in_array($normalizedHeader, $aliases, true)) {
                    $headerMap[$key] = $column;

                    break;
                }
            }
        }

        return $headerMap;
    }

    /**
     * @param  array<string, string>  $headerMap
     */
    private function hasRequiredHeaders(array $headerMap): bool
    {
        foreach ([
            'name',
            'starts_on',
            'ends_on',
            'schedule_profile',
            'shift',
            'check_in',
            'check_out',
            'standard_work_hours',
            'expected_punch_count',
        ] as $requiredHeader) {
            if (! isset($headerMap[$requiredHeader])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<string, string>  $headerMap
     * @return array{
     *     row_number: int,
     *     name: string,
     *     starts_on: string,
     *     ends_on: string,
     *     schedule_profile: string,
     *     day_type: string,
     *     shift: string,
     *     check_in: string,
     *     check_out: string,
     *     check_out_day_offset: string,
     *     standard_work_hours: string,
     *     expected_punch_count: string,
     *     company_name: string,
     *     notes: string
     * }|null
     */
    private function scheduleRow(array $values, array $headerMap, int $rowNumber): ?array
    {
        $value = fn (string $key): string => trim(
            $values[$headerMap[$key] ?? ''] ?? '',
        );
        $name = $value('name');
        $startsOn = $value('starts_on');
        $endsOn = $value('ends_on');

        if ($name === '' && $startsOn === '' && $endsOn === '') {
            return null;
        }

        return [
            'row_number' => $rowNumber,
            'name' => $name,
            'starts_on' => $this->dateValue($startsOn),
            'ends_on' => $this->dateValue($endsOn),
            'schedule_profile' => $value('schedule_profile'),
            'day_type' => $value('day_type'),
            'shift' => $value('shift'),
            'check_in' => $this->timeValue($value('check_in')),
            'check_out' => $this->timeValue($value('check_out')),
            'check_out_day_offset' => $value('check_out_day_offset'),
            'standard_work_hours' => str_replace(',', '.', $value('standard_work_hours')),
            'expected_punch_count' => $value('expected_punch_count'),
            'company_name' => $value('company_name'),
            'notes' => $value('notes'),
        ];
    }

    private function normalizeHeader(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($value))) ?? '';
    }

    private function dateValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (is_numeric($value)) {
            return CarbonImmutable::create(1899, 12, 30, 0, 0, 0, config('app.timezone'))
                ->addDays((int) floor((float) $value))
                ->toDateString();
        }

        return $value;
    }

    private function timeValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (is_numeric($value)) {
            $minutes = (int) round(fmod((float) $value, 1.0) * 1440) % 1440;

            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        if (preg_match('/^(\d{1,2})[:.](\d{2})/', $value, $matches)) {
            return sprintf('%02d:%s', (int) $matches[1], $matches[2]);
        }

        try {
            return CarbonImmutable::parse($value)->format('H:i');
        } catch (\Throwable) {
            return $value;
        }
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
