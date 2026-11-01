# CareerMentors Student Admission CRM

Laravel + MySQL CRM for crm.careermentors360.com. Phase 2: core CRM with DUMMY data only.

## What is built (Phase 2)
- Login with lock-out after 5 wrong tries; two-step login (authenticator app) required for the Super Admin
- Roles: Super Admin, Team Lead (own team only), Counsellor (own leads only)
- Staff and teams page: add staff, move Counsellors between teams, disable accounts, hand over leads
- Students: one record per standard mobile number; repeat enquiries attach to the same student
- Duplicate review list for doubtful cases, with merge
- Call history: insert-only; corrections are new entries
- Status, priority, follow-ups (today / overdue / next 7 days / needs attention), dashboards
- Search and filters with page-by-page results

## Phase 3
- Website sign-up API `POST /api/v1/leads` (one key per website, switch on/off, per-website limit, spam trap, consent required)
- Websites page: connect a site, create/replace its key, see sign-up counts
- Import Center: .xlsx/.csv upload with source name and date, column matching, preview, 300-row batches via the queue, history and rejected rows
- Export (Super Admin only, logged, formula-safe CSV)
- Settings: statuses, sources, courses, exams, colleges; activity log
- Backups: encrypted (libsodium) dump of every table, 7 daily / 4 weekly / 3 monthly kept, failure email, monthly restore test into a separate database; destination local or Google Drive (drive.file scope)

## Hostinger cron (one line, every minute)
```
* * * * * cd /path/to/crm && php artisan schedule:run >> /dev/null 2>&1
```
This runs the import queue, the 2:00 AM backup and the monthly restore test (Asia/Kolkata time).

## Backup commands
- `php artisan crm:backup-key` – create the encryption key (keep a copy off the server)
- `php artisan crm:backup` – back up now
- `php artisan crm:backup-restore-test` – restore newest backup into the test database and compare
- `php artisan crm:backup-decrypt <file> <out>` – unlock a downloaded backup

## Run locally
```
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan db:seed --class=DemoDataSeeder   # dummy staff + 500 dummy students, password Demo-pass-2026
php artisan serve
php artisan test
```
Set `CRM_REQUIRE_ADMIN_2FA=false` in `.env` only for local demos.
