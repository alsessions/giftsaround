<?php
/**
 * Site URL Rules
 *
 * You can define custom site URL rules here, which Craft will check in addition
 * to routes defined in Settings → Routes.
 *
 * Read about Craft's routing behavior (and this file's structure), here:
 * @link https://craftcms.com/docs/5.x/system/routing.html
 */

return [
    // Business listing page
    'business' => 'business/index',

    // Individual business entries are handled by the section's URI format: business/{slug}
    // No additional route needed as it's defined in the section configuration

    // User registration and authentication routes
    'register' => ['template' => 'users/register'],
    'login' => ['template' => 'users/login'],
    'logout' => ['template' => 'users/logout'],
    'account' => ['template' => 'users/account'],
    'account/profile' => ['template' => 'users/profile'],
    'register/success' => ['template' => 'users/register-success'],

    // Redeem history route
    'account/redeem-history' => 'redeem/default/history',

    // Test page
    'test-redeem' => ['template' => 'test-redeem'],
];
