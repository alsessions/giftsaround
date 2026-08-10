<?php
return [
    'business' => 'business/index',
    'register' => ['template' => 'users/register'],
    'login' => ['template' => 'users/login'],
    'logout' => ['template' => 'users/logout'],
    'account' => ['template' => 'users/account'],
    'account/profile' => ['template' => 'users/profile'],
    'register/complete' => 'registration/default/complete',
    'register/success' => ['template' => 'users/register-success'],
    'register/payment-failed' => ['template' => 'users/payment-failed'],
    'account/redeem-history' => 'redeem/default/history',
    'redeem/qr-code' => 'redeem/default/qr-code',
    'redeem/validate' => 'redeem/default/validate',
    'redeem/validation' => 'redeem/default/validate',
    'business/redeem/validate' => 'redeem/default/validate',
    'business/redeem/validation' => 'redeem/default/validate',
    'business/redeem/<token:([a-zA-Z0-9_-]{32})>' => 'redeem/default/show-redemption',
    'redeem/test-qr' => 'redeem/default/test-qr',

    // Admin redemptions management
    'manage-redemptions' => ['template' => 'admin-redemptions'],

    'test-redeem' => ['template' => 'test-redeem'],
    'test-qr' => ['template' => 'test-qr'],
];
