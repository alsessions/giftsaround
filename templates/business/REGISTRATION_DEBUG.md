# Business Registration Troubleshooting Guide

## Current Issue
Business signup form submits but:
- ❌ No user is created
- ❌ Form doesn't redirect to success page
- ❌ No error messages displayed
- ❌ Form just reloads

## Root Cause Analysis

### 1. User Registration Integration Missing or Misconfigured
The most likely cause is that Freeform's **User Registration Integration** is not properly configured. Without this integration, Freeform will save form submissions but will NOT create Craft users.

### 2. AJAX Form Submission Hiding Errors
The form has AJAX enabled (`"ajax":true` in logs), which may be silently failing and not displaying errors to the user.

## Step-by-Step Fix

### STEP 1: Verify Form Exists
1. Log into Craft CP
2. Go to **Freeform → Forms**
3. Look for "Business Signup" form
4. Verify handle is `businessSignup`

### STEP 2: Check User Registration Integration
1. Open "Business Signup" form in Freeform
2. Click **Integrations** tab
3. Look for "User Registration" integration
4. If missing, ADD IT:
   - Click "+ Add Integration"
   - Select "User Registration"

### STEP 3: Configure User Registration Integration

#### Required Field Mappings:
```
Craft User Field          →  Freeform Form Field
─────────────────────────────────────────────────
Username                  →  username
Email                     →  email
Password                  →  password
First Name                →  contactName (or create firstName field)
Last Name                 →  (leave blank or create lastName field)
```

#### Custom User Field Mappings:
These fields MUST exist in **Settings → Fields** and be added to **Settings → Users → User Fields Layout**:

```
Custom Field              →  Freeform Form Field
─────────────────────────────────────────────────
Business Name             →  businessName
Business Category         →  category
Business Phone            →  businessPhone
Business Address          →  businessAddress
Website                   →  website
```

#### User Group Assignment:
- **User Group**: Select "Business" (create this group if it doesn't exist)

#### Additional Settings:
- **Activate Account**: Enable if you want users to log in immediately
- **Send Activation Email**: Disable if you want immediate access
- **Send Notification Email**: Optional

### STEP 4: Create Missing Custom User Fields

If custom fields don't exist:

1. Go to **Settings → Fields**
2. Create a field group called "Business Fields"
3. Create these fields:

```
Field Handle: businessName
Field Type: Plain Text
Group: Business Fields

Field Handle: businessCategory (or map to category if using categories)
Field Type: Dropdown
Options: Food & Beverage, Retail, Entertainment, Health & Beauty, Services
Group: Business Fields

Field Handle: businessPhone
Field Type: Phone
Group: Business Fields

Field Handle: businessAddress
Field Type: Plain Text (multi-line)
Group: Business Fields

Field Handle: website
Field Type: URL
Group: Business Fields
```

4. Go to **Settings → Users → User Fields (Field Layout)**
5. Add all "Business Fields" to the layout

### STEP 5: Create Business User Group

1. Go to **Settings → Users → User Groups**
2. Create new group: "Business"
3. Set permissions as needed:
   - Access CP (if they need dashboard access)
   - Edit own user account
   - Any other business-specific permissions

### STEP 6: Disable AJAX (Temporary for Testing)

To see actual errors:

1. Open "Business Signup" form in Freeform
2. Go to **Settings** tab
3. Under "Behavior":
   - **Enable AJAX**: Turn OFF temporarily
4. Save form

This will make errors visible on the page.

### STEP 7: Test the Form

1. Visit `/business/signup-debug` (use the debug template created)
2. Fill out the form with test data
3. Submit
4. Check the debug output:
   - **Form Found**: Should say YES
   - **Recent Submissions**: Should show your test submission
   - **Field Errors**: Will show if any fields have issues

### STEP 8: Check Submissions vs Users

1. Go to **Freeform → Submissions**
2. Look for your test submission
3. If submission exists but user doesn't:
   - User Registration integration is missing or broken
4. Go to **Users** in CP
5. Check if the test user was created

## Common Problems & Solutions

### Problem: "contactName" field but form has "firstName"
**Solution**: Either rename the Freeform field to match or update the field mapping in User Registration integration.

### Problem: Custom fields not mapping
**Solution**: 
1. Verify custom fields exist in Craft
2. Verify they're added to User Field Layout
3. Verify field handles match exactly (case-sensitive)

### Problem: User created but missing custom data
**Solution**: Field mappings in User Registration integration are wrong or incomplete.

### Problem: "Category" field stores ID instead of label
**Solution**: The category field is pulling from Craft categories. Either:
- Map it to a custom user field that accepts category IDs
- Change to a simple dropdown with text values

### Problem: Form submits but nothing happens
**Solution**: 
1. Check storage/logs/web.log for PHP errors
2. Disable AJAX to see actual errors
3. Check browser console for JavaScript errors

## Quick Diagnostic URLs

- **Debug Form**: `/business/signup-debug`
- **Regular Form**: `/business/signup`
- **Success Page**: `/business/signup-success`

## Verify Everything Checklist

- [ ] Form "businessSignup" exists in Freeform
- [ ] User Registration integration is added to form
- [ ] All fields mapped in User Registration (username, email, password minimum)
- [ ] Custom user fields created in Craft
- [ ] Custom fields added to User Field Layout
- [ ] "Business" user group exists
- [ ] User Registration integration assigns to "Business" group
- [ ] Return URL set to: `business/signup-success`
- [ ] Test submission appears in Freeform → Submissions
- [ ] Test user appears in Users

## Files to Check

- `/templates/business/signup.twig` - Main form page
- `/templates/business/signup-debug.twig` - Debug version
- `/templates/freeform/formatting/business-signup.twig` - Form formatting
- `/templates/business/signup-success.twig` - Success page
- `storage/logs/web.log` - PHP errors

## Database Check (Advanced)

If you need to manually verify:

```bash
# Check Freeform submissions
ddev craft db:query "SELECT * FROM freeform_submissions ORDER BY dateCreated DESC LIMIT 5;"

# Check users
ddev craft db:query "SELECT id, username, email, dateCreated FROM users ORDER BY dateCreated DESC LIMIT 5;"
```

## Final Notes

The KEY to making this work is the **User Registration Integration**. Without it properly configured, Freeform will happily save submissions but will never create users. This is by design - Freeform doesn't assume you want to create users unless you explicitly tell it to.

Make sure EVERY required Craft user field has a corresponding Freeform field mapped in the integration, or the user creation will fail silently.