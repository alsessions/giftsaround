# Redeem System Testing Guide

## 🚀 Quick Start Testing

### Step 1: System Health Check
1. Visit: `https://nick2.ddev.site/test-redeem`
2. Verify all green checkmarks:
   - ✅ Redeem module is loaded
   - ✅ redeem_tokens table exists
   - ✅ User authentication status

### Step 2: User Login/Registration
1. **If not logged in**: Click "Login" or "Register" from the test page
2. **Register new user**: `https://nick2.ddev.site/register`
   - Fill out form with valid email
   - Complete registration process
3. **Login existing user**: `https://nick2.ddev.site/login`

### Step 3: Test Token Generation
1. **Visit business listing**: `https://nick2.ddev.site/business`
2. **Click any business** to view details
3. **Find "Redeem" buttons** (green buttons next to special offers)
4. **Click "Redeem"** - should redirect to redemption page
5. **Verify redemption page shows**:
   - Business name and details
   - Unique redemption code (32 characters)
   - Expiration information (24 hours)
   - Usage instructions

### Step 4: Test History Tracking
1. **Generate multiple tokens** from different businesses
2. **Visit history page**: `https://nick2.ddev.site/account/redeem-history`
3. **Verify statistics** show correct counts
4. **Test active token access** by clicking "Use Now"

## 🔧 Advanced Testing

### Test Error Scenarios

#### Invalid Token Test
```bash
# Visit invalid token URL
curl https://nick2.ddev.site/business/redeem/invalidtoken12345678901234567890
```
Should show proper error page.

#### Expired Token Test
```bash
# Connect to database and expire a token
ddev mysql -e "UPDATE redeem_tokens SET expiresAt = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = 1"
```
Then visit the expired token's URL - should show expiration error.

#### Used Token Test
```bash
# Mark token as used
ddev mysql -e "UPDATE redeem_tokens SET usedAt = NOW() WHERE id = 1"
```
Token should show as "already used".

### Test Duplicate Prevention
1. Generate a token for a business special
2. Try to generate another token for the same special
3. Should redirect to existing active token instead of creating duplicate

### Test Authentication
1. **Logout** and visit a business page
2. **Redeem buttons should not appear** for anonymous users
3. **"Sign Up to Redeem" buttons** should appear instead
4. **Direct token URLs** should redirect to login if not authenticated

## 📊 Database Testing

### Check Token Creation
```sql
-- View all tokens
SELECT * FROM redeem_tokens ORDER BY dateCreated DESC;

-- Count tokens by status
SELECT 
  COUNT(*) as total,
  COUNT(usedAt) as used,
  COUNT(CASE WHEN expiresAt > NOW() AND usedAt IS NULL THEN 1 END) as active
FROM redeem_tokens;
```

### Check Token Expiration Logic
```sql
-- View expired tokens
SELECT * FROM redeem_tokens WHERE expiresAt <= NOW() AND usedAt IS NULL;

-- View active tokens
SELECT * FROM redeem_tokens WHERE expiresAt > NOW() AND usedAt IS NULL;
```

## 🛡️ Security Testing

### CSRF Protection Test
1. **Disable JavaScript** in browser
2. **Try submitting redeem form** - should still work (CSRF token in hidden field)
3. **Manually craft POST request** without CSRF token - should fail

### Authentication Test
1. **Open incognito window**
2. **Try to visit token URL directly** - should redirect to login
3. **Try to access history page** - should redirect to login

### Token Ownership Test
1. **Login as User A** and generate token
2. **Login as User B** and try to visit User A's token URL
3. **Should show unauthorized error**

## 📱 Mobile Testing

### Responsive Design Test
1. **Open browser developer tools**
2. **Switch to mobile view** (iPhone/Android simulation)
3. **Test complete flow**:
   - Business listing page
   - Business detail page
   - Redeem button interaction
   - Redemption page display
   - History page navigation

### Touch Interaction Test
1. **Use actual mobile device** or touch simulation
2. **Test all buttons and links**
3. **Verify forms work correctly**
4. **Check text readability**

## 🔄 Load Testing

### Multiple Token Generation
```bash
# Generate multiple tokens quickly
for i in {1..5}; do
  echo "Generating token $i"
  # Visit different business pages and generate tokens
done
```

### Concurrent User Test
1. **Open multiple browser windows/tabs**
2. **Login different users** in each
3. **Generate tokens simultaneously**
4. **Verify no conflicts or errors**

## 📈 Performance Testing

### Database Query Performance
```sql
-- Test index usage
EXPLAIN SELECT * FROM redeem_tokens WHERE userId = 1 AND businessId = 2 AND redeemType = 'oneSpecial';

-- Test expiration query performance
EXPLAIN SELECT * FROM redeem_tokens WHERE expiresAt > NOW();

-- Test token lookup performance
EXPLAIN SELECT * FROM redeem_tokens WHERE token = 'test12345678901234567890123456789012';
```

### Page Load Testing
```bash
# Test page load times
time curl -s https://nick2.ddev.site/test-redeem > /dev/null
time curl -s https://nick2.ddev.site/business > /dev/null
time curl -s https://nick2.ddev.site/account/redeem-history > /dev/null
```

## 🐛 Troubleshooting Common Issues

### Module Not Loading
```bash
# Clear all caches
ddev craft clear-caches/all

# Rebuild autoloader
ddev composer dump-autoload

# Check logs
ddev logs | tail -50
```

### Database Connection Issues
```bash
# Test database connection
ddev mysql -e "SELECT 1"

# Check table exists
ddev mysql -e "SHOW TABLES LIKE 'redeem_tokens'"

# Check migration status
ddev craft migrate/history | grep redeem
```

### Template Errors
1. **Check Twig syntax** in all template files
2. **Verify variable names** match controller output
3. **Clear template caches**
4. **Check error logs** in `storage/logs/`

### Form Submission Issues
1. **Verify CSRF tokens** are included in forms
2. **Check route configuration** in `config/routes.php`
3. **Validate controller action names**
4. **Test with browser network tab** to see actual requests

## 📋 Pre-Production Checklist

### Code Quality
- [ ] All files properly documented
- [ ] No debug code or console.log statements
- [ ] Error handling covers all scenarios
- [ ] Security measures implemented correctly

### Database
- [ ] Migration applied successfully
- [ ] All indexes created correctly
- [ ] Foreign key constraints working
- [ ] Sample data for testing removed

### Configuration
- [ ] Module properly registered in `config/app.php`
- [ ] Routes configured correctly in `config/routes.php`
- [ ] Composer autoload optimized
- [ ] Environment variables set appropriately

### User Experience
- [ ] All pages mobile-responsive
- [ ] Error messages user-friendly
- [ ] Loading states implemented
- [ ] Navigation intuitive and consistent

### Security
- [ ] CSRF protection on all forms
- [ ] Authentication required for sensitive actions
- [ ] Input validation on all user inputs
- [ ] SQL injection prevention verified

## 🎯 Success Criteria

The system passes all tests when:

1. **✅ Users can successfully generate redemption tokens**
2. **✅ Tokens expire automatically after 24 hours**
3. **✅ Each token can only be used once**
4. **✅ Users can view their complete redemption history**
5. **✅ All error scenarios are handled gracefully**
6. **✅ Security measures prevent unauthorized access**
7. **✅ Mobile experience is fully functional**
8. **✅ Performance is acceptable under normal load**

## 🚀 Go Live Checklist

After all tests pass:

1. **Clear all test data** from database
2. **Run final migration check**
3. **Deploy to production environment**
4. **Test critical paths** on production
5. **Monitor logs** for first 24 hours
6. **Set up monitoring/alerts** for errors
7. **Train business users** on redemption process
8. **Document any production-specific configurations**

---

**Testing Complete**: The redeem system is ready for production use when all tests in this guide pass successfully.