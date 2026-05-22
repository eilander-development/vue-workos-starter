<?php

return [
    'reporting' => [
        'start_day_of_month' => (int) env('FINANCE_REPORT_START_DAY', 20),
        'anchor_transaction_id' => env('FINANCE_REPORT_ANCHOR_TRANSACTION_ID'),
    ],
];

