# System Date — browser test cases

Spec: `tests/e2e/systemdate.spec.mjs`. Run headed: `npx playwright test tests/e2e/systemdate.spec.mjs --headed --reporter=line`.

| Case | What is checked | Status |
|---|---|---|
| SD-00 | Setup: filled opening stock dated 10 days back (free on the back date) | PASS |
| SD-01 | Top bar shows the System Date after the branch switch; today by default, not highlighted | PASS |
| SD-02 | A back date is amber with "Back-dated", shown on every page with the notice, and kept after sign-out / sign-in | PASS |
| SD-03 | A future date is allowed and labelled "Future"; Follow today returns to today | PASS |
| SD-04 | POS time, purchase date, receipt date and sale history open on the System Date | PASS |
| SD-05 | Cash of a back date is refused while today's session is open; popup explains why with Go to Counter Cash / Change System Date | PASS |
| SD-06 | A back-dated cash sale is dated the System Date and goes into that day's session | PASS |
| SD-07 | Voiding today's sale on a back date is blocked; popup names the sale, explains why and offers the fixes | PASS |
| SD-08 | POS offers only stock that was free on the System Date | PASS |
| SD-09 | Each branch keeps its own System Date | PASS (full run with 2 branches; skips on a 1-branch DB) |
| SD-10 | System Date changes are in the audit log | PASS |

## Progress
| Date | Run | Result | Notes |
|---|---|---|---|
| 2026-11-26 | Run 2, portable PHP 8.3 + MySQL 8.4, fresh DB | SD-01..04 PASS, SD-05 FAIL (test-only), SD-06..10 not run | Other suites: 20 passed, 4 failed (CS-03, E2E-004, MB-SETUP, RT-03) not yet compared with main. |
| 2026-11-26 | Full suite, one spec at a time, fresh DB | 95 pass / 14 fail / 2 skip | No failure from System Date code. Fails: UI-09 (single-threaded PHP test server), multibranch MB-13/16/17/18 (absolute stock totals after other specs), MB-27/30 (back-dated cash session left open by SD tests, now cleaned up), runtime E2E-004..007/012/013 (QA seed customer QA-E2E-CUST and C6 type not in repo). |
| 2026-11-26 | SD fixes re-run, fresh DB | SD-00..08, SD-10 PASS; SD-09 skip (1 branch) | Added SD-00 stock setup, afterEach reset (date today, cash closed), SD-07 real time, popup reload wait. |
| 2026-11-27 | Full gate run 9: fresh DB, migrations twice, all specs one at a time on nginx + 4 php-cgi | **115 / 115 PASS, 0 fail** | pricing 57, custody 9, multibranch 23, systemdate 11, runtime 15. Fixes since run 1: local assets (no CDN), transfer date "now" window, overnight cash session when following today, runtime QA seed, multibranch stock baseline, test timing fixes. |
