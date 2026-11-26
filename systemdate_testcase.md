# System Date — browser test cases

Spec: `tests/e2e/systemdate.spec.mjs`. Run headed: `npx playwright test tests/e2e/systemdate.spec.mjs --headed --reporter=line`.

| Case | What is checked | Status |
|---|---|---|
| SD-01 | Top bar shows the System Date after the branch switch; today by default, not highlighted | PASS |
| SD-02 | A back date is amber with "Back-dated", shown on every page with the notice, and kept after sign-out / sign-in | PASS |
| SD-03 | A future date is allowed and labelled "Future"; Follow today returns to today | PASS |
| SD-04 | POS time, purchase date, receipt date and sale history open on the System Date | PASS |
| SD-05 | Cash of a back date is refused while today's session is open; popup explains why with Go to Counter Cash / Change System Date | FAIL (test selector, fixed, not re-run) |
| SD-06 | A back-dated cash sale is dated the System Date and goes into that day's session | Not run |
| SD-07 | Voiding today's sale on a back date is blocked; popup names the sale, explains why and offers the fixes | Not run |
| SD-08 | POS offers only stock that was free on the System Date | Not run |
| SD-09 | Each branch keeps its own System Date | Not run |
| SD-10 | System Date changes are in the audit log | Not run |

## Progress
| Date | Run | Result | Notes |
|---|---|---|---|
| 2026-11-26 | Run 2, portable PHP 8.3 + MySQL 8.4, fresh DB | SD-01..04 PASS, SD-05 FAIL (test-only), SD-06..10 not run | Other suites: 20 passed, 4 failed (CS-03, E2E-004, MB-SETUP, RT-03) not yet compared with main. |
