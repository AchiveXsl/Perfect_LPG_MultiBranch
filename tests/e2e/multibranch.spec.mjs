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

// Inventory Detail "Stock by Branch" row: Branch | Gas KG | Filled | Empty.
async function expectStock(page, branch, gasKg, filled, empty) {
  const row = page.locator('.card', { hasText: 'Stock by Branch' }).locator('tbody tr', { hasText: branch });
  await expect(row.locator('td')).toHaveText([branch, gasKg, filled, empty]);
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
  test.setTimeout(240_000);
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
