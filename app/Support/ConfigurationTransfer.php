<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConfigurationTransfer
{
    public const STAFF_FORMAT = 'report-gen.staff';

    public const SHIFT_FORMAT = 'report-gen.shifts';

    public const TEMPLATE_FORMAT = 'report-gen.templates';

    public static function staffEnvelope(array $items): array
    {
        return self::buildEnvelope(self::STAFF_FORMAT, $items);
    }

    public static function shiftEnvelope(array $items): array
    {
        return self::buildEnvelope(self::SHIFT_FORMAT, $items);
    }

    public static function templateEnvelope(array $items): array
    {
        return self::buildEnvelope(self::TEMPLATE_FORMAT, $items);
    }

    /**
     * @return array{format: string, version: int, exported_at: string, items: array<int, array<string, mixed>>}
     */
    public static function buildEnvelope(string $format, array $items): array
    {
        return [
            'format' => $format,
            'version' => 1,
            'exported_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur')))->format(DATE_ATOM),
            'items' => $items,
        ];
    }

    public static function downloadJson(string $filename, array $payload): StreamedResponse
    {
        return response()->streamDownload(function () use ($payload): void {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function decodeJsonUploadedFile(UploadedFile $file): array
    {
        $path = $file->getPathname();

        if (! is_readable($path)) {
            throw new \RuntimeException('The uploaded file could not be read.');
        }

        $content = @file_get_contents($path);

        if ($content === false) {
            throw new \RuntimeException('The uploaded file could not be read.');
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('The uploaded file is not valid JSON.', 0, $exception);
        }

        if (! is_array($decoded)) {
            throw new \RuntimeException('The uploaded file does not contain a JSON object.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function validateEnvelope(array $payload, string $expectedFormat): array
    {
        if (! array_key_exists('format', $payload) || ! array_key_exists('version', $payload) || ! array_key_exists('items', $payload)) {
            throw new \RuntimeException('The import file is missing required envelope fields.');
        }

        if (($payload['format'] ?? null) !== $expectedFormat) {
            throw new \RuntimeException('The import file format is not supported for this configuration type.');
        }

        if ((int) ($payload['version'] ?? 0) !== 1) {
            throw new \RuntimeException('Unsupported import version. Only version 1 is supported.');
        }

        if (! is_array($payload['items'])) {
            throw new \RuntimeException('The import file contains an invalid items payload.');
        }

        return $payload;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<string, mixed>
     */
    public static function validateAllowedFields(array $item, array $allowedFields, string $label): array
    {
        if (! is_array($item)) {
            throw new \RuntimeException($label.' item must be an object.');
        }

        $unexpected = array_diff(array_keys($item), $allowedFields);

        if ($unexpected !== []) {
            throw new \RuntimeException($label.' contains unexpected field(s): '.implode(', ', $unexpected).'.');
        }

        return $item;
    }
}
