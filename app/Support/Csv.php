<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV export of data the API already returned (no business logic here). */
final class Csv
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<list<mixed>>  $rows
     */
    public static function stream(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel opens UTF-8
            fputcsv($out, $headers, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'cell'], $row), ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise spreadsheet formula injection; amounts stay exact strings. */
    public static function cell(mixed $v): string
    {
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_array($v)) {
            $v = json_encode($v, JSON_UNESCAPED_SLASHES);
        }
        $s = (string) ($v ?? '');

        return $s !== '' && strpbrk($s[0], "=+-@\t\r") !== false && ! preg_match('/^-?\d+(\.\d+)?$/', $s) ? "'".$s : $s;
    }
}
