<?php
return [
    'business' => 'business/index',
    'register' => ['template' => 'users/register'],
    'login' => ['template' => 'users/login'],
    'logout' => ['template' => 'users/logout'],
    'account' => ['template' => 'users/account'],
    'account/profile' => ['template' => 'users/profile'],
    'register/success' => ['template' => 'users/register-success'],
    'account/redeem-history' => 'redeem/default/history',
    'redeem/qr-code' => 'redeem/default/qr-code',
    'redeem/validate' => 'redeem/default/validate',
    'redeem/test-qr' => 'redeem/default/test-qr',
    
    // Admin redemptions management
    'manage-redemptions' => ['template' => 'admin-redemptions'],
    
    'test-redeem' => ['template' => 'test-redeem'],
    'test-qr' => ['template' => 'test-qr'],
];
