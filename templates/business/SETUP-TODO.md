# Freeform Business Registration Setup - TODO

## 1. Install/Enable Freeform
- [ ] Run: `ddev composer require solspace/craft-freeform`
- [ ] Run: `ddev craft plugin/install freeform`
- [ ] Verify Freeform appears in CP sidebar

## 2. Create User Group & Permissions
- [ ] Go to Settings → Users → User Groups
- [ ] Create "Business" user group
- [ ] Set permissions:
  - [ ] Access CP (if needed for business dashboard)
  - [ ] Edit entries in Business section
  - [ ] Access business-specific areas

## 3. Create Custom User Fields
- [ ] Go to Settings → Fields → Create fields:
  - [ ] `businessName` (Plain Text, Group: Business Fields)
  - [ ] `businessCategory` (Dropdown, Group: Business Fields, Options: Food & Beverage, Retail, Entertainment, Health & Beauty, Services)
  - [ ] `businessPhone` (Phone, Group: Business Fields)
  - [ ] `businessAddress` (Address, Group: Business Fields)
  - [ ] `website` (URL, Group: Business Fields)
- [ ] Go to Settings → Users → User Fields (field layout)
- [ ] Click "+ New field" and add each Business Fields field to the layout
- [ ] Configure field settings in layout (required, translation method, etc.)

## 4. Create Freeform Form in CP
- [ ] Go to Freeform → Forms → New Form
- [ ] Name: "Business Signup"
- [ ] Handle: `businessSignup`
- [ ] Add fields (in order):
  - [ ] businessName (Text, required)
  - [ ] businessCategory (Select, required)
  - [ ] firstName (Text, required)
  - [ ] lastName (Text, required)
  - [ ] email (Email, required)
  - [ ] businessPhone (Phone, required)
  - [ ] businessAddress (Textarea, required, instructions: "Include street, city, state, and ZIP code")
  - [ ] website (Text, optional, placeholder: "https://www.yourbusiness.com")
  - [ ] username (Text, required, instructions: "Choose a unique username for your business account")
  - [ ] password (Password, required, instructions: "Must be at least 8 characters")
  - [ ] confirmPassword (Password, required)
  - [ ] terms (Checkbox, required, label: "I agree to the <a href='#'>Business Terms of Service</a> and <a href='#'>Privacy Policy</a>")
  - [ ] marketing (Checkbox, optional, label: "I'd like to receive marketing tips and updates about new features")

## 5. Configure Form Settings
- [ ] Go to form Settings tab
- [ ] Formatting Template: Select `business-signup.twig`
- [ ] Success Behavior: Redirect to URL
- [ ] Return URL: `business/signup-success`

## 6. Configure User Registration Integration
- [ ] In form, go to Integrations tab
- [ ] Click "Add Integration" → User Registration
- [ ] Map fields:
  - [ ] Username → `username` field
  - [ ] Email → `email` field
  - [ ] Password → `password` field
  - [ ] First Name → `firstName` field
  - [ ] Last Name → `lastName` field
  - [ ] Business Name → `businessName` custom field
  - [ ] Business Category → `businessCategory` custom field
  - [ ] Business Phone → `businessPhone` custom field
  - [ ] Business Address → `businessAddress` custom field
  - [ ] Website → `website` custom field
- [ ] User Group: Select "Business"
- [ ] Email Verification: Enable if desired
- [ ] Save integration

## 7. Verify Templates Are In Place
- [ ] `templates/business/signup.twig` (updated with Freeform)
- [ ] `templates/freeform/formatting/business-signup.twig` (custom formatter)
- [ ] `templates/business/signup-success.twig` (already exists)
- [ ] Backup old signup: Rename current `signup.twig` to `signup-old.twig`

## 8. Test Registration Flow
- [ ] Visit `/business/signup`
- [ ] Fill out form with test data
- [ ] Submit form
- [ ] Verify:
  - [ ] User created in CP
  - [ ] User in "Business" group
  - [ ] Custom fields populated
  - [ ] Redirected to success page
  - [ ] Email verification sent (if enabled)

## 9. Optional Enhancements
- [ ] Add email notification to admin on business signup
- [ ] Configure spam protection (Freeform has built-in options)
- [ ] Add reCAPTCHA or hCaptcha
- [ ] Create email template for business welcome email
- [ ] Set up business profile completion workflow

## Notes
- Form handle must match in template: `craft.freeform.form('businessSignup')`
- All templates use Tailwind CSS and Alpine.js
- Password validation handled by Freeform
- CSRF protection automatically included