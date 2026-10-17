# Separate project and month viewports

`private/dashboard.html` now renders the existing project column in its own vertical viewport and the unchanged twelve monthly columns in a second viewport. Scroll positions and row heights are synchronized. Project labels cannot overlay monthly cells. Horizontal scroll snaps to month headers; trailing presentation space lets the final month align without clipping the first visible cell. The current-month control uses relative bounding rectangles rather than an offset that included the project pane.

Payer headings explicitly say “Pagador / intermediario”; child rows say “Proyecto:” and repeat the associated payer. Project grouping, charge calculations and financial engine are unchanged. This is a UI-only code change: no PHP, schema, authentication, cron or configuration changes.

## Executed checks

- `node --check` passed. Extracted financial engine compared byte-for-byte with the previous version: identical.
- Synthetic isolated browser fixture: row/header/footer alignment, separate service cell actions, both scroll directions, synchronized vertical scroll, and current-month positioning (8px from monthly viewport edge on phone).
- Final authenticated production version matched the prepared main script. Expanded view tested at320×568,390×844,844×390,932×430,768×1024,1024×768 and1440×900. Table heights260/450/260/260/767/565/697px. All twelve months retained, rows aligned, project/month viewport rectangles did not overlap, and page had no horizontal overflow.
- Production keyboard scrolling synchronized both panes. Escape and Back closed expansion and returned focus. Project buttons and service-period cells remained functional. Financial state re-exported after reload for an exact comparison with the scoped approved correction; no data or evidence screenshots committed here.

Physical iOS/Safari was not available; device sizes were emulated. PHP was not changed and no PHP CLI test is claimed.

Rollback: restore the separately retained private previous template. This does not roll back financial data; reconcile any later operations before considering a state restore through authenticated storage.
