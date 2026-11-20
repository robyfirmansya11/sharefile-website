# Sharefolder Web

A web-based file manager for shared workspaces, built with Laravel, Vue, and Inertia. Browse folders, upload documents, preview files, and manage local staff accounts through a multilingual interface.

> **Project status:** local development and demo workflows are implemented. The Windows/IIS/Active Directory integration is experimental and has **not been validated against a real AD/SMB deployment**. This repository does not claim production readiness or independently audited security.

## Features

- Folder navigation without full-page reloads, list/grid views, multi-select, context menus, and keyboard shortcuts.
- File upload with drag and drop, per-file progress, download, rename, and new folders/text files.
- Application clipboard for copy, cut, and paste between folders; existing files are not silently overwritten.
- Recursive filename search with folder locations and links to the original folder.
- Image, UTF-8 text, and PDF previews; PDF page navigation and zoom.
- Local Recycle Bin with owner-scoped listing, restore, and a separate permanent-delete confirmation.
- Personal file activity history with filters and pagination.
- IT-only staff registration, enforced on the backend; public registration is disabled.
- Indonesian, English, and Simplified Chinese, with persistent language selection and localized dates/numbers.
- Light and dark themes.

## Architecture and access model

| Component | Responsibility |
| --- | --- |
| Laravel 13 / PHP 8.3+ | Authentication, authorization, filesystem operations, validation, and activity records |
| Vue 3 / Inertia 3 / TypeScript | File manager, forms, dialogs, and language selection |
| Tailwind CSS / Vite Plus | Styling and asset builds |
| MySQL or SQLite | Application accounts, sessions, cache locks, and activity records |
| Filesystem | File contents and local Recycle Bin metadata/payloads |
| PDF.js | Locally bundled PDF rendering; documents are not sent to an external preview service |

### Local mode

`SHARE_DRIVER=local` is restricted to the `local` and `testing` application environments. By default, files live in `storage/app/share-demo`.

**All verified local accounts use the same configured file root.** Department-level permissions are not implemented in this mode. IT administrator status controls staff registration; it does not establish separate file permissions. Recycle Bin entries and activity history are scoped to their respective user.

Use an isolated test folder with non-sensitive files. Do not point local mode at a company share to bypass Windows authorization.

### Windows mode — experimental

The intended design uses IIS Windows Authentication and PHP FastCGI impersonation so file operations follow each Windows user's identity. Authentication alone does not prove that SMB/NTFS authorization is correct.

There is no service-account fallback. Start with writes disabled and validate effective permissions with real accounts before considering deployment. Local staff registration and the local Recycle Bin are unavailable in Windows mode; delete operations are blocked rather than falling back to permanent deletion.

See [Windows deployment guide](DEPLOYMENT-AD.md) for prerequisites, limitations, and acceptance checks.

## Requirements

- PHP 8.3+ and extensions required by the locked Composer dependencies. Use `composer check-platform-reqs` to verify the installed environment.
- Composer 2.
- A Node.js version supported by the dependencies in `package-lock.json`, and npm.
- MySQL with `pdo_mysql`, or SQLite with `pdo_sqlite`. Automated tests use SQLite in memory.
- A writable, isolated local file root. Laravel also needs writable runtime directories under `storage` and `bootstrap/cache`.
- SMTP configured for real password-reset and verification email delivery. `MAIL_MAILER=log` is for development only.

## Local installation

Examples below use PowerShell. Keep credentials in your local `.env`, never in source control.

```powershell
git clone https://github.com/robyfirmansya11/sharefile-website.git
cd sharefile-website
composer install
npm ci
Copy-Item .env.example .env
php artisan key:generate
```

Copy `.env.example` only for a fresh installation. Do not overwrite an existing `.env` or regenerate an established application's key during upgrades.

### Configure the database

For MySQL, create a dedicated empty application database and a database account with appropriate access, then configure:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sharefolder
DB_USERNAME=sharefolder_app
DB_PASSWORD="REPLACE_LOCALLY"
```

Laragon can provide the local MySQL service. Do not use a production administrator database account for the application.

Alternatively, keep `DB_CONNECTION=sqlite` from `.env.example` and create the database file if it does not exist:

```powershell
if (-not (Test-Path database/database.sqlite)) {
    New-Item -ItemType File database/database.sqlite
}
```

### Configure local storage and start

```dotenv
APP_ENV=local
APP_URL=http://localhost:8000
SHARE_DRIVER=local
# Optional: use a dedicated test directory instead of storage/app/share-demo.
# SHARE_LOCAL_ROOT='D:\Sharefolder-Demo'
```

```powershell
php artisan migrate
npm run build
php artisan serve
```

Open `http://localhost:8000/login`. For frontend development, run `npm run dev` in a second terminal. Herd may serve the application instead of `php artisan serve`; set `APP_URL` to the URL you actually use.

### Set up the first IT administrator

There is no public registration endpoint or default administrator password. An authorized operator must provision the first local account. Do not use the sample database seeder for real onboarding.

If an account already exists, grant the role explicitly:

```powershell
php artisan staff:admin admin@example.com
```

For a fresh database, configure working email delivery first. Run `php artisan tinker` and create the initial account with your real administrator name/email and a randomly generated password that is not displayed:

```php
$user = new App\Models\User;
$user->name = 'IT Administrator';
$user->email = 'admin@example.com'; // Replace with your administrator's address.
$user->password = bin2hex(random_bytes(32)); // Hashed by the User model.
$user->save();
exit;
```

Then run `staff:admin` for that address. Use **Forgot password** on the login page to set a private password through the emailed reset link, sign in, and complete email verification. The admin menu requires both the role and a verified email. This procedure does not mark an email verified or create an AD account.

To revoke the application role:

```powershell
php artisan staff:admin admin@example.com --revoke
```

In **Staff registration**, IT can create ordinary staff accounts and review verification status. New accounts cannot gain admin status through the form. If email processing fails after account creation, the account remains saved; correct mail delivery and request verification again rather than creating a duplicate.

With `MAIL_MAILER=log`, emails are written to private application logs, not delivered to inboxes. These logs may contain verification/reset links: do not publish them or use this mail mode for real onboarding.

## Limits and behavior

| Area | Current behavior |
| --- | --- |
| Upload | Up to 20 files per queue, 25 MB per file; folder upload is not supported |
| Image preview | PNG/JPEG/GIF/WebP, up to 5 MB and 40 megapixels |
| Text preview | UTF-8 TXT/MD/CSV/JSON/LOG, up to 1 MB |
| PDF preview | Up to 10 MB; visual canvas preview, page controls, 50–200% zoom |
| Other formats | Download available; Office preview is not implemented |
| Search | Names only, up to 100 results; bounded scan/time budget; incomplete results are indicated |
| Clipboard | Internal application clipboard, not Windows Explorer clipboard |
| Recycle Bin | Local mode only; no automatic expiry; restore never overwrites existing names |
| Localization | Core file workflows and account onboarding; advanced starter-kit settings are not fully translated |

A completed transfer is not necessarily a completed save: wait for the upload's final status. After a timeout, refresh before retrying because the server may already have saved the file.

Filesystem operations are not transactional. A failed batch or recursive operation may leave partial results. Application cache locks do not lock changes made through Explorer or another external process.

## Security and operational boundaries

- Never commit `.env`, application keys, passwords, tokens, database dumps, logs, or user documents. `.env.example` contains placeholders only.
- Serve only the `public` directory. Keep the file root outside the public web directory.
- Admin pages enforce authorization server-side. The local admin role does not create or expand AD/SMB/NTFS permissions.
- File handling rejects common traversal, absolute-path, Windows device-name, alternate-stream, symbolic-link, and overwrite cases. These checks do not establish protection against every reparse-point race or external filesystem writer.
- Uploaded files are not antivirus-scanned by this application. Add an appropriate scanning workflow before accepting untrusted files in production.
- PDF previews do not enable interactive scripting or forms. Download documents for text copying/search, forms, or screen-reader use.
- Activity records are not tamper-proof. A database administrator can modify them; a prepared download is not proof of complete receipt. Audit records currently cover file workflows, not every account-management action.
- Back up the database, file root, and its `.sharefolder-trash` directory. Recycle Bin is not a backup; permanent deletion is not recoverable through the application.
- Retention policies, storage quotas, and production AD write validation remain deployment work.

If credentials were ever committed, removing them from the latest version is insufficient: rotate them and assess repository-history cleanup separately. Do not post secrets or private server diagnostics in public issues.

## Validation

```powershell
npm run types:check
npm run build
node scripts/check-locales.mjs
node scripts/check-pdf-preview.mjs
php vendor/bin/pest --compact
```

On Windows, if SQLite extensions are installed but disabled in the CLI configuration:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest --compact
```

Tests use SQLite in memory and temporary folders rather than the configured user file root. They cover file operations, path restrictions, Recycle Bin ownership, activity isolation, search, localization, staff authorization, and simulated Windows request validation. They do not validate real IIS impersonation, Kerberos delegation, or AD ACL enforcement.

`npm run build` prepares PDF assets in `public/pdfjs`. Include that generated directory alongside `public/build` in deployment artifacts. Neither generated assets nor dependencies are committed; install and build from the lockfiles.

## Repository guide

| Path | Purpose |
| --- | --- |
| `app/Services` | File access, Recycle Bin, and Windows identity checks |
| `app/Http/Controllers` | File, history, search, and staff endpoints |
| `resources/js/pages` | Dashboard, authentication, and IT registration pages |
| `resources/js/components` | File dialogs, previews, theme and language controls |
| `resources/js/i18n` and `lang` | Frontend and backend translations |
| `tests/Feature` | Application and authorization tests |
| `deployment/iis` | Example IIS configuration and read-only inspection script |

For changes, include the problem, expected behavior, relevant validation, and any deployment impact in the pull request. Use synthetic data in screenshots and bug reports.
