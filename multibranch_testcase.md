# Multi-Branch — Test Cases

Design: `ARCHITECTURE.md` → Multi-Branch. Migration: `044_20261119_multi_branch.sql`.

Record PASS / FAIL and the exact error in the Status column.

## Setup

1. Back up the database.
2. Sign in. The pending migration 044 runs from the login page.
3. Check: the existing branch is now the Head Office (Configuration → Branches shows the **Head Office** badge), and all old sales, stock and customers are still there.
4. Create branch **B2** (Configuration → Branches → Add Branch).
5. Users / Roles: create `mgr2` with B2 = MANAGER and Head Office = CASHIER (default B2). Create `cash2` with only B2 = CASHIER.

## Cases

| ID | Area | Steps | Expected | Status |
|---|---|---|---|---|
| MB-01 | Login | Sign in as `cash2` (one branch) | Goes straight to the B2 dashboard | |
| MB-02 | Login | Sign in as `mgr2` | Branch cards for Head Office and B2; your role shown on each card | |
| MB-03 | Login | Pick B2 card | B2 dashboard; top bar shows B2; menu matches MANAGER | |
| MB-04 | Switch | Top bar → Head Office | Dashboard of Head Office; menu matches CASHIER | |
| MB-05 | Admin | Sign in as admin | Cards for every branch, including B2 without assigning it | |
| MB-06 | Tab guard | Open POS in tab A on Head Office. In tab B switch to B2. Save a sale in tab A | Sale is refused with "You switched to branch…"; nothing posted at either branch | |
| MB-07 | Access | Remove `mgr2`'s B2 access while signed in on B2, then reload | Sent to the branch picker; B2 no longer offered | |
| MB-08 | Customers | At B2 create customer C-B2 with "Home branch only" | Visible at B2; not listed at Head Office POS / Receipts / Customers | |
| MB-09 | Customers | Shared customer with opening balance 1,000 (home = Head Office) | OS 1,000 at Head Office, 0 at B2 | |
| MB-10 | Credit | Shared customer, limit 5,000, credit scope "This branch". OS 4,000 at HO. Credit sale 3,000 at B2 | Allowed (B2 OS 3,000) | |
| MB-11 | Credit | Same with credit scope "All branches combined" | Refused: combined OS would be 7,000 > 5,000; message shows other-branch OS | |
| MB-12 | Purchase | At B2 open Purchasing | Purchases / Supplier Payments hidden; direct URL redirects with Head Office message | |
| MB-13 | Purchase | At Head Office: one purchase, line 1 filled 11.8 kg × 10 receive at HO, line 2 × 5 receive at B2 | One purchase, one supplier payable; HO stock +10, B2 stock +5 | |
| MB-14 | Purchase | Refill-empty line received at B2 with more cylinders than B2 has empty | "Stock not available" on the line; server refuses too | |
| MB-15 | Purchase | Void the purchase from MB-13 (nothing sold) | Stock removed from both branches; payable reversed | |
| MB-16 | Transfer | At HO send 3 filled + 2 empty to B2 | HO stock drops at once; transfer "In transit"; Inventory Detail shows 5 in transit | |
| MB-17 | Transfer | At B2 open the incoming transfer, untick 1 empty, set one filled to 1 KG less, confirm | B2 stock +3 filled (one 1 KG short), +1 empty; the missing empty is back at HO | |
| MB-18 | Transfer | Send again, then cancel at HO with a reason | All cylinders back in HO stock; B2 cannot receive it | |
| MB-19 | Transfer | Sell gas at B2 from a transferred cylinder | Sale cost (profit) uses the cost carried from HO, not 0 | |
| MB-20 | Reports | Reports with "All branches" | Totals plus By Branch table; Sales Detail has a Branch column | |
| MB-21 | Reports | Customer Balances by Branch, All branches | One column per branch plus Combined; opening only under the home branch | |
| MB-22 | Reports | Sign in as `cash2` (no REPORT_ALL_BRANCHES) and add `?branches=all` to a report URL | Still shows B2 only | |
| MB-23 | Security | At B2 open `/sales/details/{id}` of a Head Office sale | Not found | |

## Progress

Automated: `tests/e2e/multibranch.spec.mjs` (run on a fresh database: schema.sql, then the migrations run from the login page).
`LPG_SLOWMO=0 npx playwright test tests/e2e/multibranch.spec.mjs --reporter=line`

| Date | Environment | Result |
|---|---|---|
| 2026-11-19 | PHP 8.5.12 + Apache 2.4.71 + MySQL 8.4.13 (same lines as the installer), fresh DB | 14 / 14 passed: MB-00, setup, MB-01–08, MB-12, MB-13, MB-16–18, MB-20, MB-22 |

Not automated yet (test by hand): MB-09, MB-10, MB-11 (credit through POS), MB-14, MB-15, MB-19, MB-21 opening-balance detail, MB-23.
Bug found and fixed by this run: customer list and Customer Receipts crashed with `Class "AppModelsCustomerModel" not found` (namespace backslashes lost in an edit).
