<?php

return [
    'sources' => [
        'hdfc' => [
            'label' => 'HDFC',
            'wallet_name' => 'HDFC Wallet',
            'wallet_type' => 'bank',
            'keywords' => ['hdfc', 'hdfcbank'],
        ],
        'icici' => [
            'label' => 'ICICI',
            'wallet_name' => 'ICICI Wallet',
            'wallet_type' => 'bank',
            'keywords' => ['icici', 'icicibank'],
        ],
        'sbi' => [
            'label' => 'SBI',
            'wallet_name' => 'SBI Wallet',
            'wallet_type' => 'bank',
            'keywords' => ['sbi', 'state bank'],
        ],
        'axis' => [
            'label' => 'Axis',
            'wallet_name' => 'Axis Wallet',
            'wallet_type' => 'bank',
            'keywords' => ['axis', 'axisbank'],
        ],
        'kotak' => [
            'label' => 'Kotak',
            'wallet_name' => 'Kotak Wallet',
            'wallet_type' => 'bank',
            'keywords' => ['kotak'],
        ],
        'gpay' => [
            'label' => 'GPay',
            'wallet_name' => 'UPI Wallet',
            'wallet_type' => 'upi',
            'keywords' => ['gpay', 'google pay', 'tez'],
        ],
        'phonepe' => [
            'label' => 'PhonePe',
            'wallet_name' => 'UPI Wallet',
            'wallet_type' => 'upi',
            'keywords' => ['phonepe'],
        ],
        'paytm' => [
            'label' => 'Paytm',
            'wallet_name' => 'UPI Wallet',
            'wallet_type' => 'upi',
            'keywords' => ['paytm'],
        ],
        'upi' => [
            'label' => 'UPI',
            'wallet_name' => 'UPI Wallet',
            'wallet_type' => 'upi',
            'keywords' => ['upi', 'vpa'],
        ],
    ],

    'amount_patterns' => [
        '/(?:rs\.?|inr|₹)\s*([0-9,]+(?:\.[0-9]{1,2})?)/i',
        '/([0-9,]+(?:\.[0-9]{1,2})?)\s*(?:rs\.?|inr|₹)/i',
    ],

    'reference_patterns' => [
        '/(?:upi|utr|ref|txn|transaction|id|rrn)\s*(?:no|id|number)?[:\s.-]*([A-Z0-9]{4,})/i',
        '/(?:a\/c|ac|account)\s*(?:x+|ending\s*)?([0-9]{4})/i',
        '/xx([0-9]{4})/i',
    ],

    'merchant_patterns' => [
        '/(?:at|to|paid to|sent to|payment to|purchase at)\s+([A-Za-z0-9 .&@_\-\/]{2,80})/i',
        '/(?:merchant|info)[:\s.-]+([A-Za-z0-9 .&@_\-\/]{2,80})/i',
    ],
];
