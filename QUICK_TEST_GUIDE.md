# Quick Test Guide - QR Code Redemption

## ⚠️ Prerequisites

Before testing, ensure routes are configured in `config/routes.php`:

```php
// QR Code routes
'redeem/qr-code' => 'redeem/default/qr-code',
'redeem/validate' => 'redeem/default/validate',
'redeem/test-qr' => 'redeem/default/test-qr',
'test-qr' => ['template' => 'test-qr'],
```

After adding routes, clear caches:
```bash
ddev exec ./craft clear-caches/all
```

## 🚀 Quick Start (5 Minutes)

### 0. Verify Installation

Visit `/test-qr` - you should see a test QR code. If you get a 404, routes are not configured.

### 1. Test as a User

1. **Login** to your account at `/login`

2. **Browse businesses** at `/business`

3. **Select any business** and click "Redeem" on a special offer

4. **You should see:**
   - A QR code image (not a text string)
   - Message: "Show your unique QR code below to be scanned"
   - An abbreviated code reference below the QR
   - 24-hour expiration warning

### 2. Test QR Code Scanning

**Option A: Use Your Phone**
1. Open your phone's camera
2. Point it at the QR code on screen
3. Tap the notification that appears
4. Should open: `{yoursite}/redeem/validate?token=...`

**Option B: Test Validation Directly**
1. Right-click QR code → "Copy Image Address"
2. Visit that URL (should be `/redeem/qr-code?token=...`)
3. Copy the token from URL
4. Visit: `/redeem/validate?token={PASTE_TOKEN_HERE}`
5. **Note:** First visit automatically redeems the code!

### 3. Test Validation Page (Automatic Redemption)

When you land on `/redeem/validate?token=...` you should immediately see:
- ✅ Green success message
- "QR Code Redeemed!"
- Customer name and email
- Business name
- Offer details
- Redemption timestamp

**Note:** The code is marked as redeemed the instant the page loads (no confirmation needed)

### 4. Test Double-Redemption Prevention

1. Try to visit the same validation URL again (or scan QR code a second time)
2. Should see: ❌ "Already Redeemed" error
3. Shows when it was originally redeemed
4. This proves one-time use enforcement works

### 5. Test Account History

1. Visit `/account` (your account page)
2. **Overview tab** should show:
   - "Recent QR Codes" section
   - Your redemption with "Redeemed" badge (green)
   
3. Click **"QR Code History"** link
4. Should see full history at `/account/redeem-history`
5. Click **"Show QR Code"** button
6. QR code should appear inline

---

## ✅ Success Checklist

- [ ] QR code displays (not text string)
- [ ] QR code is scannable with phone camera
- [ ] Validation page immediately redeems (no confirmation needed)
- [ ] First scan shows success message with details
- [ ] Double-scan shows "Already Redeemed" error
- [ ] Account overview shows recent QR codes
- [ ] History page displays all redemptions
- [ ] Status badges show correctly (Active/Redeemed)
- [ ] No time-based expiration (codes stay active until scanned)

---

## 🐛 Troubleshooting

### QR Code Not Showing (Most Common Issue)

**Problem:** Image shows broken/404 error

**Solution:** Routes not configured!

1. Check `config/routes.php` has the QR routes (see Prerequisites above)
2. Clear caches: `ddev exec ./craft clear-caches/all`
3. Test: Visit `/test-qr` - should show a test QR code
4. If still not working, check package:

```bash
# Check if package installed
ddev composer show endroid/qr-code

# Should show: endroid/qr-code 5.0.7
```

### Blank/Broken Image
- Check error logs: `tail -f storage/logs/web.log`
- Verify GD extension: `ddev exec php -m | grep gd`
- Try accessing QR URL directly: `/redeem/qr-code?token=test123`

### "Token not found" Error
- Token might be invalid
- Check database: `SELECT * FROM redeem_tokens ORDER BY dateCreated DESC LIMIT 5;`
- Generate a fresh redemption code

### Validation Page Not Loading
- Verify routes in `config/routes.php`
- Check that action allows anonymous: `'allowAnonymous' => ['validate']`
- Clear Craft cache: `ddev exec ./craft clear-caches/all`

---

## 📱 Mobile Testing

### iOS (11+)
- Open Camera app
- Point at QR code
- Tap yellow notification banner
- Should open Safari with validation page
- **Code is immediately redeemed** (no button to click)

### Android (5+)
- Open Camera app
- Point at QR code
- Tap the notification/popup
- Should open Chrome with validation page
- **Code is immediately redeemed** (no button to click)

### QR Scanner Apps
- Any third-party QR scanner should work
- QR code contains a simple URL (no special encoding)

---

## 🔄 Test Different Scenarios

### Active Code
```
Status: Blue "Active" badge
Can be: Scanned and redeemed (no expiration time)
Action: First scan immediately redeems
```

### Redeemed Code
```
Status: Green "Redeemed" badge
Can be: Viewed in history
Action: Shows "Already Redeemed" error with timestamp
Note: Cannot be used again
```

---

## 🎯 Key URLs

| Action | URL | Auth Required |
|--------|-----|---------------|
| **Test QR** | `/test-qr` | No |
| View businesses | `/business` | No |
| Generate code | POST `/redeem/default/generate-redeem-token` | Yes |
| QR code image | `/redeem/qr-code?token={TOKEN}` | No |
| Validate/scan | `/redeem/validate?token={TOKEN}` | No |
| User history | `/account/redeem-history` | Yes |
| User account | `/account` | Yes |

---

## 💡 Pro Tips

1. **No time expiration:** Codes stay active indefinitely until scanned
2. **View raw QR URL:** Right-click QR code → Inspect → Check `src` attribute
3. **Database check:** `SELECT token, usedAt, dateCreated FROM redeem_tokens WHERE userId = {YOUR_ID};`
4. **Clear test data:** `DELETE FROM redeem_tokens WHERE userId = {YOUR_ID};`
5. **Anyone can scan:** No user authentication required for validation page

---

## 🎉 That's It!

The system is working if:
1. ✅ `/test-qr` shows a QR code (proves routes & package work)
2. ✅ Users see QR codes instead of text strings
3. ✅ Anyone can scan to validate (no authentication needed)
4. ✅ First scan immediately redeems (no confirmation button)
5. ✅ Codes work only once (double-scan shows error)
6. ✅ No time expiration (codes stay active until used)
7. ✅ History tracks everything
8. ✅ Account page shows recent activity

**⚠️ Most Common Issue:** If QR codes show 404/broken image, routes are not configured in `config/routes.php`

**Need more details?** See `QR_CODE_IMPLEMENTATION.md` or `modules/redeem/README.md`
