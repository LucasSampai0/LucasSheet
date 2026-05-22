<?php

namespace App\Exports;

use App\Services\WorkDuration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class WorkReportXlsx
{
    public function __construct(private array $report)
    {
    }

    public function downloadName(): string
    {
        return 'relatorio-horas-'.now()->format('Y-m-d-His').'.xlsx';
    }

    public function contents(): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('A extensao php-zip e necessaria para exportar XLSX.');
        }

        $path = $this->temporaryPath();
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Nao foi possivel criar o arquivo XLSX temporario.');
        }

        $sheets = [
            'Tarefas' => $this->recordsRows(),
            'Resumo por Cliente' => $this->summaryRows($this->report['clients']),
            'Resumo por Projeto' => $this->summaryRows($this->report['projects']),
            'Resumo por Categoria' => $this->summaryRows($this->report['categories']),
        ];

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml(count($sheets)));
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml(array_keys($sheets)));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml(count($sheets)));
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        foreach (array_values($sheets) as $index => $rows) {
            $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->sheetXml($rows));
        }

        $zip->close();
        $contents = file_get_contents($path);
        @unlink($path);

        return $contents === false ? '' : $contents;
    }

    private function temporaryPath(): string
    {
        $directory = storage_path('app/private/reports/tmp');

        File::ensureDirectoryExists($directory, 0775, true);

        if (! is_writable($directory)) {
            throw new RuntimeException('O diretorio temporario de relatorios nao tem permissao de escrita: '.$directory);
        }

        $path = tempnam($directory, 'work-report-');

        if ($path === false) {
            throw new RuntimeException('Nao foi possivel criar o arquivo temporario do relatorio.');
        }

        return $path;
    }

    private function recordsRows(): array
    {
        $rows = [[
            'Data',
            'Inicio',
            'Fim',
            'Duracao',
            'Cliente',
            'Projeto',
            'Categoria',
            'Titulo',
            'Descricao',
        ]];

        foreach ($this->report['records'] as $record) {
            $rows[] = [
                optional($record->work_date)->format('d/m/Y'),
                substr((string) $record->started_at, 0, 5),
                $record->ended_at ? substr((string) $record->ended_at, 0, 5) : '',
                WorkDuration::formatMinutes($record->duration_minutes),
                $record->client->name,
                optional($record->project)->name ?? '',
                optional($record->category)->name ?? '',
                $record->title,
                $record->description ?? '',
            ];
        }

        return $rows;
    }

    private function summaryRows(Collection $summary): array
    {
        $rows = [['Nome', 'Total em minutos', 'Total HH:MM']];

        foreach ($summary as $item) {
            $rows[] = [$item['name'], $item['minutes'], $item['formatted']];
        }

        return $rows;
    }

    private function sheetXml(array $rows): string
    {
        $xml = ['<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'];
        $xml[] = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $xml[] = '<row r="'.($rowIndex + 1).'">';

            foreach (array_values($row) as $columnIndex => $value) {
                $cell = $this->cellName($columnIndex, $rowIndex + 1);
                $style = $rowIndex === 0 ? ' s="1"' : '';

                if (is_numeric($value) && $rowIndex !== 0) {
                    $xml[] = '<c r="'.$cell.'"'.$style.'><v>'.$value.'</v></c>';
                } else {
                    $xml[] = '<c r="'.$cell.'" t="inlineStr"'.$style.'><is><t>'.$this->escape((string) $value).'</t></is></c>';
                }
            }

            $xml[] = '</row>';
        }

        $xml[] = '</sheetData></worksheet>';

        return implode('', $xml);
    }

    private function cellName(int $columnIndex, int $row): string
    {
        $name = '';
        $column = $columnIndex + 1;

        while ($column > 0) {
            $mod = ($column - 1) % 26;
            $name = chr(65 + $mod).$name;
            $column = intdiv($column - $mod, 26);
        }

        return $name.$row;
    }

    private function contentTypesXml(int $sheetCount): string
    {
        $sheets = '';

        for ($i = 1; $i <= $sheetCount; $i++) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$sheets
            .'</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(array $sheetNames): string
    {
        $sheets = '';

        foreach ($sheetNames as $index => $name) {
            $sheetId = $index + 1;
            $sheets .= '<sheet name="'.$this->escape($name).'" sheetId="'.$sheetId.'" r:id="rId'.$sheetId.'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets></workbook>';
    }

    private function workbookRelsXml(int $sheetCount): string
    {
        $rels = '';

        for ($i = 1; $i <= $sheetCount; $i++) {
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }

        $rels .= '<Relationship Id="rId'.($sheetCount + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            .'<cellXfs count="2"><xf fontId="0" fillId="0" borderId="0"/><xf fontId="1" fillId="0" borderId="0" applyFont="1"/></cellXfs>'
            .'</styleSheet>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
