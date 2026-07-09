<?php
declare(strict_types=1);

namespace App\Shared\Analytics;

class ExportEngine
{
    public function toCsv(array $rows, array $colonnes, string $separateur = ';'): string
    {
        $lines   = [];
        $headers = array_map(fn($c) => $this->escape($c['label'] ?? $c['key']), $colonnes);
        $lines[] = implode($separateur, $headers);
        foreach ($rows as $row) {
            $cells = [];
            foreach ($colonnes as $col) {
                $cells[] = $this->escape((string)($row[$col['key']] ?? ''));
            }
            $lines[] = implode($separateur, $cells);
        }
        return "\xEF\xBB\xBF" . implode("\n", $lines);
    }

    public function toExcelXml(array $rows, array $colonnes, string $titre = 'Export'): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        $xml .= '<Styles><Style ss:ID="H"><Font ss:Bold="1"/><Interior ss:Color="#7c3aed" ss:Pattern="Solid"/><Font ss:Color="#FFFFFF" ss:Bold="1"/></Style></Styles>' . "\n";
        $xml .= '<Worksheet ss:Name="' . htmlspecialchars($titre) . '">' . "\n";
        $xml .= '<Table>' . "\n";
        $xml .= '<Row>';
        foreach ($colonnes as $col) {
            $xml .= '<Cell ss:StyleID="H"><Data ss:Type="String">' . htmlspecialchars($col['label'] ?? $col['key']) . '</Data></Cell>';
        }
        $xml .= '</Row>' . "\n";
        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach ($colonnes as $col) {
                $val  = $row[$col['key']] ?? '';
                $type = is_numeric($val) ? 'Number' : 'String';
                $xml .= '<Cell><Data ss:Type="' . $type . '">' . htmlspecialchars((string)$val) . '</Data></Cell>';
            }
            $xml .= '</Row>' . "\n";
        }
        $xml .= '</Table></Worksheet></Workbook>';
        return $xml;
    }

    public function toHtmlTable(array $rows, array $colonnes, string $titre = '', array $footer = []): string
    {
        $html  = '<style>body{font-family:Arial,sans-serif;font-size:11pt;}';
        $html .= 'h2{color:#7c3aed;}';
        $html .= 'table{width:100%;border-collapse:collapse;}';
        $html .= 'th{background:#7c3aed;color:#fff;padding:6px 8px;text-align:left;}';
        $html .= 'td{padding:5px 8px;border-bottom:1px solid #e2e8f0;}';
        $html .= 'tr:nth-child(even) td{background:#f8f7ff;}';
        $html .= '.footer td{font-weight:bold;background:#ede9fe;}';
        $html .= '</style>';
        if ($titre) $html .= '<h2>' . htmlspecialchars($titre) . '</h2>';
        $html .= '<table><thead><tr>';
        foreach ($colonnes as $col) {
            $html .= '<th>' . htmlspecialchars($col['label'] ?? $col['key']) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($colonnes as $col) {
                $html .= '<td>' . htmlspecialchars((string)($row[$col['key']] ?? '')) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        if ($footer) {
            $html .= '<tfoot class="footer"><tr>';
            foreach ($colonnes as $col) {
                $html .= '<td>' . htmlspecialchars((string)($footer[$col['key']] ?? '')) . '</td>';
            }
            $html .= '</tr></tfoot>';
        }
        $html .= '</table>';
        return $html;
    }

    public function sendCsvResponse(string $csv, string $filename): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        echo $csv;
    }

    public function sendExcelResponse(string $xml, string $filename): void
    {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        echo $xml;
    }

    private function escape(string $val): string
    {
        return '"' . str_replace('"', '""', $val) . '"';
    }
}
