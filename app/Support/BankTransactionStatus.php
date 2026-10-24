<?php

namespace App\Support;

class BankTransactionStatus
{
    public static function isPending(array $row): bool
    {
        if (! empty($row['is_pending']) || ! empty($row['isPending'])) {
            return true;
        }

        if (in_array(strtoupper((string) ($row['status'] ?? '')), ['PDNG', 'PENDING', 'INFO'], true)) {
            return true;
        }

        return is_array($row['raw'] ?? null) && self::isPending($row['raw']);
    }
}
