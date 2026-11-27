<?php

namespace App\Support\Payroll;

use App\Enums\KaryawanStatus;
use App\Models\Karyawan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class KaryawanExcelKhususExporter
{
    private const Headers = [
        'NIP',
        'Nama Karyawan',
        'NIK/Passport',
        'Nomor Identitas',
        'Tempat Lahir',
        'Tanggal Lahir',
        'Jenis Kelamin',
        'Alamat ID',
        'Alamat Tinggal',
        'Email',
        'No HP',
        'Company',
        'Directorate',
        'Department',
        'Placement',
        'Jabatan',
        'Status',
        'Tanggal Bergabung',
        'Tanggal Resigned/Blacklist',
        'No Rekening',
    ];

    private const ColumnWidths = [
        12, 32, 18, 22, 18, 14, 16, 36, 36, 30, 18, 16, 28, 24, 24, 24,
        18, 18, 24, 22,
    ];

    /**
     * @param  iterable<int, Karyawan>  $karyawans
     */
    public function export(iterable $karyawans, string $title, ?string $filename = null): string
    {
        $directory = storage_path('app/private/exports');
        File::ensureDirectoryExists($directory);

        $filename = $this->safeFilename($filename ?? 'Data_Karyawan_Khusus_'.now()->format('Ymd_His').'.xlsx');
        $outputPath = $directory.DIRECTORY_SEPARATOR.$filename;
        $sheetPath = $outputPath.'.sheet.xml';

        try {
            $this->writeWorksheet($karyawans, $sheetPath, $title);
            $this->writeArchive($outputPath, $sheetPath);
        } finally {
            if (File::exists($sheetPath)) {
                File::delete($sheetPath);
            }
        }

        return $outputPath;
    }

    /**
     * @param  iterable<int, Karyawan>  $karyawans
     */
    private function writeWorksheet(iterable $karyawans, string $sheetPath, string $title): void
    {
        $handle = fopen($sheetPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Unable to create the Excel worksheet stream.');
        }

        fwrite($handle, $this->worksheetPrefix());
        fwrite($handle, $this->titleRow($title));
        fwrite($handle, $this->subtitleRow());
        fwrite($handle, $this->headerRow());

        $rowNumber = 5;

        foreach ($karyawans as $karyawan) {
            fwrite($handle, $this->dataRow($karyawan, $rowNumber));
            $rowNumber++;
        }

        fwrite($handle, $this->worksheetSuffix(max(4, $rowNumber - 1)));
        fclose($handle);
    }

    private function worksheetPrefix(): string
    {
        $columns = '';

        foreach (self::ColumnWidths as $index => $width) {
            $columnNumber = $index + 1;
            $columns .= '<col min="'.$columnNumber.'" max="'.$columnNumber.'" width="'.$width.'" customWidth="1"/>';
        }

        return <<<XML
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
                <sheetViews>
                    <sheetView tabSelected="1" showGridLines="0" workbookViewId="0">
                        <pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/>
                    </sheetView>
                </sheetViews>
                <sheetFormatPr defaultRowHeight="15"/>
                <cols>{$columns}</cols>
                <sheetData>
            XML;
    }

    private function worksheetSuffix(int $lastRow): string
    {
        $lastColumn = $this->columnName(count(self::Headers));

        return <<<XML
                </sheetData>
                <autoFilter ref="A4:{$lastColumn}{$lastRow}"/>
                <mergeCells count="2">
                    <mergeCell ref="A1:{$lastColumn}1"/>
                    <mergeCell ref="A2:{$lastColumn}2"/>
                </mergeCells>
                <pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>
            </worksheet>
            XML;
    }

    private function titleRow(string $title): string
    {
        return '<row r="1" ht="34" customHeight="1">'.$this->inlineStringCell('A1', $title, 1).'</row>';
    }

    private function subtitleRow(): string
    {
        $subtitle = 'Diekspor '.now()->translatedFormat('d F Y H:i').' · Data sesuai filter aktif';

        return '<row r="2" ht="22" customHeight="1">'.$this->inlineStringCell('A2', $subtitle, 2).'</row>';
    }

    private function headerRow(): string
    {
        $cells = '';

        foreach (self::Headers as $index => $header) {
            $cells .= $this->inlineStringCell($this->columnName($index + 1).'4', $header, 3);
        }

        return '<row r="4" ht="26" customHeight="1">'.$cells.'</row>';
    }

    private function dataRow(Karyawan $karyawan, int $rowNumber): string
    {
        $values = [
            $karyawan->nip,
            $karyawan->nama,
            $karyawan->jenis_identitas,
            $this->textValue($karyawan->no_identitas),
            $karyawan->tempat_lahir,
            $karyawan->tanggal_lahir?->format('d-m-Y'),
            $karyawan->gender,
            $karyawan->alamat_identitas,
            $karyawan->alamat_tinggal_sekarang,
            $karyawan->email,
            $karyawan->hp,
            $karyawan->company?->name,
            $karyawan->directorate?->name,
            $karyawan->department?->name,
            '-',
            $karyawan->jabatan?->name,
            $karyawan->status_karyawan?->value,
            $karyawan->tanggal_bergabung?->format('d-m-Y'),
            $this->exitDate($karyawan),
            $this->textValue($karyawan->nomor_rekening),
        ];

        $cells = '';

        foreach ($values as $index => $value) {
            $reference = $this->columnName($index + 1).$rowNumber;
            $style = 4;
            $cells .= is_int($value) || is_float($value)
                ? $this->numberCell($reference, $value, $style)
                : $this->inlineStringCell($reference, $this->stringValue($value), $style);
        }

        return '<row r="'.$rowNumber.'">'.$cells.'</row>';
    }

    private function textValue(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '-' : $value;
    }

    private function exitDate(Karyawan $karyawan): string
    {
        return match ($karyawan->status_karyawan) {
            KaryawanStatus::Resigned => $karyawan->tanggal_resigned?->format('d-m-Y') ?? '-',
            KaryawanStatus::Blacklist => $karyawan->tanggal_blacklist?->format('d-m-Y') ?? '-',
            default => '-',
        };
    }

    private function writeArchive(string $outputPath, string $sheetPath): void
    {
        $archive = new ZipArchive;

        if ($archive->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the Excel archive.');
        }

        $archive->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $archive->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $archive->addFromString('docProps/app.xml', $this->appPropertiesXml());
        $archive->addFromString('docProps/core.xml', $this->corePropertiesXml());
        $archive->addFromString('xl/workbook.xml', $this->workbookXml());
        $archive->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
        $archive->addFromString('xl/styles.xml', $this->stylesXml());
        $archive->addFile($sheetPath, 'xl/worksheets/sheet1.xml');
        $archive->close();
    }

    private function numberCell(string $reference, int|float $value, int $style): string
    {
        return '<c r="'.$reference.'" s="'.$style.'"><v>'.$value.'</v></c>';
    }

    private function inlineStringCell(string $reference, string $value, int $style): string
    {
        if ($value === '') {
            return '<c r="'.$reference.'" s="'.$style.'"/>';
        }

        return '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
            .$this->escape($value)
            .'</t></is></c>';
    }

    private function stringValue(mixed $value): string
    {
        return $value === null ? '' : trim((string) $value);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
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

    private function safeFilename(string $filename): string
    {
        $base = Str::of($filename)
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->trim('-')
            ->toString();

        return str_ends_with(mb_strtolower($base), '.xlsx')
            ? $base
            : $base.'.xlsx';
    }

    private function contentTypesXml(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
                <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
                <Default Extension="xml" ContentType="application/xml"/>
                <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
                <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
                <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
                <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
                <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
            </Types>
            XML;
    }

    private function rootRelationshipsXml(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
                <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
                <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
            </Relationships>
            XML;
    }

    private function workbookXml(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
                <sheets>
                    <sheet name="Karyawan Excel View" sheetId="1" r:id="rId1"/>
                </sheets>
            </workbook>
            XML;
    }

    private function workbookRelationshipsXml(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
                <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
            </Relationships>
            XML;
    }

    private function stylesXml(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
                <numFmts count="1">
                    <numFmt numFmtId="164" formatCode="#,##0"/>
                </numFmts>
                <fonts count="4">
                    <font><sz val="10"/><color rgb="FF334155"/><name val="Calibri"/><family val="2"/></font>
                    <font><b/><sz val="18"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>
                    <font><i/><sz val="10"/><color rgb="FF64748B"/><name val="Calibri"/><family val="2"/></font>
                    <font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>
                </fonts>
                <fills count="5">
                    <fill><patternFill patternType="none"/></fill>
                    <fill><patternFill patternType="gray125"/></fill>
                    <fill><patternFill patternType="solid"><fgColor rgb="FF334155"/><bgColor indexed="64"/></patternFill></fill>
                    <fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/><bgColor indexed="64"/></patternFill></fill>
                    <fill><patternFill patternType="solid"><fgColor rgb="FF64748B"/><bgColor indexed="64"/></patternFill></fill>
                </fills>
                <borders count="2">
                    <border><left/><right/><top/><bottom/><diagonal/></border>
                    <border>
                        <left style="thin"><color rgb="FFE2E8F0"/></left>
                        <right style="thin"><color rgb="FFE2E8F0"/></right>
                        <top style="thin"><color rgb="FFE2E8F0"/></top>
                        <bottom style="thin"><color rgb="FFE2E8F0"/></bottom>
                        <diagonal/>
                    </border>
                </borders>
                <cellStyleXfs count="1">
                    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
                </cellStyleXfs>
                <cellXfs count="6">
                    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
                    <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
                    <xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
                    <xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
                    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>
                    <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>
                </cellXfs>
                <cellStyles count="1">
                    <cellStyle name="Normal" xfId="0" builtinId="0"/>
                </cellStyles>
            </styleSheet>
            XML;
    }

    private function appPropertiesXml(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
                <Application>Payroll V2</Application>
            </Properties>
            XML;
    }

    private function corePropertiesXml(): string
    {
        $timestamp = now()->toAtomString();

        return <<<XML
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                <dc:creator>Payroll V2</dc:creator>
                <cp:lastModifiedBy>Payroll V2</cp:lastModifiedBy>
                <dcterms:created xsi:type="dcterms:W3CDTF">{$timestamp}</dcterms:created>
                <dcterms:modified xsi:type="dcterms:W3CDTF">{$timestamp}</dcterms:modified>
            </cp:coreProperties>
            XML;
    }
}
