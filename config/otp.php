<?php

return [
    'bypass' => (bool) env('OTP_BYPASS', false),
    'bypass_code' => env('OTP_BYPASS_CODE', '123456'),
    'expiry_minutes' => (int) env('OTP_EXPIRY_MINUTES', 5),
];
