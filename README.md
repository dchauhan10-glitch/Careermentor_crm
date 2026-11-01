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

Not yet: website sign-up API, Import Center, exports, backups (Phase 3).

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
