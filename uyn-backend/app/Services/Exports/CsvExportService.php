<?php

namespace App\Services\Exports;

use Carbon\CarbonInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportService
{
    public function download(
        string $filename,
        array $headers,
        iterable $rows
    ): StreamedResponse {
        return response()->streamDownload(
            function () use ($headers, $rows): void {
                $handle = fopen('php://output', 'w');

                if ($handle === false) {
                    return;
                }

                /*
                 * BOM UTF-8 para que Excel abra correctamente acentos,
                 * eñes y caracteres especiales en español.
                 */
                fwrite($handle, "\xEF\xBB\xBF");

                fputcsv($handle, $headers);

                foreach ($rows as $row) {
                    fputcsv(
                        $handle,
                        array_map(
                            fn ($value) => $this->formatValue($value),
                            $row
                        )
                    );
                }

                fclose($handle);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        if (is_array($value)) {
            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: '';
        }

        return (string) $value;
    }
}