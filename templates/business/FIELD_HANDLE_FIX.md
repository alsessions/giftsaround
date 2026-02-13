# CRITICAL FIX: Field Handle Mismatch

## Problem Found!
The User Registration integration is mapping to the wrong field handles. The integration expects `businessWebsite` but Craft has `website`.

## Quick Fix Steps

### Step 1: Check Your Craft User Field Handles
Go to **Settings → Fields** and verify the EXACT handles:

```
Expected in Craft:
- businessName (handle)
- businessPhone (handle)
- businessAddress (handle)
- website (handle) OR businessWebsite (handle)
- businessCategory (handle) OR category (handle)
```

### Step 2: Update Integration Field Mapping

Go to: **Freeform → Forms → Business Signup → Integrations → User Registration**

#### In "Field Mapping" section, change:

**Current (WRONG):**
```
Craft Field              Freeform Field
businessWebsite    →     Website field
```

**Should be (CORRECT):**
```
Craft Field              Freeform Field
website            →     Website field
```

### Step 3: Verify All Mappings Match Exactly

Make sure these match EXACTLY (case-sensitive):

```
Craft User Field Handle     →    Freeform Form Field
─────────────────────────────────────────────────────
businessName                →    businessName
businessPhone               →    businessPhone  
businessAddress             →    businessAddress
website                     →    website
category OR businessCategory →   category
```

### Step 4: Check if Fields Exist in Craft

Run this command to see all user field handles:

```bash
ddev craft db:query "SELECT handle, name FROM fields WHERE context = 'global' ORDER BY name;"
```

OR check in CP: **Settings → Fields**

### Step 5: Save and Test

1. Save the integration
2. Save the form
3. Test at `/business/signup-debug`
4. Check if user is created in **Users** section

## Common Mistakes

### Mistake 1: Field doesn't exist in Craft
**Solution:** Create the missing field in Settings → Fields

### Mistake 2: Field exists but not in User Layout
**Solution:** Settings → Users → User Fields → Add the field

### Mistake 3: Field handle mismatch (businessWebsite vs website)
**Solution:** Either:
- Rename the Craft field handle to match the integration, OR
- Update the integration to use the correct Craft field handle

### Mistake 4: Category field stores ID, not value
**Solution:** This is normal - categories store as IDs. Make sure:
- The category field in Craft accepts category IDs (relation field)
- OR change Freeform field from Categories to simple dropdown with text values

## Verification Checklist

After fixing:

- [ ] All Craft user fields exist in Settings → Fields
- [ ] All fields added to User Field Layout (Settings → Users → User Fields)
- [ ] Integration field mappings use correct Craft field handles
- [ ] Test submission creates a user in Users section
- [ ] User has all custom field data populated
- [ ] User is assigned to "Business" group

## If Still Failing

Check `storage/logs/web.log` for errors:

```bash
ddev exec tail -50 storage/logs/web-2026-02-12.log | grep -i error
```

Common errors:
- "Field does not exist" - Create the field
- "Invalid field value" - Check data type compatibility
- "User validation failed" - Check required fields are mapped