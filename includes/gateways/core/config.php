<?php

return [
    'environment' => [
        'production_url' => 'https://payment.9pay.vn/portal', //Pro
        'x_forward_link_production' => 'https://payment.9pay.vn/payments/create', //Pro

        //Alpha
        'stg_url' => 'https://sand-portal.9pay.vn/portal/create/order', // V2 SAND
        'x_forward_link_test' => 'https://sand-payment.9pay.vn/payments/create', /*Alpha*/
    ],
    'min_amount' => 2000,
    'max_amount' => 200000000,
    'status' => [
        'PAYMENT_SUCCESS' => [1, 2, 4, 5],
        'PAYMENT_FAILED' => [6, 7, 8, 10, 12, 14, 15],
    ],
    'PAYMENT_CANCEL' => 8,
    'PAYMENT_REVIEW' => 3,
    'PAYMENT_TIMEOUT' => 15,
    'CURRENCY' => [
        'VND', 'USD', 'EUR', 'GBP', 'CNY', 'JPY'
    ],
    'NOT_HAS_IPN' => ['MB', 'STB', 'VPB'],
];