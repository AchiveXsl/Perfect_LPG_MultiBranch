# Perfect LPG Project Tracker

This file is a concise continuation point for development.

## Current State
- Target architecture is documented in ARCHITECTURE.md.
- The complete one-shot MySQL schema is database/schema.sql.
- The old schema_v2.sql is no longer the authoritative schema.
- TESTING.md is the mandatory user/developer test gate.
- Phase 1 foundation changes are pushed and ready for local testing.

## Phase 1 Ready for User Testing
1. Execute database/schema.sql on a fresh MySQL server.
2. Configure CodeIgniter .env for faizi_traders_lpg.
3. Test login with admin / admin123.
4. Test dashboard.
5. Record PASS/FAIL and exact errors in TESTING.md.
6. Push the updated TESTING.md.

## Important Design Rules
- Transaction tables are source of truth.
- Inventory is represented by opening balances plus immutable inventory movements.
- Counter cash is represented by cash sessions plus cash transactions.
- Posted transactions are reversed/voided; they are not hard-deleted.
- Sales must preserve the actual applied rate on every line.
- Financial posting must be atomic inside a database transaction.
- Walk-in customers are cash-only and cannot create credit or cylinder-return balances.
- Credit sales must enforce the customer's credit limit.
- Controllers should not contain financial posting logic; use application services.

## Next Development Gate
After Phase 1 PASS, implement Phase 2 master data. If Phase 1 has FAIL entries, fix those first and update TESTING.md before continuing.


---

## October 2026 Current Implementation Baseline

This section supersedes older POS assumptions in this file. Do not revert the newer transaction architecture to the historical five-mode model unless explicitly requested.

### Repository / branch
- Primary branch: `main`.
- Known-good historical baseline: `b91321ebc38ee2daac71310653153041682f7745`.
- Current automated CI is defined in `.github/workflows/ci.yml`.

### Current POS transaction architecture
POS now uses one transaction type per invoice:
- `gas_sale` — Gas Sale / Refill.
- `cylinder_sale` — Cylinder Sale.
- `security_deposit` — Security Deposit / Issue Cylinder.
- `cylinder_return` — Cylinder Return / Refund Deposit.

The branch default transaction type is stored in `shop_settings.default_transaction_type` and is selected by Shop Settings. The old `default_sale_mode` field remains for backward compatibility and should not be treated as the current POS architecture.

### Gas sale / physical-cylinder behavior
- Normal gas sale quantity is KG, not whole-cylinder quantity.
- Selling 1 KG consumes only 1 KG of gas.
- When individual cylinder tracking is OFF, POS shows aggregate available gas by cylinder type instead of a physical source-cylinder dropdown.
- Backend automatically allocates gas across available filled physical cylinders of that type.
- Physical cylinder records are still maintained in the backend.
- If a physical source cylinder reaches 0 KG, it becomes empty and the corresponding inventory movements are recorded.
- Gas quantity cannot exceed available gas for the applicable transaction unless an explicit stock-override configuration permits it.
- A single POS gas-sale line can therefore consume gas from more than one physical cylinder internally while remaining one sales line.

### Individual cylinder tracking setting
- Shop Settings contains `individual_cylinder_tracking`.
- Default is OFF.
- OFF: aggregate stock by cylinder type is shown on normal POS gas sales and backend allocation is automatic.
- ON: normal POS can expose physical source-cylinder selection.
- Customer custody cylinder selection remains physical-unit based because custody/return/refill workflows require identifying the cylinder held by the customer.

### Security deposit / custody
- Company cylinders can move to customer custody when a security deposit is issued.
- Security deposit is a liability/hold, not sales revenue. The gas charged on an issue IS sales revenue: Dashboard and Reports use `SalesService::revenueSaleCondition()` (Gas Sale, Cylinder Sale, Security Deposit with total_amount > 0; never Cylinder Return), and payment-mode totals count only `payment_type = 'sale'`.
- Cylinder return requires the customer's custody cylinder and refunds the applicable deposit.
- Repeated custody cycles are supported.
- Every sale type can be voided (owner decision 2026-11-23) by users with POS_VOID (Admin by default), with a reason, in the branch where it was posted. A void reverses everything it posted: stock movements and physical cylinders, custody, deposit ledger (opposite hold/refund entries), counter cash, OS settlements, cost layers; customer OS follows from VOID. A custody refill (gas sale into a customer cylinder) also takes the added gas back off that cylinder. A void is refused only while a later transaction depends on the same cylinder (returned, refilled, sold, transferred or adjusted afterwards): void that later transaction first. Code: `SalesService::void()` and `reverseCustodySale()`.

### Customer OS / payments / credit
- Previous customer OS is carried into the current receivable.
- Payments are applied to previous OS first, then the current sale.
- Remaining unpaid amount becomes customer OS.
- Credit limits support branch validation modes: none, customer, or shop.
- Credit sales require the applicable customer/branch credit validation.

### Inventory
- Opening inventory supports filled cylinders and empty cylinders; gas-only opening records remain historically supported.
- Gas stock is never stored independently. Available Gas KG is always derived from `cylinder_units.gas_weight_kg` for company-owned cylinders with `status='filled'`.
- Inventory has a separate Stock Adjustment & History screen for physical-cylinder and cylinder-count adjustments. Gas adjustments must target a specific physical cylinder and change that unit's actual gas weight; bulk independent Gas KG adjustments are not allowed.
- Filled-cylinder IN requires actual gas weight and cannot exceed cylinder capacity. Purchasing gas must therefore be represented by receiving filled physical cylinders with actual gas weights.
- Inventory Detail Report shows current gas stock, filled/empty cylinder counts, stock by cylinder type, and partially used physical cylinders.

### Pricing (Retail / Wholesale price lists)
Requirements: `requirements.md` → Rate Configuration. Test status and resume point: `testcase.md` → Progress.
- All POS prices come from `App\Services\RateResolver`, scoped to the current shop (`rate_cards.location_id`) and the transaction time. Do not read `rate_cards` for pricing anywhere else.
- Rates belong to a price list (`rate_cards.price_list` = retail / wholesale; migration 035). There are three rate types:
  - `gas_per_kg` (all cylinders);
  - `gas_per_cylinder` (per cylinder type);
  - `empty_cylinder` (per cylinder type).
- Price list per invoice: invoice switch (if `pos_price_list_switch`) → walk-in uses `walkin_price_list` → customer `customer_type` (`retail` / `wholesale` / `shop_default` → `default_price_list`). `wholesale_price_list_enabled = 0` forces Retail everywhere.
- Customer special rates (`customer_rates`, migration 036, `CustomerRateModel`) come first for a named customer: `RateResolver::forCustomer($customer)`. Walk-in never gets them, and nothing applies when `customer_special_rates_enabled = 0`. They are set in LPG Rates → Customer Rates (`Rates::saveCustomer` / `stopCustomer` / `cancelCustomer`). POS reloads `sales/rates?customer_id=` on a customer change, and lines record `rate_source = customer_special`.
- Lookup and fallback:
  - per KG and per full cylinder convert into each other within a list (× / ÷ capacity);
  - Wholesale falls back to Retail;
  - an empty cylinder falls back to `cylinder_types.empty_cylinder_price`.
- Which price each transaction type uses:
  - **Gas Sale:** KG or amount mode uses `gasPerKg`. Cylinders mode uses `gasPerCylinder`, and the entered rate is per cylinder.
  - **Cylinder Sale:** empty lines charge `emptyCylinder`. Filled lines charge `cylinderGasPerKg` × actual gas (full-cylinder price ÷ capacity, else per KG) **plus** `emptyCylinder`.
  - **Security Deposit:** charges **only the gas** of filled / part-filled cylinders (`cylinderGasPerKg` × actual gas). There is **no cylinder price**, because the cylinder stays company property; empty cylinders cost 0. The deposit is a refundable hold, not a charge (owner rule, 2026-10-21).
  - **Cylinder Return:** returned gas is valued at the custody `issued_gas_rate`. A different rate is a POS rate change.
- POS sends a rate only when the cashier changed it; blank means "use the price list". `pos_rate_change_mode` (`not_allowed` / `allowed` / `reason_required`) is enforced server-side for all four transaction types. The reason is stored in `sales.rate_change_reason`.
- Transaction time:
  - up to 10 minutes old counts as now (`RateResolver::pricingTime`), because the POS fills the time in when the screen opens;
  - more than 5 minutes in the future is rejected;
  - more than 10 minutes in the past, when its prices differ from today's, counts as a rate change: blocked when `not_allowed`, needs a reason when `reason_required`, and sets `custom_rate_flag`.
- Sales store `price_list`. Sale lines store `rate_source` (`customer_special`, `retail`, `wholesale`, `retail_fallback`, `cylinder_type`, `manual`, `issued_rate`).
- LPG Rates screen:
  - Retail / Wholesale grids, a Customer Rates tab and a History tab, with the grids and histories scrolling inside their own boxes;
  - Effective From cannot be in the past (up to 1 hour old is treated as now);
  - rates are never edited or deleted: **Stop** ends the current price, and **Cancel** removes a scheduled one that has not started;
  - every save, stop and cancel runs in one transaction and writes `rate_change_log` and an audit entry.
- Security Deposit combined payments: credit can pay gas only, never the deposit, under both `gas_first` and `deposit_first`.

### Browser tests (Playwright)
- Spec: `tests/e2e/pricing.spec.mjs` (57 cases). Case list, status and resume point: `testcase.md`. Config: `playwright.config.mjs`. Install with `npm install`.
- The owner wants headed runs that can be watched, **run in parallel where possible** to save time. Use the wave runner:
  `npm run test:pricing:waves`, which is `node tests/e2e/run-cases.mjs` with the plan in `tests/e2e/pricing.plan.json`.
  - Cases in a wave run in parallel, up to 4 Chrome windows.
  - Waves run in order, and the run stops after a wave with a failure. Resume with `--from N`.
  - Single-case waves change Shop Settings or rates that other cases read.
- When adding a case, put it in a wave where it cannot affect the other cases (see the plan notes).
- One case on its own: `LPG_SLOWMO=500 npx playwright test tests/e2e/pricing.spec.mjs -g "\bPOS-P01\b" --headed --reporter=line`.
- The base URL comes from `.env` `app.baseURL` (e.g. `http://localhost:180/lpg-management-system/public/`). Never hard-code `/login`; use relative paths.
- The tests write to the local database: TC customers, rates, opening stock, test sales and Shop Settings changes (restored afterwards).
- After every run, update the Status column and the Progress table in `testcase.md`, so the next session can continue from there.

### Permissions
Sidebar entries are permission-aware and hidden when the current user lacks the relevant permission.
Current mappings include:
- DASHBOARD_VIEW
- POS_SALE
- CASH_MANAGE
- CUSTOMER_MANAGE
- SUPPLIER_MANAGE
- INVENTORY_MANAGE
- RATE_MANAGE
- PURCHASE_MANAGE
- EXPENSE_MANAGE
- REPORT_VIEW
- USER_MANAGE
- AUDIT_VIEW
- LOAN_MANAGE

### Automated CI status
Latest verified CI result:
- Workflow: **LPG CI #20**
- Commit: `f19b68e730d49ba5b40be9ed98d4c0b9b407f7ae`
- PHP syntax validation: PASS
- Composer dependency installation: PASS
- MySQL 8 schema bootstrap: PASS
- Required migrations: PASS
- `php spark`: PASS
- PHPUnit: **5/5 tests, 6 assertions**
- PHPUnit coverage is intentionally disabled in CI with `--no-coverage` because the CI runner has no coverage driver configured.

Important: CI success is an automated code/database smoke result. It is NOT browser/SQA sign-off.

### Current real-world testing path
Browser/E2E testing still requires a reachable staging application.
Recommended setup:
`Windows PC/XAMPP -> Cloudflare Tunnel -> public HTTPS staging URL -> browser testing`.

Do not expose the production application/database. Use a dedicated test database and test credentials.


## Environment Configuration Rule — Important

- **Never add Cloudflare/tunnel-specific URL logic to application code.**
- The application must use the normal CodeIgniter `app.baseURL` configuration from the local `.env` file.
- For staging, set `app.baseURL` in the staging machine's local `.env` to the staging HTTPS URL.
- For production, set `app.baseURL` in the production machine's local `.env` to the production URL.
- Do not commit tunnel URLs, environment-specific hostnames, production URLs, or environment secrets to the repository.
- Testing infrastructure may use external URLs, but it must not require application-code changes to accommodate a temporary tunnel.


## Windows client installer
- `installer/` builds `PerfectLPG-Setup-<version>.exe` with `.\installer\build.ps1` (Inno Setup 6; Composer needs PHP on PATH). See `installer/README.md`.
- The exe carries only the app. On the client PC it downloads the latest PHP 8.5 / Apache 2.4 / MySQL 8.4 patch (`installer/scripts/resolve.ps1`), installs the `PerfectLPG-Apache` / `PerfectLPG-MySQL` services under `C:\PerfectLPG` and opens the web Installation page. Re-running it upgrades and keeps data.
- Client databases are upgraded only through migrations, so every schema change needs a new migration file.
- Installer scripts must run on Windows PowerShell 5.1: call external programs through `Invoke-Native` in `configure.ps1` (PS 5.1 turns redirected stderr into fatal errors under `ErrorActionPreference = 'Stop'`).

## Client installer
- `installer/` is self-contained and app-agnostic: everything app-specific is in `installer/app.ini` (it can be copied into another PHP app's root). See `installer/README.md`.
- Build: double-click `installer/build.bat` (asks version + notes, updates `app.ini [release]` and `installer/RELEASES.md`; commit both).
- Machine-locked licensing: `installer/license/keygen.bat` issues keys; the private signing key lives outside the repo in `%USERPROFILE%\.app-licenses\<short_name>\`. Runtime check is `installer/scripts/license-check.php`, hooked via php.ini `auto_prepend_file` and injected by the build into `[license] entry_points` (shipped copy only) — do not add license logic to app code.

## Git Workflow Rule
- Flow is feature -> stage -> main. Never push directly to `main` or `stage`.
- Branch from `stage` (`feature/`, `fix/`, `chore/`), push, open a PR into `stage`, merge after CI passes. Promote with a PR from `stage` into `main`.
- Run `git config core.hooksPath .githooks` once per clone to enable the pre-push guard.

## Diagnostics log
- `diagnostics.enabled = true` in `.env` logs requests, responses, SQL queries, errors and browser actions to `writable/logs/diagnostics-YYYY-MM-DD.log` (`App\Libraries\Diagnostics`), for client PCs that cannot be reached. Off by default; takes effect on the next request; files are deleted after 7 days.
- Administrators download the log from `/diagnostics/download` (`?date=YYYY-MM-DD` for an older day). Lines of one request share a `rid`.
- Screens add their own events with `window.lpgDiag?.(name, data)`. Secrets are masked by key name and in SQL; keep that true when logging anything new.

## Old Issued Cylinders
- Inventory > Old Issued Cylinders (`OldIssuedCylinders`, `OldIssuedCylinderService`, migration 038) records cylinders customers held before the system was used, one grid row per issue.
- An entry creates custody cylinders (`source_type = old_issue`, treated as full, rate per KG = rate per cylinder / capacity) and adds its outstanding amount to `customers.opening_balance`. It creates no sale, cash, deposit or inventory movement. The cylinders join shop stock through the normal Cylinder Return.
- Void needs a reason and is blocked once a cylinder was returned or used in a sale. Migration comments must not contain semicolons or apostrophes: the runner splits on them.

## Fill / Empty Cylinders
- Inventory > Fill / Empty Cylinders (`CylinderFills`, `CylinderFillService`, migration 039) converts shop cylinders of one type between empty and full, with history in `cylinder_fills`.
- Empty to full fills to capacity. Full to empty needs a reason, removes the gas in the cylinder and takes the cylinders with the most gas first.
- It only changes stock (`source_type = cylinder_fill` movements for the cylinders and gas). No purchase, sale, cost or cash is recorded.

## Online Database Sync
- Configuration > Online Database Sync (`OnlineSync`, `OnlineSyncService`, `php spark sync:online`, migration 040) copies this database one way to one online MySQL database, one PC per online database. Rows are replaced in one transaction, and changes made online are overwritten.
- Each run connects, refuses this PC's own database or one it does not own (`lpg_sync_target` marker, unless Replace is confirmed), runs missing migrations online through `MigrationRunnerService`, compares columns, then copies. Rows of `online_sync_settings`, `online_sync_log` and `lpg_migration_history` are never copied.
- The password is encrypted with `encryption.key`. The installer's hourly scheduled task runs `installer/scripts/hourly.ps1`, which runs the `[tasks] hourly` command in `installer/app.ini`.

## Cost price / profit (FIFO, LIFO, Average)
- Shop Settings -> Pricing -> Cost Price Method (`shop_settings.cost_method`, migration 042) decides which purchase cost is charged against a sale. `App\Services\CostService` does all of it, controllers must not.
- Purchases and Opening Inventory create `cost_layers` (gas in KG, cylinders per type). Opening rows take optional `gas_cost_per_kg` / `cylinder_cost`, blank counts as 0. First use per location builds layers from existing purchases and opening stock (`ensureReady`, quantity left = stock on hand).
- Posting a Gas Sale, Cylinder Sale or Security Deposit calls `CostService::costSale()` inside the posting transaction: it consumes layers and stores `sale_items.cost_gas / cost_cylinder / cost_amount / cost_method` plus `cost_layer_usage`. Changing the method never touches posted sales. Void calls `reverseSale()` (layers restored), purchase void calls `voidPurchaseLayers()`.
- A filled-cylinder purchase is costed all as gas, an empty-cylinder purchase as a cylinder. Security Deposit has no cylinder cost. Cylinder Return is not costed.
- Dashboard "Sales & Profit" card = gross profit (sales minus cost). Click: customers (`dashboard/profit/customers`) -> that customer's sales (`profit/sales/{id}`, 0 = walk-in) -> one sale with price, cost and cost lots (`profit/sale/{id}`). Sales posted before migration 042 have no stored cost and are estimated with the old all-purchases average, shown as "est.".

---

## November 2026 — Multi-Branch (this repository)

This repository started from AchiveXsl/Faizi_Traders and adds multi-branch support.
- Design: `ARCHITECTURE.md` → Multi-Branch. Migration: `044_20261119_multi_branch.sql`.
- Test cases and resume point: `multibranch_testcase.md`.
- Read the current branch through `App\Services\BranchContext`, not `session('location_id')`, in new code.
- New reports take a branch scope (`BranchContext::reportScope`) and filter with `whereIn('location_id', ...)`.
- Purchasing mode (owner decision 2026-11-24), Shop Settings → Security & Void at the Head Office (`purchase_mode`, migration 046), read with `BranchContext::purchaseMode()` / `canPurchase()`:
  - **Central** (default): purchases and supplier payments belong to the Head Office; stock lands at each purchase line's `receive_location_id`.
  - **Split** (fully separate): each branch purchases only its own stock, owes and pays its own supplier balance from its own cash and voids its own purchases. The Head Office has no special purchasing role. `supplierBalance()` is per branch; the supplier opening balance stays with the Head Office.
  - Split → Central is blocked while any other branch still owes a supplier.
  - Split also makes **every customer branch-only** at its home branch, with credit checked against that branch only (`CustomerModel::branchOnly()`). The customer form shows the branch fields read-only; switching to Split sets `branch_scope` / `credit_scope` to branch for all customers. Migration 049 maps customers without a home branch to the Head Office. Switching back to Central leaves customers branch-only (editable again).

## Cylinder types (owner decision 2026-11-24)
- Only `cylinder_types.code` is unique. Several types may share one capacity (migration 047 drops the capacity unique index).
- Never look a cylinder type up by capacity: stock, rates, costs, sales and imports all use `cylinder_type_id` or `code`.

## Gas Decanting (owner decision 2026-11-23)
Screen: Inventory → Gas Decanting (`/inventory/decant`), permission `GAS_DECANT` (Admin and Manager by default).
- Moves gas from one filled shop cylinder (source) into one or more empty / part-filled shop cylinders (targets) of the same branch, e.g. 20 KG from a 45 kg cylinder into 2 × 10 kg cylinders.
- Total gas never changes: source loses exactly what the targets gain. A target cannot exceed its capacity; the source cannot give more than it holds.
- Targets become `filled`; a source left with 0 KG becomes `empty`.
- Each decanting has a number (DC...), header `gas_decants` and lines `gas_decant_items`, linked stock movements (`source_type='gas_decant'`) and an audit entry.
- Gas cost stays correct automatically: gas cost layers are per branch in KG for all cylinder types together, so moving gas between cylinders of one branch does not change them.
- Only stock changes: no sale, purchase, cash or customer effect. Cylinders in custody, in transit or sold cannot be used.

## Loans (owner decision 2026-11-24)
Screen: Cash & Expenses → Loans (`/loans`), permission `LOAN_MANAGE` (Admin by default), migration 048. Code: `LoanService`, `Loans` controller.
- Records money the business **borrows** from a lender (a person who is neither customer nor supplier). No interest or profit share. Money lent out is not covered yet.
- Each branch keeps its own lenders (`lenders.location_id`) and loans and repays them itself.
- `loan_entries` holds every Loan Received (`LN...`) and Repayment (`LR...`). Balance owed = lender opening balance + received - repaid (posted only). A repayment cannot exceed what is owed.
- A loan is a liability: never sales, profit or expense. Cash entries post counter cash `loan_received` (IN) / `loan_repayment` (OUT) and need an open cash session; cheque / online do not touch the counter.
- Entries are voided with a reason, never deleted; the void reverses the cash in the session it was posted in. A received loan cannot be voided while part of it is repaid (void the repayment first). Lender opening balance is fixed once entries exist.

## Back-dated customer receipts (owner decision 2026-11-26)
- Customer Receipts has a Receive Date (default today, never in the future), stored in `customer_receipts.receipt_at` and printed on the receipt.
- A date before today needs a reason (`backdate_reason`, migration 052). No special permission is required.
- Cash receipts cannot be dated before the open counter session opened. Cheque and online have no earliest date.

## Purchase rate edit (owner decision 2026-11-24)
- Purchases → History → a purchase → **Edit Rates** (PURCHASE_MANAGE, purchase of the current branch, not voided). Only the rate changes; quantity and stock never do. Reason required. Code: `PurchaseService::changeRates()` / `lineStockUse()`, history in `purchase_rate_changes` (migration 050).
- Line total, purchase subtotal / total and `credit_amount` move by the difference (supplier payable adjusts; money already paid stays). The line cost layer takes the new unit cost.
- While none of the line's stock is sold the change is applied directly. Once any is sold, the screen asks "stock is sold, change may affect profit / loss, apply the new rate to all linked sales?". Only on Yes (`confirm_sold=1`) it applies and restates `cost_layer_usage` and `sale_items.cost_*` of those sales (qty x unit-cost difference). Stock already transferred to another branch keeps the cost it carried.

## Sale History / ledger exports (2026-11-24)
- Sale History has a **Cylinders** column: cylinder codes with counts, e.g. `C11_8 (2), C45 (1)` (gas lines count KG / capacity, at least 1 per line). CSV and PDF exports include it.
- Customer ledger PDF: the customer page has Export PDF at the top (same as the Details tab export), and Reports → Customer Ledger has its own Export PDF (opening balance, all rows, current OS).
- Expense history: date filter (default current month), total row, Export PDF.

## Stock transfer dates (owner decision 2026-11-25)
- Dispatch (New Transfer) and receive forms have a date-time field, default now. Shop Settings → Inventory Control → **Stock Transfer: Days Back Allowed** (`shop_settings.transfer_backdate_days`, migration 051, per branch): blank = back to the 1st of the current month (default), 0 = today only.
- `StockTransferService::resolveDate()` enforces it server-side: never in the future, never before the branch limit; a dispatch never before the cylinders' last stock movement; a receipt never before the dispatch. The stock movements of that step use the chosen date.
- Transfer list has a From / To filter on the dispatch date (default current month); transfers still in transit always show.
