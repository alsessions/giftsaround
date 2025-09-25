# One-Time Redeem System Documentation

## Overview

This system allows logged-in users to generate one-time redemption URLs for business specials. Each redemption link is unique, expires after 24 hours, and can only be used once.

## Components

### 1. Frontend Templates

#### `/templates/business/_entry.twig`
- Updated with secure form submissions for redeem buttons
- Forms submit to `business/generate-redeem-token` action
- Only visible to logged-in users

#### `/templates/business/redeem.twig`
- Displays the redemption page with special details
- Shows unique redemption code
- Includes business contact information
- Handles expired/invalid redemption states

### 2. Required Backend Implementation

You'll need to implement the following controller actions in your Craft CMS plugin or module:

#### `business/generate-redeem-token` Action
```php
public function actionGenerateRedeemToken()
{
    $this->requireLogin();
    $this->requirePostRequest();
    
    $request = Craft::$app->getRequest();
    $businessId = $request->getRequiredBodyParam('businessId');
    $redeemType = $request->getRequiredBodyParam('redeemType');
    $monthIndex = $request->getBodyParam('monthIndex');
    $monthData = $request->getBodyParam('monthData');
    
    // Generate unique token
    $token = Craft::$app->getSecurity()->generateRandomString(32);
    
    // Store redemption data in database
    // Table: redeem_tokens
    // Fields: token, user_id, business_id, redeem_type, month_index, month_data, expires_at, used_at, created_at
    
    $expiresAt = new DateTime('+24 hours');
    
    // Save to database...
    
    // Redirect to redemption page
    return $this->redirect("/business/redeem/{$token}");
}
```

#### Redemption Display Route
Create a route that handles `/business/redeem/{token}` and renders the redeem template:

```php
public function actionShowRedemption(string $token)
{
    $this->requireLogin();
    
    // Look up redemption record
    $redemption = // Query database for valid, unexpired, unused token
    
    if (!$redemption || $redemption->isExpired() || $redemption->isUsed()) {
        return $this->renderTemplate('business/redeem', [
            'redeemToken' => null,
            'entry' => null
        ]);
    }
    
    $business = Entry::find()->id($redemption->business_id)->one();
    
    $variables = [
        'entry' => $business,
        'redeemToken' => $token,
        'redeemType' => $redemption->redeem_type,
        'monthlySpecialData' => null
    ];
    
    if ($redemption->redeem_type === 'monthlySpecial' && $redemption->month_data) {
        $monthData = explode('|', $redemption->month_data);
        $variables['monthlySpecialData'] = [
            'month' => $monthData[0] ?? '',
            'special' => $monthData[1] ?? ''
        ];
    }
    
    return $this->renderTemplate('business/redeem', $variables);
}
```

### 3. Database Schema

Create a `redeem_tokens` table:

```sql
CREATE TABLE redeem_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    token VARCHAR(64) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    business_id INT NOT NULL,
    redeem_type ENUM('oneSpecial', 'monthlySpecial') NOT NULL,
    month_index INT NULL,
    month_data TEXT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_token (token),
    INDEX idx_user_business (user_id, business_id),
    INDEX idx_expires (expires_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES entries(id) ON DELETE CASCADE
);
```

### 4. Security Features

- **CSRF Protection**: All forms include CSRF tokens
- **User Authentication**: Only logged-in users can redeem
- **Token Expiration**: Tokens expire after 24 hours
- **Single Use**: Each token can only be used once
- **User Validation**: Redemption page shows which user the token belongs to

### 5. URL Structure

- **Generation**: POST to `/actions/business/generate-redeem-token`
- **Redemption**: GET `/business/redeem/{token}`
- **Business Page**: `/business/{slug}` (existing)

### 6. User Flow

1. User visits business page
2. User clicks "Redeem" button (if logged in)
3. Form submits to generate unique token
4. User redirected to redemption page with token
5. User sees special offer details and redemption code
6. User presents code to business
7. Token is marked as used (optional backend implementation)

### 7. Template Variables

The redeem template expects these variables:

- `entry` - The business entry
- `redeemToken` - The unique redemption token
- `redeemType` - Either 'oneSpecial' or 'monthlySpecial'
- `monthlySpecialData` - Object with 'month' and 'special' keys (for monthly specials)
- `currentUser` - The logged-in user (from Craft)

### 8. Error Handling

The redeem template handles these error states:

- Invalid/expired tokens
- Already used tokens
- Missing business data
- User not logged in

### 9. Styling

The templates use Tailwind CSS classes consistent with the existing design:

- Green theme for successful redemption
- Amber/yellow for instructions
- Red for warnings and errors
- Consistent spacing and typography

### 10. Next Steps

1. Implement the backend controller actions
2. Create the database table
3. Set up the routing
4. Test the complete flow
5. Consider adding email notifications
6. Add analytics/tracking if desired

## Optional Enhancements

- Email confirmation when token is generated
- SMS notifications
- QR codes for mobile redemption
- Admin interface to view redemption analytics
- Rate limiting to prevent abuse
- Integration with business POS systems