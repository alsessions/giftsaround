# Changelog

All notable project changes are documented here.

This changelog was generated from the fetched remote git history for `origin/main` and `origin/master` on 2026-08-09. The project does not currently use release tags, so entries are grouped by date and feature milestone. `origin/master` contains the February 25 hotfix also represented on `origin/main`, so the changelog follows `origin/main` as the canonical history.

## Current - 2026-08-09

### Added

- Added a reusable header partial and refreshed site navigation in the base layout.
- Added header search access and updated the search results template.
- Added a front page carousel with supporting slider behavior.
- Added the dedicated `modules/registration` Craft module and controller for payment-gated registration.
- Added registration migrations for payment-gated registration setup and redirect restoration.
- Added paid-registration password validation handling.
- Added and refined Mohawk Valley form/template work.

### Changed

- Refined home page, carousel, business entry, hero, registration, and global CSS styling.
- Updated business signup and signup success templates.
- Updated account, profile, activation, email verification, and logout templates.
- Updated Freeform user registration formatting for the paid registration flow.
- Refined redemption templates for business redemption, redemption history, validation, admin redemptions, and QR testing.
- Updated redemption routes, module bootstrap behavior, and controller handling.

### Fixed

- Fixed redemption breakage across routes, controller handling, QR output, and redemption history.
- Removed an earlier paid-registration password field migration after the registration flow changed.

## 2026-08-04 to 2026-08-05

### Added

- Added a category listing section to the home page.
- Added index icons.
- Added redemption calendar logic.

### Changed

- Applied broad visual updates across the base layout, home page, contact section config, and compiled CSS.
- Refined business entry pages with updated hero, wrapper, gradient, and page styling.
- Updated Craft dependencies.

## 2026-06-11

### Changed

- Updated Craft dependencies.

## 2026-05-22 to 2026-05-24

### Added

- Added payment processing routes and templates for registration completion and failed payment states.
- Added payment-gated user registration workflow documentation.
- Added/reintroduced a verification template for the payment-gated registration flow.

### Changed

- Iterated heavily on Stripe/Freeform registration completion handling.
- Added safety checks around registration completion.
- Updated the Redeem module bootstrap behavior.

## 2026-05-17 to 2026-05-21

### Added

- Added the Freeform user registration formatting template.
- Added SEOMatic dependency/configuration updates.

### Changed

- Updated Stripe and Craft dependencies.
- Refined registration form layout, copy, spacing, and styles.
- Refined Freeform field styling.
- Updated base layout, business entry templates, and compiled CSS with broader style adjustments.
- Refined business entry row layouts.
- Updated project section configuration for business, business listings, contact, Mohawk Valley, and privacy policy sections.

## 2026-05-03 to 2026-05-12

### Added

- Added Mohawk Valley Living section/template work.
- Added specials display to business listing and category pages.

### Changed

- Updated DDEV config, environment handling, Craft executable, deploy script, and storage gitignore files.
- Removed old SQL archive files from the repo.
- Updated user redirects and registration template behavior.
- Refined registration form functionality, wording, spacing, and styles.
- Added telephone number display updates for business and category templates.
- Updated schema configuration for body, business description, and business Instagram fields.
- Increased latest businesses display limit on the home page to 8.
- Refined home page listing and front page content.
- Updated Craft/Freeform dependencies.

## 2026-03-01 to 2026-03-28

### Changed

- Updated Craft dependencies.
- Updated email verification handling in the Redeem module.
- Refined business signup logic and signup/account/register success templates.
- Removed older verification templates that were no longer used.
- Removed CKEditor config from project config during Craft updates.

## 2026-02-25 to 2026-02-28

### Added

- Added business profile/social fields for Facebook, Instagram, business hours, and year established.
- Added GraphQL project configuration and schema config.
- Added privacy policy entry type, section, and template.

### Changed

- Migrated/refined the business address field and updated related business, category, account, profile, and signup templates.
- Refined business signup logic, Freeform submit handling, signup form width, and contact form styles.
- Updated logo image usage in the base layout.
- Hardened and iterated on deployment script behavior, permissions, and cache clearing.
- Updated Craft dependencies and project config after rebase/hotfix work.
- Updated category and business listing CSS and pagination.
- Updated front page listing and contact form behavior.
- Updated general token/Craft naming configuration.

### Fixed

- Fixed redemption token handling in business entry, redemption, redemption history, and QR test templates.
- Removed obsolete deployment, QR, redemption, testing, and registration debug documentation.
- Applied hotfix repair to `composer.lock` and storage gitignore files.

## 2026-02-10 to 2026-02-12

### Added

- Added Freeform business signup flow and business signup formatting template.
- Added `businessName` user field/project configuration.
- Added user field layout updates for business signup.
- Added business profile, signup debug, and signup old templates.
- Added address migration tooling in `migrate-addresses.php`.
- Added Freeform export archive.
- Added Prettier configuration.

### Changed

- Refactored business signup and address handling across project config and templates.
- Refined business entry buttons, pagination, and entry CSS.
- Completed ImageOptimize rendering/configuration work across business and category pages.
- Tested and adjusted WebP/image variant handling.
- Updated `composer.lock`.

## 2026-02-04 to 2026-02-07

### Added

- Added login/admin access requirements for admin redemptions.
- Added field volume settings for optimized images.

### Changed

- Updated Craft and Freeform dependencies.
- Updated logo path, UI tweaks, and CSS baseline.
- Updated deploy script cache flush behavior.
- Enhanced category image rendering and added frontend formatting support.
- Updated image rendering in business and category templates.
- Improved monthly special and one-special redemption checks.
- Updated redemption logic in business entries and the Redeem controller.

### Fixed

- Prevented duplicate monthly special redemption.

## 2026-01-28 to 2026-01-30

### Added

- Added business description field and related business/category display updates.
- Added optimized image field configuration.
- Added search template.
- Added payment failed template.

### Changed

- Switched registration toward Freeform and Stripe templates.
- Updated register template behavior and styles.
- Enhanced search in the base layout and CSS.
- Replaced map-related handling with ImageOptimize workflow.
- Updated deploy script iterations.
- Displayed category body content on category pages.
- Updated Craft/Freeform dependencies and project config.

## 2025-12-30 to 2026-01-03

### Added

- Added QR code generation and routing for redemption flows.
- Added QR validation and test templates.
- Added admin redemptions management UI.
- Added deployment authentication setup documentation.

### Changed

- Polished dark theme, navbar, category pages, business entries, home page, and redemption UI.
- Updated redemption history, business redemption, account access, and QR-related controller behavior.
- Updated deployment script behavior and `.gitignore`.
- Rebuilt Craft project config.

### Fixed

- Removed an erroneous redemption error message.

## 2025-10-19 to 2025-10-23

### Added

- Added environment example files for dev, staging, and production.
- Added deployment script for Git/Craft deployment.
- Added Craft CKEditor and Freeform integrations.
- Added frontend build tooling with Tailwind, package scripts, and compiled CSS.
- Added contact section and template.

### Changed

- Refactored project structure for readability and maintainability.
- Updated Composer dependencies.

## 2025-09-25

### Added

- Added the custom Craft Redeem module, routes, controller, token record, install migration, and module migration.
- Added business redemption, redemption history, and test redemption templates.
- Added redemption system/testing documentation.

### Changed

- Added redemption access to business entries and user account pages.
- Converted token status handling to booleans.

## 2025-09-02 to 2025-09-19

### Added

- Initial Craft CMS project setup with DDEV, Composer, Craft bootstrap, config, web entrypoint, and base templates.
- Added business listings, category, account, login, logout, registration, register success, profile, and business signup templates.
- Added Craft project config for business content, business listings, categories, fields, sites, volumes, and user groups.
- Added user verification and activation templates.
- Added member specials support and Clear Cache plugin dependency.

### Changed

- Updated `.gitignore`.
- Refined monthly special field behavior and business entry display.
- Removed the earlier monthly special entry type in favor of updated specials fields.
