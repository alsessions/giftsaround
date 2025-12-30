# Redeem Module - QR Code Redemption System

This module provides QR code-based redemption functionality for the Gifts Around platform. Users can generate unique QR codes for special offers that businesses can scan to validate and redeem.

## Features

- **Unique QR Code Generation**: Each redemption generates a unique QR code with a secure token
- **One-Time Use**: QR codes can only be scanned and redeemed once
- **Expiration**: Codes automatically expire 24 hours after generation
- **Tracking**: Full history of all redemptions with status tracking
- **Validation**: Businesses can scan QR codes to verify and redeem offers

## Database Schema

The module uses the `redeem_tokens` table:

```sql
- id (int)
- token (string, unique, 64 chars)
- userId (int) - User who generated the code
- businessId (int) - Associated business entry
- redeemType (enum: 'oneSpecial', 'monthlySpecial')
- monthIndex (int, nullable) - For monthly specials
- monthData (string, nullable) - Serialized month data
- expiresAt (datetime) - When the code expires
- usedAt (datetime, nullable) - When the code was redeemed
- dateCreated (datetime)
- dateUpdated (datetime)
- uid (string)
```

## Controller Actions

### User Actions

- **`generate-redeem-token`** (POST, requires login)
  - Generates a new redemption token and QR code
  - Parameters: businessId, redeemType, monthIndex (optional), monthData (optional)
  - Returns: Renders the redemption page with QR code

- **`show-redemption`** (GET, requires login)
  - Displays an existing redemption with QR code
  - Parameters: token
  - Returns: Renders the redemption page

- **`history`** (GET, requires login)
  - Shows user's redemption history
  - Returns: List of all redemptions with status

### QR Code Actions

- **`qr-code`** (GET, anonymous)
  - Generates QR code image
  - Parameters: token
  - Returns: PNG image of QR code

- **`validate`** (GET/POST, anonymous)
  - Validates a QR code and optionally marks as redeemed
  - Parameters: token, confirm (1 for POST to mark as used)
  - Returns: Validation page with redemption details

## Usage Flow

### For Users

1. User browses businesses and selects a special offer
2. User clicks "Redeem" button on business page
3. System generates unique token and QR code
4. User receives redemption page with QR code
5. User presents QR code to business staff

### For Businesses

1. Business staff scans customer's QR code
2. QR code links to validation URL
3. Validation page shows customer details and offer info
4. Staff confirms the redemption
5. System marks token as used (usedAt timestamp set)
6. Customer receives confirmation

## QR Code Format

QR codes contain a URL in the format:
```
https://yoursite.com/redeem/validate?token={TOKEN}
```

Where `{TOKEN}` is the unique 32-character redemption token.

## Routes

```php
'/redeem/default/generate-redeem-token' => 'redeem/default/generate-redeem-token'
'/redeem/default/qr-code' => 'redeem/default/qr-code'
'/redeem/validate' => 'redeem/default/validate'
'/account/redeem-history' => 'redeem/default/history'
```

## Templates

- `business/redeem.twig` - Shows QR code to user
- `business/redeem-validate.twig` - Validation page for businesses
- `business/redeem-history.twig` - User's redemption history
- `users/account.twig` - Includes recent redemptions widget

## Security Features

- Tokens are cryptographically secure random strings
- Tokens are unique and cannot be reused
- Expiration prevents indefinite validity
- User verification ensures token ownership
- CSRF protection on all form submissions

## Dependencies

- `endroid/qr-code` ^5.0 - QR code generation library
- Craft CMS 5.x
- PHP 8.3+

## Installation

1. Add to composer.json:
```json
"endroid/qr-code": "^5.0"
```

2. Run:
```bash
composer install
```

3. Run migration to create database table (if not already done)

4. Module is auto-loaded via PSR-4 in composer.json

## API Examples

### Generate QR Code Programmatically

```php
use modules\redeem\records\RedeemTokenRecord;

// Create token
$token = Craft::$app->getSecurity()->generateRandomString(32);
$record = new RedeemTokenRecord();
$record->token = $token;
$record->userId = $currentUser->id;
$record->businessId = $businessId;
$record->redeemType = 'oneSpecial';
$record->expiresAt = date('Y-m-d H:i:s', time() + 86400);
$record->save();

// Get QR code URL
$qrUrl = '/redeem/default/qr-code?token=' . $token;
```

### Check Token Status

```php
$tokenRecord = RedeemTokenRecord::findOne(['token' => $token]);

if (!$tokenRecord) {
    // Invalid token
} elseif ($tokenRecord->usedAt) {
    // Already redeemed
} elseif ($tokenRecord->isExpired()) {
    // Expired
} else {
    // Valid and ready to use
}
```

## Troubleshooting

### QR Code Not Displaying

- Ensure endroid/qr-code is installed: `composer show endroid/qr-code`
- Check PHP GD extension is enabled
- Verify token parameter is being passed

### Validation Not Working

- Check that token exists in database
- Verify CSRF token is included in forms
- Check browser console for JavaScript errors

### Token Already Used Error

- This is expected behavior - tokens can only be used once
- User must generate a new token for additional redemptions

## Future Enhancements

- [ ] Add business-specific scanning interface
- [ ] Implement usage analytics
- [ ] Add email notifications for redemptions
- [ ] Support for bulk token generation
- [ ] QR code styling/branding options
- [ ] Mobile app integration