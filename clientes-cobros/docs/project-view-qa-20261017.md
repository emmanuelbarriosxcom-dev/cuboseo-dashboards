# Project status and payer view — 2026-10-17

The annual table and month cards now default to Active projects. All retains inactive/paused client projects and closed service histories. Each service has its own row; explicit payer relationships group rows without a second monetary total. Clicking a service cell opens only that service's charges for that month. Search also matches explicit payer names.

Only `private/dashboard.html` changes application behavior. No schema, authentication, API, PHP validation, calendar, backup or production configuration changes are required. Existing expansion, layout preference and server storage mechanisms remain intact.

## Checks executed

- JavaScript syntax: `node --check` passed.
- Isolated Node checks with fictional clients: active scope, inactive clients, paused services, closed history retained in All, two services under one payer, payer-only row excluded, unique service rows, filtered services, and input state unchanged.
- Isolated browser fixture: payer search, separate service cells, All shows archived projects, service-cell dialog scoped to one service, expansion and Escape focus return.
- Authenticated production browser after installation: 320×568, 390×844, 844×390, 932×430, 768×1024, 1024×768 and 1440×900. Measured table viewport heights: 260, 400, 260, 260, 767, 565 and 697 px respectively. All retained 12 month columns, sticky first column and totals, internal scrolling and no horizontal page overflow. Fullscreen API not used.
- Production: internal keyboard scrolling, last month accessible, Escape and Back restore focus, Active/All switching, separate service rows, and no captured browser error/warning logs.
- Production state exported after reload and compared with the approved scoped correction: exact match; unrelated records and settings preserved. Financial data and private evidence intentionally omitted from this repository.

These were emulated browser sizes, not physical iPhone/iPad or iOS Safari tests. PHP CLI was unavailable locally; no PHP file was modified and no PHP lint pass is claimed.

## Rollback

Restore the private copy of the previous dashboard template. UI rollback does not revert or delete financial records. A financial rollback must use the separately retained private pre-change state after checking for subsequent edits, through the authenticated version-checked storage flow. Never restore blindly over new operations.
