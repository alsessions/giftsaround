# Project Guidelines

- This is a Craft CMS 5 application. PHP dependencies are managed with Composer, and the `craft` executable is the project CLI.
- Local development runs in DDEV with nginx-fpm, PHP 8.4, MySQL 8.0, Composer 2, and `web/` as the document root. Run Craft and Composer commands through DDEV.
- Twig templates live in `templates/`. Custom application code lives in PSR-4-autoloaded modules under `modules/`; the `redeem` and `registration` modules are registered and bootstrapped in `config/app.php`.
- Craft configuration lives in `config/`. Version-controlled schema and settings live in `config/project/`; apply pending project-config changes after pulling relevant updates.
- Installed Craft plugins include CKEditor, Freeform, Image Optimize, SEOmatic, and CP Clear Cache. QR-code generation uses `endroid/qr-code`.
- Front-end styling uses Tailwind CSS 3. Its source is `src/css/tailwind.css`, its configuration is `tailwind.config.js`, and the generated stylesheet is `web/css/styles.css`.
- Use `npm run watch` while developing styles and `npm run build` for a minified production stylesheet. Tailwind scans Twig files under `templates/`.
- Alpine.js 3 is loaded from a CDN by the base Twig layout and is used for lightweight client-side behavior.
- Prefer simple implementations and native platform features over additional variables or functions.
- Prefer inline native Tailwind utility classes.
- Keep comments concise; assume contributors are technically proficient.
