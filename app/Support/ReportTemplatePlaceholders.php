<?php

namespace App\Support;

class ReportTemplatePlaceholders
{
    /**
     * @var list<string>
     */
    private const SUPPORTED = [
        'date',
        'day',
        'shift_start',
        'shift_end',
        'shift_time_range',
        'supervisor',
        'working_staff_list',
        'working_staff_nosupervisor_list',
        'leave_staff_list',
        'overtime_staff_list',
        'attendance_count',
    ];

    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        return self::SUPPORTED;
    }

    /**
     * @return array{unknown: list<string>, malformed: bool}
     */
    public static function inspect(string $body): array
    {
        $unknown = [];
        $malformed = false;
        $matchedRanges = [];

        preg_match_all('/\{\{(.*?)\}\}/s', $body, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $index => [$token, $offset]) {
            $content = $matches[1][$index][0];
            $matchedRanges[] = [$offset, strlen($token)];

            if (preg_match('/^[a-z][a-z0-9_]*$/D', $content) !== 1) {
                $malformed = true;

                continue;
            }

            if (! in_array($content, self::SUPPORTED, true)) {
                $unknown[] = $token;
            }
        }

        foreach (array_reverse($matchedRanges) as [$offset, $length]) {
            $body = substr_replace($body, '', $offset, $length);
        }

        if (str_contains($body, '{{') || str_contains($body, '}}')) {
            $malformed = true;
        }

        return [
            'unknown' => array_values(array_unique($unknown)),
            'malformed' => $malformed,
        ];
    }
}
