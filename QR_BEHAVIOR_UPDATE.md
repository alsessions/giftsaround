# QR Code Behavior Update

## Summary of Changes

The QR code redemption system has been updated to simplify the user flow and remove time-based expiration.

## What Changed

### Before
- QR codes expired after 24 hours
- Validation page required confirmation button click
- Only the user who generated the code could view it
- Two-step process: scan → confirm → redeem

### After
- **QR codes never expire** (only when used)
- **Automatic redemption** on first scan (no confirmation needed)
- **Anyone can scan** the QR code (user or business)
- One-step process: scan → immediately redeemed

## New Behavior

### 1. No Time Expiration
- QR codes are valid indefinitely until scanned
- The `expiresAt` field is set to 10 years in the future
- Database queries ignore expiration time
- Only the `usedAt` field determines if a code is valid

### 2. Automatic Redemption
When a QR code is scanned:
1. Camera opens: `https://yoursite.com/redeem/validate?token={TOKEN}`
2. Validation page loads
3. **System immediately marks token as used** (on first page load)
4. Success page displays with redemption details
5. Second scan shows "Already Redeemed" error

### 3. No Authentication Required
- Validation endpoint is publicly accessible
- No login required to scan/validate
- Anyone with the QR code can redeem it
- Business or user can scan - doesn't matter

## Technical Implementation

### Controller Changes

**`modules/redeem/controllers/DefaultController.php`**

```php
// Set expiration far in future (10 years)
$expiresAt = date('Y-m-d H:i:s', time() + (365 * 24 * 60 * 60 * 10));

// actionValidate() - Auto-redeem on first access
public function actionValidate(): Response
{
    // Check if already used
    if ($tokenData['usedAt']) {
        return "Already Redeemed" page;
    }
    
    // Mark as used immediately
    Craft::$app->db->createCommand()
        ->update('{{%redeem_tokens}}', 
            ['usedAt' => $now], 
            ['token' => $token, 'usedAt' => null]
        )
        ->execute();
    
    return "Success" page;
}

// actionHistory() - Don't check expiration
$token->isExpired = false; // Never expired by time
$token->isValid = !$token->isUsed; // Only invalid if used
```

### Template Changes

**`templates/business/redeem.twig`**
- Removed: "expires in 24 hours" warning
- Changed to: "can only be scanned once"
- Updated: Instructions reflect automatic redemption

**`templates/business/redeem-validate.twig`**
- Removed: Confirmation form (no POST needed)
- Removed: "Confirm Redemption" button
- Changed: Shows success immediately
- Simplified: Only two states (Success or Already Used)

**`templates/business/redeem-history.twig`**
- Removed: "Expired" status badge
- Removed: `expiresAt` date display
- Shows only: Active or Redeemed

**`templates/users/account.twig`**
- Removed: Expired status from recent QR codes
- Removed: Time-based expiration checks
- Shows only: Active (blue) or Redeemed (green)

## User Impact

### For Customers
✅ **Better:**
- QR codes don't expire - use anytime
- Faster redemption (no button to click)
- Simpler instructions

⚠️ **Note:**
- Can't "preview" validation page without redeeming
- Anyone with the QR code link can redeem it

### For Businesses
✅ **Better:**
- Instant redemption when scanned
- No staff training needed for confirmation step
- Clearer success/error states

⚠️ **Note:**
- No ability to review before confirming
- Must ensure QR is scanned only when ready to redeem

## Database Impact

### Status Determination

**Old Logic:**
```sql
CASE 
    WHEN usedAt IS NOT NULL THEN 'Redeemed'
    WHEN expiresAt < NOW() THEN 'Expired'
    ELSE 'Active'
END as status
```

**New Logic:**
```sql
CASE 
    WHEN usedAt IS NOT NULL THEN 'Redeemed'
    ELSE 'Active'
END as status
```

### Fields Still Used
- `token` - Unique redemption code
- `userId` - Who generated it (for history)
- `businessId` - Which business
- `redeemType` - Type of offer
- `usedAt` - When redeemed (NULL = active)
- `dateCreated` - When generated
- `expiresAt` - Still in DB but set to 10 years (ignored)

## Testing Checklist

- [x] Generate QR code - displays without expiration warning
- [x] Scan QR code - immediately shows success page
- [x] Scan again - shows "Already Redeemed" error
- [x] Check history - no "Expired" badges
- [x] Check account page - only shows Active/Redeemed
- [x] Verify no time checks in validation logic
- [x] Confirm anyone can scan (no auth required)

## Edge Cases Handled

### Race Condition Protection
```php
// Only update if not already used
->update('{{%redeem_tokens}}', 
    ['usedAt' => $now], 
    ['token' => $token, 'usedAt' => null] // WHERE clause
)
```

If two people scan simultaneously, only one update succeeds.

### Invalid Token
- Shows "Invalid redemption code" error
- No redemption occurs

### Already Used Token
- Shows "Already Redeemed" page
- Displays original redemption timestamp
- Shows customer and business info

## Security Considerations

### What's Still Secure
- ✅ Tokens are cryptographically random (32 chars)
- ✅ One-time use enforcement (database-level)
- ✅ Full audit trail (usedAt timestamps)
- ✅ Unique tokens (can't guess or reuse)

### What Changed
- ⚠️ No user authentication on validation page
- ⚠️ No time-based expiration
- ⚠️ No confirmation step

### Recommendations for Production
If you need tighter control, consider:
1. **Add time expiration back:** Change expiration to 24-72 hours
2. **Require business login:** Add authentication to validation endpoint
3. **Add geofencing:** Check user is at business location
4. **Add confirmation step:** Restore two-step validation process

## Rollback Plan

If you need to restore old behavior:

### 1. Restore Time Expiration
```php
// In actionGenerateRedeemToken()
$expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60)); // 24 hours

// In actionValidate()
if (strtotime($tokenData['expiresAt']) <= time()) {
    return "Expired" page;
}
```

### 2. Restore Confirmation Step
- Revert `templates/business/redeem-validate.twig` from git
- Change `actionValidate()` to check for POST request
- Add back confirmation form

### 3. Restore User Verification
```php
// In actionShowRedemption()
if ($tokenData['userId'] != Craft::$app->getUser()->getId()) {
    return "Unauthorized" error;
}
```

## Migration Notes

### Existing Tokens
- All existing QR codes are still valid
- `expiresAt` dates in the past are ignored
- Unused codes can still be redeemed
- Used codes remain marked as used

### Database Migration
No migration needed - schema unchanged. Just behavior logic updated.

## Support

### Common Questions

**Q: Can I restore 24-hour expiration?**
A: Yes, change line 109 in `DefaultController.php` and uncomment expiration checks.

**Q: Why no confirmation button?**
A: Simplifies UX - scan = redeem. Add it back if needed (see Rollback Plan).

**Q: What if someone scans accidentally?**
A: They can generate a new QR code. Consider adding confirmation back if this is common.

**Q: Can we require business login to scan?**
A: Yes, remove `'validate'` from `$allowAnonymous` array and add authentication check.

## Files Modified

### Backend
- `modules/redeem/controllers/DefaultController.php`
  - Line 109: Changed expiration to 10 years
  - Lines 190-198: Removed user ownership check
  - Lines 418-451: Simplified validation to auto-redeem
  - Lines 295-296: Set isExpired to false always

### Templates
- `templates/business/redeem.twig`
  - Line 98: Removed "24 hours" warning
  - Line 162: Updated instructions
  
- `templates/business/redeem-validate.twig`
  - Removed: Entire confirmation form section (lines 161-268)
  - Simplified: Only success and error states
  
- `templates/business/redeem-history.twig`
  - Line 112: Removed `expiresAt` display
  - Lines 152-159: Removed "Expired" badge
  
- `templates/users/account.twig`
  - Line 123: Removed `isExpired` check
  - Lines 143-150: Removed "Expired" status badge

### Documentation
- `QR_CODE_IMPLEMENTATION.md` - Updated flow descriptions
- `QUICK_TEST_GUIDE.md` - Updated testing instructions
- `QR_BEHAVIOR_UPDATE.md` - This file (new)

## Summary

**Old:** Time-limited codes with two-step confirmation
**New:** Permanent codes with instant redemption

The change makes the system simpler and more user-friendly, but removes some safeguards. Consider your specific use case when deciding if this behavior is appropriate.

For most scenarios, instant redemption is better UX. For high-value offers or situations requiring verification, consider restoring the confirmation step.