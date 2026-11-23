<?php

namespace App\Support\Payroll;

use App\Enums\KaryawanStatus;
use App\Models\Karyawan;
use Carbon\CarbonInterface;
use DomainException;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class SalaryAdjustTemplateExporter
{
    private const SpreadsheetNamespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const TemplateArchive = 'templates/payroll/salary-adjust-os.zip';

    private const DirectorateTemplateArchive = 'templates/payroll/salary-adjust-directorate-os.zip';

    /**
     * @return array{path: string, filename: string}
     */
    public function exportDepartmentTemplates(?CarbonInterface $exportDate = null): array
    {
        $exportDate ??= now();
        $karyawansByDepartment = $this->activeKaryawansByDepartment();

        if ($karyawansByDepartment->isEmpty()) {
            throw new DomainException('Tidak ada karyawan aktif untuk template department.');
        }

        return $this->exportGroupedTemplateArchive(
            $karyawansByDepartment,
            'Template Form Salary Adjust by Directorate - Department For OS - '
                .$exportDate->format('Y-m-d').'.zip',
            self::TemplateArchive,
            $exportDate,
            function (Collection $karyawanGroup): array {
                $firstKaryawan = $karyawanGroup->first();
                $directorateName = (string) ($firstKaryawan?->directorate?->name ?? '');
                $departmentName = (string) ($firstKaryawan?->department?->name ?? '');
                $archiveDirectory = 'directorate_'.$this->archiveSafeName($directorateName);

                return [
                    'archive_path' => $archiveDirectory.'/'.$archiveDirectory
                        .'_department_'.$this->archiveSafeName($departmentName).'_OS.xlsx',
                    'title' => 'Data Karyawan OS Placement '.$directorateName
                        .' - Department '.$departmentName,
                ];
            },
        );
    }

    /**
     * @return array{path: string, filename: string}
     */
    public function exportDirectorateTemplates(?CarbonInterface $exportDate = null): array
    {
        $exportDate ??= now();
        $karyawansByDirectorate = $this->activeKaryawansByDirectorate();

        if ($karyawansByDirectorate->isEmpty()) {
            throw new DomainException('Tidak ada karyawan aktif untuk template directorate.');
        }

        return $this->exportGroupedTemplateArchive(
            $karyawansByDirectorate,
            'Template Form Salary Adjust by Directorate only For OS - '
                .$exportDate->format('Y-m-d').'.zip',
            self::DirectorateTemplateArchive,
            $exportDate,
            function (Collection $karyawanGroup): array {
                $directorateName = (string) ($karyawanGroup->first()?->directorate?->name ?? '');
                $archiveDirectory = 'Directorate_'.$this->archiveSafeName($directorateName);

                return [
                    'archive_path' => $archiveDirectory.'/'.$archiveDirectory.'_OS.xlsx',
                    'title' => 'Data Karyawan OS Directorate '.$directorateName,
                ];
            },
        );
    }

    /**
     * @param  Collection<string, Collection<int, Karyawan>>  $karyawanGroups
     * @param  callable(Collection<int, Karyawan>): array{archive_path: string, title: string}  $groupMetadata
     * @return array{path: string, filename: string}
     */
    private function exportGroupedTemplateArchive(
        Collection $karyawanGroups,
        string $filename,
        string $templateArchive,
        CarbonInterface $exportDate,
        callable $groupMetadata,
    ): array {
        $directory = storage_path('app/private/exports');
        File::ensureDirectoryExists($directory);

        $outputPath = $directory.DIRECTORY_SEPARATOR.$filename;
        $templateWorkbook = $this->templateStyleWorkbook($templateArchive);
        $output = new ZipArchive;

        if ($output->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the salary adjustment export archive.');
        }

        try {
            foreach ($karyawanGroups as $karyawanGroup) {
                $metadata = $groupMetadata($karyawanGroup);
                $workbook = $this->buildTemplateWorkbook(
                    $templateWorkbook,
                    $metadata['title'].' - '.$exportDate->format('d-m-Y H:i:s'),
                    $karyawanGroup,
                    $exportDate,
                );

                $output->addFromString($metadata['archive_path'], $workbook);
            }

            $output->close();
        } catch (\Throwable $exception) {
            $output->close();
            File::delete($outputPath);

            throw $exception;
        }

        return [
            'path' => $outputPath,
            'filename' => $filename,
        ];
    }

    /**
     * @return Collection<string, Collection<int, Karyawan>>
     */
    private function activeKaryawansByDirectorate(): Collection
    {
        return $this->activeKaryawanQuery()
            ->orderBy('directorate_id')
            ->orderBy('nip')
            ->get()
            ->groupBy('directorate_id');
    }

    /**
     * @return Collection<string, Collection<int, Karyawan>>
     */
    private function activeKaryawansByDepartment(): Collection
    {
        return $this->activeKaryawanQuery()
            ->orderBy('directorate_id')
            ->orderBy('department_id')
            ->orderBy('nip')
            ->get()
            ->groupBy(
                fn (Karyawan $karyawan): string => $karyawan->directorate_id
                    .'-'.$karyawan->department_id,
            );
    }

    private function activeKaryawanQuery(): Builder
    {
        return Karyawan::query()
            ->select([
                'id',
                'nip',
                'nama',
                'status_karyawan',
                'tanggal_bergabung',
                'tanggal_update',
                'directorate_id',
                'department_id',
                'jabatan_id',
                'job_grade_id',
                'gaji_pokok',
                'gaji_overtime',
                'bonus',
            ])
            ->with([
                'directorate:id,name',
                'department:id,name',
                'jabatan:id,name',
                'jobGrade:id,grade,grade_name',
            ])
            ->whereIn('status_karyawan', KaryawanStatus::valuesForFilter('active'));
    }

    private function templateStyleWorkbook(string $templateArchive): string
    {
        $templatePath = resource_path($templateArchive);

        if (! File::exists($templatePath)) {
            throw new RuntimeException('Salary adjustment template archive was not found.');
        }

        $archive = new ZipArchive;

        if ($archive->open($templatePath) !== true) {
            throw new RuntimeException('Unable to open the salary adjustment template archive.');
        }

        try {
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entryName = $archive->getNameIndex($index);

                if ($entryName === false || ! str_ends_with(mb_strtolower($entryName), '.xlsx')) {
                    continue;
                }

                $workbook = $archive->getFromIndex($index);

                if ($workbook === false) {
                    throw new RuntimeException("Unable to read template file [{$entryName}].");
                }

                return $workbook;
            }
        } finally {
            $archive->close();
        }

        throw new RuntimeException('The salary adjustment template archive has no workbook.');
    }

    /**
     * @param  Collection<int, Karyawan>  $karyawans
     */
    private function buildTemplateWorkbook(
        string $templateWorkbook,
        string $title,
        Collection $karyawans,
        CarbonInterface $exportDate,
    ): string {
        $workspace = storage_path('app/private/exports/tmp');
        File::ensureDirectoryExists($workspace);
        $templatePath = tempnam($workspace, 'directorate-template-source-');
        $targetPath = tempnam($workspace, 'directorate-template-target-');

        if ($templatePath === false || $targetPath === false) {
            throw new RuntimeException('Unable to create temporary directorate workbook files.');
        }

        try {
            File::put($templatePath, $templateWorkbook);

            $source = new ZipArchive;

            if ($source->open($templatePath) !== true) {
                throw new RuntimeException('Unable to open the directorate worksheet template.');
            }

            $sharedStringsXml = $source->getFromName('xl/sharedStrings.xml');
            $worksheetXml = $source->getFromName('xl/worksheets/sheet1.xml');

            if ($sharedStringsXml === false || $worksheetXml === false) {
                $source->close();

                throw new RuntimeException('The directorate worksheet template is incomplete.');
            }

            $stringTable = $this->sharedStringValues($sharedStringsXml);
            $stringTable = array_map(
                static fn (string $value): string => in_array(trim($value), ['ID', 'ID Karyawan', 'ID Employee', '工号'], true)
                    ? 'NIP'
                    : $value,
                $stringTable,
            );
            $worksheet = $this->loadXml($worksheetXml);
            $xpath = new DOMXPath($worksheet);
            $sheetData = $xpath->query(
                '/*[local-name()="worksheet"]/*[local-name()="sheetData"]',
            )->item(0);

            if (! $sheetData instanceof DOMElement) {
                $source->close();

                throw new RuntimeException('The directorate worksheet has no sheet data.');
            }

            $dataRowStyles = $this->dataRowStyles($xpath);
            $this->replaceTemplateHeaders(
                $worksheet,
                $xpath,
                $sheetData,
                $stringTable,
                $title,
                $exportDate,
            );

            $rowNumber = 6;

            foreach ($karyawans as $karyawan) {
                $this->appendKaryawanRow(
                    $worksheet,
                    $sheetData,
                    $karyawan,
                    $rowNumber,
                    $dataRowStyles,
                );
                $rowNumber++;
            }

            $dimension = $xpath->query(
                '/*[local-name()="worksheet"]/*[local-name()="dimension"]',
            )->item(0);

            if ($dimension instanceof DOMElement) {
                $dimension->setAttribute('ref', 'A1:N'.max(5, $rowNumber - 1));
            }

            $target = new ZipArchive;

            if ($target->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $source->close();

                throw new RuntimeException('Unable to create the directorate workbook.');
            }

            try {
                for ($index = 0; $index < $source->numFiles; $index++) {
                    $entryName = $source->getNameIndex($index);

                    if ($entryName === false || str_ends_with($entryName, '/')) {
                        continue;
                    }

                    $entryContents = match ($entryName) {
                        'xl/sharedStrings.xml' => $this->emptySharedStringsXml(),
                        'xl/worksheets/sheet1.xml' => $worksheet->saveXML() ?: $worksheetXml,
                        default => $source->getFromIndex($index),
                    };

                    if ($entryContents === false) {
                        throw new RuntimeException("Unable to read template file [{$entryName}].");
                    }

                    $target->addFromString($entryName, $entryContents);
                }
            } finally {
                $source->close();
                $target->close();
            }

            return File::get($targetPath);
        } finally {
            File::delete([$templatePath, $targetPath]);
        }
    }

    /**
     * @param  array<int, string>  $stringTable
     */
    private function replaceTemplateHeaders(
        DOMDocument $worksheet,
        DOMXPath $xpath,
        DOMElement $sheetData,
        array $stringTable,
        string $title,
        CarbonInterface $exportDate,
    ): void {
        $rows = [];

        foreach ($xpath->query('*[local-name()="row"]', $sheetData) as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            if ((int) $row->getAttribute('r') > 5) {
                $sheetData->removeChild($row);

                continue;
            }

            $rows[] = $row;
        }

        foreach ($rows as $row) {
            foreach ($xpath->query('*[local-name()="c"]', $row) as $cell) {
                if (! $cell instanceof DOMElement || $cell->getAttribute('t') !== 's') {
                    continue;
                }

                $valueNode = $this->firstChildElement($cell, 'v');
                $stringIndex = (int) ($valueNode?->textContent ?? -1);
                $this->setInlineString($worksheet, $cell, $stringTable[$stringIndex] ?? '');
            }
        }

        $date = "\u{65E5}\u{671F} / Tanggal: ".$exportDate->format('d-m-Y');
        $titleCell = $xpath->query('//*[local-name()="c"][@r="B1"]')->item(0);
        $dateCell = $xpath->query('//*[local-name()="c"][@r="B3"]')->item(0);

        if ($titleCell instanceof DOMElement) {
            $this->setInlineString($worksheet, $titleCell, $title);
        }

        if ($dateCell instanceof DOMElement) {
            $this->setInlineString($worksheet, $dateCell, $date);
        }
    }

    /**
     * @return array<int, int>
     */
    private function dataRowStyles(DOMXPath $xpath): array
    {
        $styles = [];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'];

        foreach ($columns as $index => $column) {
            $cell = $xpath->query('//*[local-name()="c"][@r="'.$column.'6"]')->item(0);
            $styles[$index] = $cell instanceof DOMElement
                ? (int) $cell->getAttribute('s')
                : 4;
        }

        return $styles;
    }

    /**
     * @param  array<int, int>  $styles
     */
    private function appendKaryawanRow(
        DOMDocument $worksheet,
        DOMElement $sheetData,
        Karyawan $karyawan,
        int $rowNumber,
        array $styles,
    ): void {
        $values = [
            $karyawan->nip,
            $karyawan->nama,
            $karyawan->department?->name ?? '',
            $karyawan->jabatan?->name ?? '',
            $karyawan->jobGrade?->grade ?? '',
            $karyawan->tanggal_bergabung?->format('d-M-Y') ?? '',
            $karyawan->tanggal_update?->format('d-M-Y') ?? '',
            '',
            $karyawan->gaji_pokok ?? 0,
            '',
            '',
            $karyawan->gaji_overtime ?? 0,
            '',
            $karyawan->bonus ?? 0,
        ];

        $row = $worksheet->createElementNS(self::SpreadsheetNamespace, 'row');
        $row->setAttribute('r', (string) $rowNumber);
        $row->setAttribute('spans', '1:14');

        foreach ($values as $index => $value) {
            $cell = $worksheet->createElementNS(self::SpreadsheetNamespace, 'c');
            $cell->setAttribute('r', $this->columnName($index + 1).$rowNumber);
            $cell->setAttribute('s', (string) $styles[$index]);

            if (is_int($value) || is_float($value)) {
                $valueNode = $worksheet->createElementNS(self::SpreadsheetNamespace, 'v');
                $valueNode->appendChild($worksheet->createTextNode((string) $value));
                $cell->appendChild($valueNode);
            } elseif (trim($value) !== '') {
                $this->setInlineString($worksheet, $cell, trim($value));
            }

            $row->appendChild($cell);
        }

        $sheetData->appendChild($row);
    }

    private function setInlineString(DOMDocument $document, DOMElement $cell, string $value): void
    {
        while ($cell->firstChild !== null) {
            $cell->removeChild($cell->firstChild);
        }

        if ($value === '') {
            $cell->removeAttribute('t');

            return;
        }

        $cell->setAttribute('t', 'inlineStr');
        $inlineString = $document->createElementNS(self::SpreadsheetNamespace, 'is');
        $text = $document->createElementNS(self::SpreadsheetNamespace, 't');
        $text->setAttributeNS(
            'http://www.w3.org/XML/1998/namespace',
            'xml:space',
            'preserve',
        );
        $text->appendChild($document->createTextNode($value));
        $inlineString->appendChild($text);
        $cell->appendChild($inlineString);
    }

    /**
     * @return array<int, string>
     */
    private function sharedStringValues(string $sharedStringsXml): array
    {
        $document = $this->loadXml($sharedStringsXml);
        $xpath = new DOMXPath($document);
        $values = [];

        foreach ($xpath->query('/*[local-name()="sst"]/*[local-name()="si"]') as $item) {
            $values[] = $item->textContent;
        }

        return $values;
    }

    private function emptySharedStringsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="'.self::SpreadsheetNamespace.'" count="0" uniqueCount="0"/>';
    }

    private function firstChildElement(DOMElement $element, string $localName): ?DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function archiveSafeName(string $name): string
    {
        $safeName = preg_replace('/[\/\\\\\x00-\x1F:*?"<>|]+/u', '-', $name);

        return trim($safeName ?? $name);
    }

    private function columnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function loadXml(string $xml): DOMDocument
    {
        $document = new DOMDocument;

        if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new RuntimeException('Unable to parse an Excel XML component.');
        }

        return $document;
    }
}
