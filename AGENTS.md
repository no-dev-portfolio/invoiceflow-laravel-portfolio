# InvoiceFlow maintenance
- Read README.md and docs/DESIGN.md before changes.
- Local synthetic-data demo; never commit .env, APP_KEY, DB files, sessions, logs, or vendor/.
- Preserve user ownership scopes, all-or-nothing writes, immutable issued invoices, integer yen, no overpayment and token idempotency.
- SQLite transactions use IMMEDIATE. Do not replace the real-process tests with mocks.
- Run composer validate --strict, php artisan test, and vendor/bin/pint --test before declaring a change verified.
- The Docker image supplies PHP/Composer; do not require changes to the host machine to review this project.
- Do not claim production, tax/legal compliance, third-party audit, or remote CI success without evidence.
