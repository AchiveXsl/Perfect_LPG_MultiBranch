// Multi-branch browser tests. Case list: multibranch_testcase.md.
// Runs against a database where migration 044 is pending or applied; it creates branch "Branch Two" (B2),
// users mgr2 / cash2, a supplier, purchases and transfers. Run on a test database only.
import { test, expect } from '@playwright/test';

test.describe.configure({ mode: 'serial' });

const PASS = 'Test@123';
const B2 = { code: 'B2', name: 'Branch Two' };
const hoName = process.env.LPG_HO_NAME || 'Main LPG Shop';
let b2Id = '';

async function login(page, user, pass) {
  await page.goto('login', { waitUntil: 'load' });
  await page.locator('input[name="login"]').fill(user);
  await page.locator('input[name="password"]').fill(pass);
  await page.locator('form:has(input[name="login"]) button[type="submit"]').click();
  await page.waitForLoadState('load');
}

async function logout(page) {
  await page.goto('logout', { waitUntil: 'load' });
}

async function switchBranch(page, name) {
  await page.locator('#branchSwitch').click();
  await page.locator('.dropdown-menu button.dropdown-item', { hasText: name }).click();
  await page.waitForLoadState('load');
  await expect(page.locator('#branchSwitch')).toContainText(name);
}

// Stock a branch already had before this spec (other specs may have run on the same database),
// read in MB-00. Expected figures below are what this spec itself adds; the baseline is added on top.
const baseStock = {};
async function readStock(page, branch) {
  const row = page.locator('.card', { hasText: 'Stock by Branch' }).locator('tbody tr', { hasText: branch });
  if (!(await row.count())) {
    // One branch only: the report has no by-branch table, its summary cards are that branch's totals.
    const card = async (label) => Number(((await page.locator('.card-body', { hasText: label }).first().locator('h3').innerText()).match(/[\d,.]+/) || ['0'])[0].replace(/,/g, ''));
    return { kg: await card('Gas Stock'), filled: await card('Filled Cylinders'), empty: await card('Empty Cylinders') };
  }
  const t = (await row.first().locator('td').allInnerTexts()).map(s => Number(s.replace(/,/g, '')));
  return { kg: t[1] || 0, filled: t[2] || 0, empty: t[3] || 0 };
}

// Inventory Detail "Stock by Branch" row: Branch | Gas KG | Filled | Empty.
async function expectStock(page, branch, gasKg, filled, empty) {
  const b = baseStock[branch] || { kg: 0, filled: 0, empty: 0 };
  const row = page.locator('.card', { hasText: 'Stock by Branch' }).locator('tbody tr', { hasText: branch });
  await expect(row.locator('td')).toHaveText([
    branch,
    (Number(gasKg) + b.kg).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
    String(Number(filled) + b.filled),
    String(Number(empty) + b.empty),
  ]);
}

const alertText = page =>page.locator('.alert').allInnerTexts().then(t => t.join(' | '));

test('MB-00 migrations run and admin signs in', async ({ page }) => {
  await page.goto('login', { waitUntil: 'load' });
  const run = page.locator('form[action$="migrations/run"] button[type="submit"]');
  if (await run.count()) {
    await run.click();
    await page.waitForLoadState('load');
  }
  await login(page, 'admin', 'admin123');
  // Only one branch so far: straight to the dashboard.
  await expect(page).toHaveURL(/dashboard/);
  await expect(page.locator('.topbar')).toContainText(hoName);
  // Baseline stock left by other specs on this database.
  await page.goto('reports/inventory-detail?branches=all', { waitUntil: 'load' });
  baseStock[hoName] = await readStock(page, hoName);
});

test('MB-SETUP create Branch Two, supplier and users', async ({ page }) => {
  await login(page, 'admin', 'admin123');
  await page.goto('branches', { waitUntil: 'load' });
  await expect(page.locator('.content-wrap table').first()).toContainText('Head Office');
  if (!(await page.locator('.content-wrap table').first().innerText()).includes(B2.name)) {
    await page.locator('a[data-bs-target="#branchForm"]').click();
    const form = page.locator('#branchForm');
    await form.locator('input[name="code"]').fill(B2.code);
    await form.locator('input[name="name"]').fill(B2.name);
    await form.locator('input[name="city"]').fill('Lahore');
    await form.locator('button.btn-primary').click();
    await page.waitForLoadState('load');
    expect(await alertText(page)).toContain('created');
  }
  const editHref = await page.locator('.content-wrap tr', { hasText: B2.name }).locator('a', { hasText: 'Edit' }).getAttribute('href');
  b2Id = editHref.match(/edit=(\d+)/)[1];

  // Supplier with room for credit purchases.
  await page.goto('suppliers', { waitUntil: 'load' });
  if (!(await page.locator('body').innerText()).includes('MB Supplier')) {
    await page.locator('[data-bs-target="#form"]').first().click();
    await page.locator('#form input[name="name"]').fill('MB Supplier');
    await page.locator('#form input[name="credit_limit"]').fill('10000000');
    await page.locator('#form button.btn-primary').click();
    await page.waitForLoadState('load');
  }

  // mgr2: Head Office = CASHIER, Branch Two = MANAGER (default). cash2: Branch Two = CASHIER only.
  for (const u of [
    { username: 'mgr2', name: 'Manager Two', rows: { HO: 'CASHIER', B2: 'MANAGER' }, def: 'B2' },
    { username: 'cash2', name: 'Cashier Two', rows: { B2: 'CASHIER' }, def: 'B2' },
  ]) {
    await page.goto('users', { waitUntil: 'load' });
    if ((await page.locator('.content-wrap table').first().innerText()).includes(u.username)) continue;
    await page.locator('button[data-bs-target="#userForm"]').click();
    const f = page.locator('#userForm');
    await f.locator('input[name="full_name"]').fill(u.name);
    await f.locator('input[name="username"]').fill(u.username);
    await f.locator('input[name="password"]').fill(PASS);
    await f.locator('#userMainRole').selectOption({ label: /CASHIER/.test(u.rows.B2) ? 'CASHIER — Cashier' : 'MANAGER — Manager' });
    for (const [key, role] of Object.entries(u.rows)) {
      const row = f.locator('#userBranchRows tbody tr', { hasText: key === 'HO' ? hoName : B2.name });
      await row.locator('input.branch-include').check();
      await row.locator('select.branch-role').selectOption({ label: role });
      if (key === u.def) await row.locator('input[type="radio"]').check();
    }
    await f.locator('button.btn-primary').click();
    await page.waitForLoadState('load');
    expect(await alertText(page)).toContain('User created');
  }
  await expect(page.locator('.content-wrap tr', { hasText: 'mgr2' })).toContainText('B2 · MANAGER');
});

test('MB-05 admin sees every branch as cards', async ({ page }) => {
  await login(page, 'admin', 'admin123');
  await expect(page).toHaveURL(/branches\/select/);
  await expect(page.locator('.branch-card')).toHaveCount(2);
  await expect(page.locator('[data-testid="branch-card-B2"]')).toContainText('Your role: Administrator');
});

test('MB-01 single-branch user goes straight in', async ({ page }) => {
  await login(page, 'cash2', PASS);
  await expect(page).toHaveURL(/dashboard/);
  await expect(page.locator('.topbar')).toContainText(B2.name);
  await expect(page.locator('#branchSwitch')).toHaveCount(0);
});

test('MB-02/03/04 multi-branch user picks a card, then switches with the role following the branch', async ({ page }) => {
  await login(page, 'mgr2', PASS);
  await expect(page).toHaveURL(/branches\/select/);
  await expect(page.locator('[data-testid="branch-card-B2"]')).toContainText('Your role: Manager');
  await expect(page.locator('[data-testid="branch-card-MAIN"]')).toContainText('Your role: Cashier');
  await page.locator('[data-testid="branch-card-B2"]').click();
  await page.waitForLoadState('load');
  await expect(page).toHaveURL(/dashboard/);
  await expect(page.locator('.topbar .badge').last()).toHaveText('MANAGER');
  // Manager at B2 sees inventory menu; cashier at Head Office does not.
  await expect(page.locator('#sidebar')).toContainText('Stock Transfers');
  await switchBranch(page, hoName);
  await expect(page.locator('.topbar .badge').last()).toHaveText('CASHIER');
  await expect(page.locator('#sidebar')).not.toContainText('Stock Transfers');
});

test('MB-06 a page opened in one branch cannot save after switching branch in another tab', async ({ browser }) => {
  const context = await browser.newContext();
  const a = await context.newPage();
  await login(a, 'admin', 'admin123');
  await a.locator('[data-testid="branch-card-MAIN"]').click();
  await a.waitForLoadState('load');
  await a.goto('customers', { waitUntil: 'load' });
  await a.locator('[data-bs-target="#form"]').first().click();
  await a.locator('#form input[name="name"]').fill('MB Guard Customer');

  const b = await context.newPage();
  await b.goto('dashboard', { waitUntil: 'load' });
  await switchBranch(b, B2.name);

  await a.locator('#form button.btn-primary').click();
  await a.waitForLoadState('load');
  expect(await alertText(a)).toContain('You switched to branch');
  await b.goto('customers', { waitUntil: 'load' });
  await expect(b.locator('body')).not.toContainText('MB Guard Customer');
  await context.close();
});

test('MB-08 branch-only customer is hidden at other branches', async ({ page }) => {
  await login(page, 'admin', 'admin123');
  await page.locator('[data-testid="branch-card-B2"]').click();
  await page.waitForLoadState('load');
  await page.goto('customers', { waitUntil: 'load' });
  await page.locator('[data-bs-target="#form"]').first().click();
  await page.locator('#form input[name="name"]').fill('MB B2 Only');
  await page.locator('#customerBranchScope').selectOption('branch');
  await page.locator('#form button.btn-primary').click();
  await page.waitForLoadState('load');
  await expect(page.locator('.content-wrap table').first()).toContainText('MB B2 Only');
  await expect(page.locator('.content-wrap tr', { hasText: 'MB B2 Only' })).toContainText('B2 only');
  await switchBranch(page, hoName);
  await page.goto('customers', { waitUntil: 'load' });
  await expect(page.locator('body')).not.toContainText('MB B2 Only');
});

test('MB-12 purchasing is Head Office only', async ({ page }) => {
  await login(page, 'admin', 'admin123');
  await page.locator('[data-testid="branch-card-B2"]').click();
  await page.waitForLoadState('load');
  await expect(page.locator('#sidebar')).not.toContainText('Supplier Payments');
  await page.goto('purchases', { waitUntil: 'load' });
  await expect(page).toHaveURL(/dashboard/);
  expect(await alertText(page)).toContain('Purchases are entered at the Head Office');
});

test('MB-13 one Head Office purchase receives stock at two branches', async ({ page }) => {
  await login(page, 'admin', 'admin123');
  await page.locator('[data-testid="branch-card-MAIN"]').click();
  await page.waitForLoadState('load');
  await page.goto('purchases', { waitUntil: 'load' });
  await page.locator('#supplier_id').selectOption({ label: 'MB Supplier' });
  const lines = [
    { type: 'filled_cylinder', dest: hoName, qty: '10' },
    { type: 'filled_cylinder', dest: B2.name, qty: '5' },
    { type: 'empty_cylinder', dest: hoName, qty: '4' },
  ];
  for (let i = 0; i < lines.length; i++) {
    if (i > 0) await page.locator('#add').click();
    const row = page.locator('#lines tr.line').nth(i);
    await row.locator('.type').selectOption(lines[i].type);
    await row.locator('.cyl').selectOption({ index: 1 });
    // Head Office options read "<name> (HO)".
    const destValue = await row.locator('.dest option').evaluateAll((opts, name) => (opts.find(o => o.textContent.trim().startsWith(name)) || {}).value, lines[i].dest);
    await row.locator('.dest').selectOption(destValue);
    await row.locator('.qty').fill(lines[i].qty);
    await row.locator('.rate').fill('3000');
  }
  await page.locator('#postPurchase').click();
  await page.waitForLoadState('load');
  expect(await alertText(page)).toContain('posted');

  await page.goto('reports/inventory-detail?branches=all', { waitUntil: 'load' });
  const byBranch = page.locator('.card', { hasText: 'Stock by Branch' });
  await expectStock(page, hoName, '118.00', '10', '4');
  await expectStock(page, B2.name, '59.00', '5', '0');
});

test('MB-16/17 transfer: dispatch, partial receipt, missing cylinder returns to sender', async ({ page }) => {
  page.on('dialog', d => d.accept());
  await login(page, 'admin', 'admin123');
  await page.locator('[data-testid="branch-card-MAIN"]').click();
  await page.waitForLoadState('load');
  await page.goto('stock-transfers/new', { waitUntil: 'load' });
  await page.locator('select[name="to_location_id"]').selectOption({ label: B2.name });
  await page.locator('input[name$="[filled]"]').first().fill('3');
  await page.locator('input[name$="[empty]"]').first().fill('2');
  await page.locator('#transferForm button[type="submit"]').click();
  await page.waitForLoadState('load');
  expect(await alertText(page)).toContain('dispatched: 3 filled and 2 empty');
  const transferUrl = page.url();

  await page.goto('reports/inventory-detail?branches=all', { waitUntil: 'load' });
  await expect(page.locator('.card', { hasText: 'In Transit Between Branches' })).toContainText('5');
  await expectStock(page, hoName, '82.60', '7', '2');

  await switchBranch(page, B2.name);
  await page.goto('stock-transfers', { waitUntil: 'load' });
  await expect(page.locator('.alert-warning')).toContainText('1 incoming transfer');
  await page.goto(transferUrl, { waitUntil: 'load' });
  // One empty did not arrive, one filled cylinder arrived 1 KG short.
  const emptyRows = page.locator('#receiveForm tbody tr', { hasText: 'Empty' });
  await emptyRows.first().locator('.arrived').uncheck();
  await page.locator('#receiveForm .gas-in').first().fill('10.8');
  await page.locator('#receiveForm button[type="submit"]').click();
  await page.waitForLoadState('load');
  const msg = await alertText(page);
  expect(msg).toContain('4 cylinder(s) added');
  expect(msg).toContain('1 cylinder(s) marked missing');
  expect(msg).toContain('1.000 KG');

  await page.goto('reports/inventory-detail?branches=all', { waitUntil: 'load' });
  const byBranch = page.locator('.card', { hasText: 'Stock by Branch' });
  await expectStock(page, B2.name, '93.40', '8', '1');
  await expectStock(page, hoName, '82.60', '7', '3');
  await expect(page.locator('.card', { hasText: 'In Transit Between Branches' }).locator('h3')).toContainText('0');
});

test('MB-18 cancelled transfer returns everything to the sender', async ({ page }) => {
  page.on('dialog', d => d.accept());
  await login(page, 'admin', 'admin123');
  await page.locator('[data-testid="branch-card-MAIN"]').click();
  await page.waitForLoadState('load');
  await page.goto('stock-transfers/new', { waitUntil: 'load' });
  await page.locator('select[name="to_location_id"]').selectOption({ label: B2.name });
  await page.locator('input[name$="[filled]"]').first().fill('2');
  await page.locator('#transferForm button[type="submit"]').click();
  await page.waitForLoadState('load');
  await page.locator('input[name="reason"]').fill('Vehicle not available');
  await page.locator('form[action*="stock-transfers/cancel"] button[type="submit"]').click();
  await page.waitForLoadState('load');
  expect(await alertText(page)).toContain('cancelled');
  await expect(page.locator('h4')).toContainText('Cancelled');
  await page.goto('reports/inventory-detail?branches=all', { waitUntil: 'load' });
  await expectStock(page, hoName, '82.60', '7', '3');
});

test('MB-20 combined report shows a by-branch breakdown', async ({ page }) => {
  await login(page, 'admin', 'admin123');
  await page.locator('[data-testid="branch-card-MAIN"]').click();
  await page.waitForLoadState('load');
  await page.goto('reports?branches=all', { waitUntil: 'load' });
  await expect(page.locator('body')).toContainText('All branches');
  const byBranch = page.locator('.card', { hasText: 'By Branch' });
  await expect(byBranch.locator('tbody tr')).toHaveCount(2);
  // Purchase value of stock received: 10×3000 + 4×3000 at Head Office, 5×3000 at Branch Two.
  await expect(byBranch.locator('tr', { hasText: hoName })).toContainText('42,000.00');
  await expect(byBranch.locator('tr', { hasText: B2.name })).toContainText('15,000.00');
  await page.goto('reports/customer-balances?branches=all', { waitUntil: 'load' });
  await expect(page.locator('.content-wrap thead')).toContainText('Combined');
});

test('MB-22 a user without all-branch reports only sees their branch', async ({ page }) => {
  await login(page, 'cash2', PASS);
  await page.goto('reports?branches=all', { waitUntil: 'load' });
  await expect(page.locator('body')).not.toContainText('All branches');
  await expect(page.locator('.card', { hasText: 'By Branch' })).toHaveCount(0);
  await expect(page.locator('h4 + .text-muted')).toContainText(B2.name);
});

test('MB-07 removing branch access sends the user back to the picker', async ({ browser }) => {
  test.setTimeout(480_000);
  const admin = await (await browser.newContext()).newPage();
  const user = await (await browser.newContext()).newPage();
  await login(user, 'mgr2', PASS);
  await user.locator('[data-testid="branch-card-B2"]').click();
  await user.waitForLoadState('load');

  await login(admin, 'admin', 'admin123');
  await admin.locator('[data-testid="branch-card-MAIN"]').click();
  await admin.waitForLoadState('load');
  await admin.goto('users', { waitUntil: 'load' });
  const editHref = await admin.locator('.content-wrap tr', { hasText: 'mgr2' }).locator('a', { hasText: 'Edit' }).getAttribute('href');
  await admin.goto(editHref, { waitUntil: 'load' });
  const f = admin.locator('#userForm');
  await expect(f).toBeVisible();
  await f.locator('#userBranchRows tbody tr', { hasText: B2.name }).locator('input.branch-include').uncheck();
  await f.locator('#userBranchRows tbody tr', { hasText: hoName }).locator('input[type="radio"]').check();
  await f.locator('button.btn-primary').click();
  await admin.waitForLoadState('load');
  expect(await alertText(admin)).toContain('User updated');

  // mgr2 now has only the Head Office: the next request drops B2 and re-enters the only branch.
  await user.goto('dashboard', { waitUntil: 'load' });
  await expect(user.locator('.topbar')).toContainText(hoName);
  await expect(user.locator('.topbar')).not.toContainText(B2.name);
});

async function setPurchaseMode(page, mode) {
  await page.goto('shop-settings', { waitUntil: 'load' });
  await page.locator('button[data-bs-target="#security"]').click();
  await page.locator(mode === 'split' ? '#pmSplit' : '#pmCentral').check();
  await page.locator('button.btn-primary', { hasText: 'Save Shop Settings' }).click();
  await page.waitForLoadState('load');
}

test('MB-24 split purchasing: a branch buys and pays its own stock, central blocked while it owes', async ({ page }) => {
  test.setTimeout(480_000);
  await login(page, 'admin', 'admin123');
  await page.locator('[data-testid="branch-card-MAIN"]').click();
  await page.waitForLoadState('load');
  await setPurchaseMode(page, 'split');
  expect(await alertText(page)).not.toContain('Cannot');

  await switchBranch(page, B2.name);
  await expect(page.locator('#sidebar')).toContainText('Supplier Payments');
  await page.goto('purchases', { waitUntil: 'load' });
  await expect(page).toHaveURL(/purchases/);
  // Receive at offers only this branch.
  await page.locator('#supplier_id').selectOption({ label: 'MB Supplier' });
  const row = page.locator('#lines tr.line').first();
  await expect(row.locator('.dest option')).toHaveCount(1);
  await expect(row.locator('.dest option')).toHaveText([B2.name]);
  await row.locator('.type').selectOption('filled_cylinder');
  await row.locator('.cyl').selectOption({ index: 1 });
  await row.locator('.qty').fill('2');
  await row.locator('.rate').fill('3000');
  await page.locator('#postPurchase').click();
  await page.waitForLoadState('load');
  expect(await alertText(page)).toContain('posted');

  // B2 owes only its own purchase (2 x 3000), not the Head Office balance.
  await page.goto('supplier-payments', { waitUntil: 'load' });
  await page.locator('#supplier_id').selectOption({ label: 'MB Supplier' });
  await expect(page.locator('#outstanding')).toContainText('6,000.00');

  // Back to central is refused while B2 owes.
  await switchBranch(page, hoName);
  await setPurchaseMode(page, 'central');
  expect(await alertText(page)).toContain('Branch Two');

  // B2 pays its balance, then central is accepted.
  await switchBranch(page, B2.name);
  await page.goto('supplier-payments', { waitUntil: 'load' });
  await page.locator('#supplier_id').selectOption({ label: 'MB Supplier' });
  await page.locator('#amount').fill('6000');
  await page.locator('#payment_mode').selectOption('online');
  await page.locator('#payButton').click();
  await page.waitForLoadState('load');
  expect(await alertText(page)).toContain('posted');

  await switchBranch(page, hoName);
  await setPurchaseMode(page, 'central');
  expect(await alertText(page)).not.toContain('Cannot');
  await switchBranch(page, B2.name);
  await expect(page.locator('#sidebar')).not.toContainText('Supplier Payments');
});


test('MB-25 cylinder types may share a capacity, codes stay unique', async ({ page }) => {
  await login(page, 'admin', 'admin123');
  await page.locator('[data-testid="branch-card-MAIN"]').click();
  await page.waitForLoadState('load');
  const addType = async (code, name, cap) => {
    await page.goto('cylinder-types', { waitUntil: 'load' });
    await page.locator('[data-bs-target="#form"]').first().click();
    const f = page.locator('#form');
    await f.locator('input[name="code"]').fill(code);
    await f.locator('input[name="name"]').fill(name);
    await f.locator('input[name="capacity_kg"]').fill(cap);
    await f.locator('input[name="empty_cylinder_price"]').fill('0');
    await f.locator('input[name="sort_order"]').fill('9');
    await f.locator('button.btn-primary').click();
    await page.waitForLoadState('load');
    return alertText(page);
  };
  // 11.8 kg already exists as C11_8: a second 11.8 kg type with a new code is accepted.
  expect(await addType('C11_8B', '11.8 kg Brand B', '11.8')).toContain('Cylinder type created');
  // The same code again is refused with a clear message.
  expect(await addType('C11_8B', 'Duplicate', '15')).toContain('already used');
});

test('MB-26 split purchasing makes every customer branch-only, fields read-only', async ({ page }) => {
  test.setTimeout(480_000);
  await login(page, 'admin', 'admin123');
  await page.locator('[data-testid="branch-card-MAIN"]').click();
  await page.waitForLoadState('load');
  // A shared customer created at the Head Office while central.
  await page.goto('customers', { waitUntil: 'load' });
  await page.locator('[data-bs-target="#form"]').first().click();
  await page.locator('#form input[name="name"]').fill('MB Shared HO');
  await page.locator('#customerBranchScope').selectOption('shared');
  await page.locator('#form button.btn-primary').click();
  await page.waitForLoadState('load');

  await setPurchaseMode(page, 'split');
  // Now the HO customer belongs to the HO only.
  await switchBranch(page, B2.name);
  await page.goto('customers', { waitUntil: 'load' });
  await expect(page.locator('body')).not.toContainText('MB Shared HO');
  // New customer at B2: branch fields are read-only and fixed to B2.
  await page.locator('[data-bs-target="#form"]').first().click();
  await expect(page.locator('#customerBranchScope')).toHaveCount(0);
  await expect(page.locator('#customerHomeBranch')).toHaveValue(B2.name);
  await expect(page.locator('#customerHomeBranch')).toBeDisabled();
  await page.locator('#form input[name="name"]').fill('MB Split B2');
  await page.locator('#form button.btn-primary').click();
  await page.waitForLoadState('load');
  await expect(page.locator('.content-wrap tr', { hasText: 'MB Split B2' })).toContainText('B2 only');
  await switchBranch(page, hoName);
  await page.goto('customers', { waitUntil: 'load' });
  await expect(page.locator('body')).not.toContainText('MB Split B2');
  await expect(page.locator('.content-wrap tr', { hasText: 'MB Shared HO' })).toContainText('only');

  // Restore central (no branch owes a supplier here).
  await setPurchaseMode(page, 'central');
  expect(await alertText(page)).not.toContain('Cannot');
});

// ---------- Purchase rate edit, sale history cylinders, ledger / expense PDFs, loans ----------
async function enterHo(page) {
  await login(page, 'admin', 'admin123');
  await page.locator('[data-testid="branch-card-MAIN"]').click();
  await page.waitForLoadState('load');
}
async function openCash(page) {
  await page.goto('cash', { waitUntil: 'load' });
  const open = page.locator('form[action$="cash/open"] button');
  if (await open.count()) { await open.first().click(); await page.waitForLoadState('load'); }
}
async function openLatestPurchase(page) {
  await page.goto('purchases?tab=history', { waitUntil: 'load' });
  // The test posts its sale in the background, so that sale's receipt popup (normally shown and cleared by the
  // POS reload) opens on the next page, which is this one. Close it first.
  const overlay = page.locator('#purchaseReceiptOverlay.is-visible');
  if (await overlay.count()) { await page.locator('#purchaseReceiptClose').click(); await expect(overlay).toHaveCount(0); }
  const btn = page.locator('[data-bs-target^="#purchaseDetailModal"]').first();
  const target = await btn.getAttribute('data-bs-target');
  await btn.click();
  const modal = page.locator(target);
  await expect(modal).toBeVisible();
  await modal.locator('summary', { hasText: 'Edit Rates' }).click();
  return modal;
}
async function postWalkinGas(page, typeId, kg, rate) {
  const html = await (await page.request.get('sales')).text();
  const token = html.match(/name="csrf_test_name" value="([^"]+)"/)[1];
  const d = new Date(), p = n => String(n).padStart(2, '0');
  const res = await page.request.post('sales/save', {
    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    form: {
      csrf_test_name: token, discount_amount: '0', stock_override_confirmed: '0', transaction_type: 'gas_sale', customer_id: '',
      transaction_at: `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`,
      rate_change_reason: 'MB test rate',
      lines_json: JSON.stringify([{ cylinder_type_id: String(typeId), source_cylinder_unit_id: '', quantity: String(kg), cylinder_quantity: '0', gas_entry_mode: 'quantity', gas_rate: String(rate), entered_amount: '', customer_cylinder_unit_id: '' }]),
      payments_json: JSON.stringify([{ payment_type: 'sale', payment_mode: 'cash', amount: String(kg * rate) }]),
    },
  });
  return res.json();
}

test('MB-27 purchase rate edit: free while unsold, asks before restating sold stock', async ({ page }) => {
  test.setTimeout(480_000);
  await enterHo(page);
  await openCash(page);
  // Purchase 2 filled 11.8 kg at 3000 on credit.
  await page.goto('purchases', { waitUntil: 'load' });
  await page.locator('#supplier_id').selectOption({ label: 'MB Supplier' });
  const row = page.locator('#lines tr.line').first();
  await row.locator('.type').selectOption('filled_cylinder');
  await row.locator('.cyl').selectOption({ index: 1 });
  await row.locator('.qty').fill('2');
  await row.locator('.rate').fill('3000');
  await page.locator('#postPurchase').click();
  await page.waitForLoadState('load');
  expect(await alertText(page)).toContain('posted');

  // Unsold: the rate changes without a question.
  let modal = await openLatestPurchase(page);
  const cells = modal.locator('.purchase-rate-form tbody tr').first().locator('td');
  await expect(cells.nth(3)).toHaveText('None');
  await modal.locator('.rate-input').first().fill('3100');
  await modal.locator('.purchase-rate-form input[name="reason"]').fill('Final rate from supplier');
  await modal.locator('.purchase-rate-form button[type="submit"]').click();
  await page.waitForLoadState('load');
  expect(await alertText(page)).toContain('Total changed by Rs. 200.00');

  // Sell gas: part of this purchase's gas is now sold (cylinder type 1 = C11_8 on a fresh database).
  const sale = await postWalkinGas(page, 1, 5, 300);
  expect(sale.success, JSON.stringify(sale)).toBeTruthy();

  modal = await openLatestPurchase(page);
  const cells2 = modal.locator('.purchase-rate-form tbody tr').first().locator('td');
  await expect(cells2.nth(3)).toContainText('KG');
  await modal.locator('.rate-input').first().fill('3200');
  await modal.locator('.purchase-rate-form input[name="reason"]').fill('Supplier revised again');
  // Cancel the question: nothing is posted.
  let asked = '';
  page.once('dialog', d => { asked = d.message(); d.dismiss(); });
  await modal.locator('.purchase-rate-form button[type="submit"]').click();
  await expect.poll(() => asked).toContain('already sold');
  await expect(cells2.nth(4)).toHaveText('3,100.00');
  // Accept: rate applied and linked sales restated.
  page.once('dialog', d => d.accept());
  await modal.locator('.purchase-rate-form button[type="submit"]').click();
  await page.waitForLoadState('load');
  const msg = await alertText(page);
  expect(msg).toContain('Total changed by Rs. 200.00');
  expect(msg).toContain('restated');
});

test('MB-28 sale history shows cylinder types with counts in one column', async ({ page }) => {
  await enterHo(page);
  await page.goto('sales/history', { waitUntil: 'load' });
  await expect(page.locator('thead')).toContainText('Cylinders');
  await expect(page.locator('#saleHistoryBody tr.sale-history-row').first().locator('td.sh-cyl')).toHaveText('C11_8 (1)');
});

test('MB-29 customer ledger exports PDF from the customer page and from Reports', async ({ page }) => {
  await enterHo(page);
  await page.goto('customers', { waitUntil: 'load' });
  const href = await page.locator('.content-wrap tr', { hasText: 'MB Shared HO' }).locator('a[href*="customers/ledger/"]').first().getAttribute('href');
  const id = href.match(/ledger\/(\d+)/)[1];
  await page.goto('customers/ledger/' + id, { waitUntil: 'load' });
  await page.evaluate(() => { window.print = () => {}; });
  let [popup] = await Promise.all([page.waitForEvent('popup'), page.locator('#ledgerPdfTop').click()]);
  await popup.waitForLoadState();
  expect(await popup.content()).toContain('Customer Ledger - MB Shared HO');
  await popup.close();
  await page.goto('reports/ledger/' + id, { waitUntil: 'load' });
  await page.evaluate(() => { window.print = () => {}; });
  [popup] = await Promise.all([page.waitForEvent('popup'), page.locator('#ledgerPdf').click()]);
  await popup.waitForLoadState();
  const html = await popup.content();
  expect(html).toContain('MB Shared HO');
  expect(html).toContain('Opening Balance');
});

test('MB-30 loans: lender, loan received, repayment limit, void', async ({ page }) => {
  test.setTimeout(480_000);
  page.on('dialog', d => d.accept('Entered by mistake'));
  await enterHo(page);
  await openCash(page);
  await page.goto('loans', { waitUntil: 'load' });
  await page.locator('[data-bs-target="#lenderForm"]').click();
  await page.locator('#lenderForm input[name="name"]').fill('MB Lender');
  await page.locator('#lenderForm input[name="opening_balance"]').fill('1000');
  await page.locator('#lenderForm button.btn-primary').click();
  await page.waitForLoadState('load');
  const post = async (type, amount) => {
    await page.locator(type === 'received' ? 'label[for="etReceived"]' : 'label[for="etRepayment"]').click();
    await page.locator('#loanLender').selectOption({ label: 'MB Lender' });
    await page.evaluate(() => document.getElementById('loanAmount').removeAttribute('max'));
    await page.locator('#loanAmount').fill(amount);
    await page.locator('#loanMode').selectOption('cash');
    await page.locator('#loanPost').click();
    await page.waitForLoadState('load');
    return alertText(page);
  };
  expect(await post('received', '50000')).toContain('Loan received');
  expect(await post('repayment', '20000')).toContain('Repayment');
  await expect(page.locator('#lenderTable tr', { hasText: 'MB Lender' })).toContainText('31,000.00');
  expect(await post('repayment', '40000')).toContain('still owed');
  await page.locator('#lenderTable tr', { hasText: 'MB Lender' }).locator('a', { hasText: 'Ledger' }).click();
  await page.waitForLoadState('load');
  await page.locator('tr', { hasText: 'Repayment' }).locator('button', { hasText: 'Void' }).first().click();
  await page.waitForLoadState('load');
  await expect(page.locator('#ledgerBalance')).toHaveText('51,000.00');
});

test('MB-31 expense history: date filter and PDF', async ({ page }) => {
  await enterHo(page);
  await openCash(page);
  await page.goto('expenses', { waitUntil: 'load' });
  if (await page.locator('select[name="category_id"] option').count() > 1) {
    await page.locator('select[name="category_id"]').selectOption({ index: 1 });
    await page.locator('input[name="amount"]').fill('1234.5');
    await page.locator('select[name="payment_mode"]').selectOption('online');
    await page.locator('input[name="description"]').fill('MB fuel');
    await page.locator('button', { hasText: 'Post Expense' }).click();
    await page.waitForLoadState('load');
    await expect(page.locator('#expenseTable tbody')).toContainText('MB fuel');
  }
  await page.locator('#expFrom').fill('2020-01-01');
  await page.locator('#expTo').fill('2020-01-31');
  await page.locator('#expenseFilter button.btn-primary').click();
  await page.waitForLoadState('load');
  await expect(page.locator('#expenseTable tbody')).toContainText('No expenses in this period');
  await page.goto('expenses', { waitUntil: 'load' });
  await page.evaluate(() => { window.print = () => {}; });
  const [popup] = await Promise.all([page.waitForEvent('popup'), page.locator('#expensePdf').click()]);
  await popup.waitForLoadState();
  expect(await popup.title()).toContain('Expense History');
});

test('MB-32 transfer dates: setting limit, last-movement check, receive date, list date filter', async ({ page }) => {
  test.setTimeout(480_000);
  page.on('dialog', d => d.accept());
  await enterHo(page);
  const iso = d => { const p = n => String(n).padStart(2, '0'); return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`; };
  const daysAgo = n => { const d = new Date(); d.setDate(d.getDate() - n); return d; };
  const setDays = async v => {
    await page.goto('shop-settings', { waitUntil: 'load' });
    await page.locator('button[data-bs-target="#inventory"]').click();
    await page.locator('#transferBackdateDays').fill(v);
    await page.locator('button.btn-primary', { hasText: 'Save Shop Settings' }).click();
    await page.waitForLoadState('load');
  };
  await setDays('2');

  const tryDispatch = async date => {
    await page.goto('stock-transfers/new', { waitUntil: 'load' });
    await page.locator('select[name="to_location_id"]').selectOption({ label: B2.name });
    await page.locator('input[name$="[empty]"]').first().fill('1');
    // Bypass the browser min / max so the server rule is what is tested.
    await page.locator('#transferDate').evaluate((el, v) => { el.removeAttribute('min'); el.removeAttribute('max'); el.value = v; }, date);
    await page.locator('#transferForm button[type="submit"]').click();
    await page.waitForLoadState('load');
    return alertText(page);
  };
  // 5 days back is beyond the 2-day setting.
  expect(await tryDispatch(iso(daysAgo(5)) + 'T10:00')).toContain('Shop Settings');
  // Tomorrow is in the future.
  expect(await tryDispatch(iso(daysAgo(-1)) + 'T10:00')).toContain('future');
  // A valid date (now) dispatches.
  const d = new Date(), p = n => String(n).padStart(2, '0');
  expect(await tryDispatch(`${iso(d)}T${p(d.getHours())}:${p(d.getMinutes())}`)).toContain('dispatched');
  const transferUrl = page.url();

  // The list filter: a 2020 range still shows the transfer, because it is in transit.
  await page.goto('stock-transfers?from=2020-01-01&to=2020-01-31', { waitUntil: 'load' });
  await expect(page.locator('#trFrom')).toHaveValue('2020-01-01');
  const inTransitRows = await page.locator('.content-wrap table tbody tr').count();
  expect(inTransitRows).toBeGreaterThan(0);

  // Receive at B2: a date before the dispatch is refused, then now is accepted.
  await switchBranch(page, B2.name);
  await page.goto(transferUrl, { waitUntil: 'load' });
  await page.locator('#receiveDate').evaluate((el, v) => { el.removeAttribute('min'); el.value = v; }, iso(daysAgo(1)) + 'T08:00');
  await page.locator('#receiveForm button[type="submit"]').click();
  await page.waitForLoadState('load');
  expect(await alertText(page)).toContain('cannot be before');
  await page.goto(transferUrl, { waitUntil: 'load' });
  await page.locator('#receiveForm button[type="submit"]').click();
  await page.waitForLoadState('load');
  expect(await alertText(page)).toContain('received');

  // Received transfers drop out of a 2020 filter; this month shows them.
  await page.goto('stock-transfers?from=2020-01-01&to=2020-01-31', { waitUntil: 'load' });
  await expect(page.locator('.content-wrap table tbody')).not.toContainText('Received');
  await page.goto('stock-transfers', { waitUntil: 'load' });
  await expect(page.locator('.content-wrap table tbody')).toContainText(/Received/i);

  await switchBranch(page, hoName);
  await setDays('');
});
