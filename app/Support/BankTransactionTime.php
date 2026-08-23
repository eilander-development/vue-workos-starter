<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class BankTransactionTime
{
    private const TIMEZONE = 'Europe/Amsterdam';

    /** 2026-08-20T14:32:00, eventueel met tijdzone (Z of +02:00). */
    private const ISO_PATTERN = '/\d{4}-\d{2}-\d{2}[T ](\d{1,2}):(\d{2})(?::\d{2})?(Z|[+-]\d{2}:?\d{2})?/';

    /** 20-08-2026 14:32, 23.08.26/14.21, 23/08/2026 14h21. */
    private const DUTCH_PATTERN = '/\d{1,2}[-.\/]\d{1,2}[-.\/]\d{2,4}[\s\/,]{1,3}(\d{1,2})[:.h](\d{2})/iu';

    /**
     * Velden waarin een bank een volledige datum + tijd kan meegeven.
     */
    private const DATETIME_FIELDS = [
        'time',
        'booking_datetime',
        'booking_date_time',
        'bookingDateTime',
        'value_datetime',
        'value_date_time',
        'valueDateTime',
        'transaction_datetime',
        'transaction_date_time',
        'transactionDateTime',
        'entry_datetime',
        'transaction_date',
    ];

    /**
     * Vrije-tekstvelden waarin ING de tijd in de omschrijving zet
     * (bijvoorbeeld "Datum/Tijd: 20-08-2026 14:32" of "23.08.26/14.21").
     */
    private const TEXT_FIELDS = [
        'remittance_information',
        'note',
        'additional_information',
        'description',
    ];

    public static function extract(array $row): ?string
    {
        $raw = is_array($row['raw'] ?? null) ? $row['raw'] : $row;

        foreach (self::DATETIME_FIELDS as $field) {
            foreach ([$row[$field] ?? null, $raw[$field] ?? null] as $value) {
                if ($time = self::fromValue($value)) {
                    return $time;
                }
            }
        }

        $lines = array_merge(self::textLines($row), self::textLines($raw));

        foreach ($lines as $line) {
            if ($time = self::fromLabel($line)) {
                return $time;
            }
        }

        foreach ($lines as $line) {
            if ($time = self::fromDateTimeText($line)) {
                return $time;
            }
        }

        return null;
    }

    public static function fromValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        // Een datum met tijd wordt letterlijk gelezen; zonder tijdzone-informatie
        // zou omrekenen de klok verschuiven.
        if (self::containsDateTime($value)) {
            return self::fromDateTimeText($value);
        }

        if (preg_match('/^(\d{1,2})[:.](\d{2})(?::\d{2})?$/', $value, $clock)) {
            return self::clock($clock[1], $clock[2]);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function textLines(array $payload): array
    {
        $lines = [];

        foreach (self::TEXT_FIELDS as $field) {
            $value = $payload[$field] ?? null;

            if (is_string($value)) {
                $lines[] = $value;

                continue;
            }

            if (is_array($value)) {
                foreach ($value as $line) {
                    if (is_string($line)) {
                        $lines[] = $line;
                    }
                }
            }
        }

        return $lines;
    }

    private static function fromLabel(string $text): ?string
    {
        if (! preg_match('/(?:datum\s*\/\s*tijd|tijd|time)\s*[:=]\s*([^;\r\n]+)/iu', $text, $matches)) {
            return null;
        }

        $value = trim($matches[1]);

        if ($time = self::fromDateTimeText($value)) {
            return $time;
        }

        if (preg_match('/^(\d{1,2})[:.](\d{2})/', $value, $clock)) {
            return self::clock($clock[1], $clock[2]);
        }

        return null;
    }

    private static function containsDateTime(string $text): bool
    {
        return preg_match(self::ISO_PATTERN, $text) === 1
            || preg_match(self::DUTCH_PATTERN, $text) === 1;
    }

    private static function fromDateTimeText(string $text): ?string
    {
        if (preg_match(self::ISO_PATTERN, $text, $matches)) {
            if (! empty($matches[3])) {
                try {
                    $carbon = Carbon::parse($matches[0])->timezone(self::TIMEZONE);

                    return self::clock($carbon->format('H'), $carbon->format('i'));
                } catch (\Throwable) {
                    // Val terug op de letterlijke tijd uit de string.
                }
            }

            return self::clock($matches[1], $matches[2]);
        }

        if (preg_match(self::DUTCH_PATTERN, $text, $matches)) {
            return self::clock($matches[1], $matches[2]);
        }

        return null;
    }

    /**
     * Middernacht komt in de praktijk niet voor en duidt op een datum zonder tijd.
     */
    private static function clock(string|int $hours, string|int $minutes): ?string
    {
        $hours = (int) $hours;
        $minutes = (int) $minutes;

        if ($hours > 23 || $minutes > 59 || ($hours === 0 && $minutes === 0)) {
            return null;
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
