// System Date browser tests (owner decision 2026-11-26). Case list: systemdate_testcase.md.
// Every branch has a System Date in the top bar, after the branch switch. Transactions take that date
// with the clock time, screens open on it, stock and balances show the end of that day, and a posting
// that would put a cylinder movement before a later one is refused with a popup that explains why and
// what to do.
//
// Run:  npx playwright test tests/e2e/systemdate.spec.mjs --headed
// Needs: admin / admin123, migration 054, filled stock of at least one cylinder type in the Head Office.
// Data: changes the Head Office System Date (restored to "follow today" at the end), opens / closes
// counter sessions and posts small walk-in cash gas sales.
import { test, expect } from '@playwright/test';

test.describe.configure({ mode: 'serial' });

const pad = n => String(n).padStart(2, '0');
const iso = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const dmy = d => `${pad(d.getDate())}-${d.toLocaleString('en-US', { month: 'short' })}-${d.getFullYear()}`;
const today = new Date();
const back = new Date(today.getTime() - 3 * 86400000); // three days ago

async function login(page) {
  await page.goto('login', { waitUntil: 'load' });
  const gate = page.getByRole('button', { name: /Execute Pending Migrations/i });
  if (await gate.count()) { await gate.click(); await page.waitForURL(/\/login/); }
  await page.fill('input[name="login"]', 'admin');
  await page.fill('input[name="password"]', 'admin123');
  await Promise.all([page.waitForURL(/\/(dashboard|branches\/select)/), page.click('form:has(input[name="login"]) button[type="submit"]')]);
  if (page.url().includes('branches/select')) {
    await Promise.all([page.waitForURL(/\/dashboard/), page.locator('.branch-card').first().click()]);
  }
}

/** Sets the System Date from the top bar; null = follow today. */
async function setSystemDate(page, date) {
  await page.locator('#systemDateButton').click();
  const follow = page.locator('#systemDateFollow');
  if (date === null) {
    await follow.check();
  } else {
    await page.locator('#systemDateInput').fill(iso(date));
    await follow.uncheck();
  }
  await Promise.all([page.waitForLoadState('load'), page.locator('#systemDateSave').click()]);
  await expect(page.locator('.alert-success').filter({ hasText: 'System Date set to' })).toHaveCount(1);
}

async function csrf(page, path = 'sales') {
  const html = await (await page.request.get(path)).text();
  return html.match(/name="csrf_test_name" value="([^"]+)"/)[1];
}

/** First cylinder type with filled stock on the POS, as [typeId, availableKg]. */
async function stockedType(page) {
  await page.goto('sales', { waitUntil: 'load' });
  // The POS keeps the usable filled cylinders per type in the page constant filledUnits.
  // eslint-disable-next-line no-undef
  const types = await page.evaluate(() => Object.entries(typeof filledUnits === 'object' && filledUnits ? filledUnits : {})
    .map(([id, units]) => [id, (units || []).reduce((s, u) => s + Number(u.gas_weight_kg || 0), 0)]));
  const t = types.find(([, kg]) => kg >= 1);
  return t || null;
}

async function postWalkinGas(page, typeId, kg, rate, at) {
  const token = await csrf(page);
  const res = await page.request.post('sales/save', {
    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    form: {
      csrf_test_name: token, discount_amount: '0', stock_override_confirmed: '0', transaction_type: 'gas_sale', customer_id: '',
      transaction_at: at, rate_change_reason: 'System Date test',
      lines_json: JSON.stringify([{ cylinder_type_id: String(typeId), source_cylinder_unit_id: '', quantity: String(kg), cylinder_quantity: '0', gas_entry_mode: 'quantity', gas_rate: String(rate), entered_amount: '', customer_cylinder_unit_id: '' }]),
      payments_json: JSON.stringify([{ payment_type: 'sale', payment_mode: 'cash', amount: String(kg * rate) }]),
    },
  });
  return { status: res.status(), json: await res.json().catch(() => ({})), conflict: res.headers()['x-lpg-conflict'] || '' };
}

async function closeOpenCash(page) {
  await page.goto('cash', { waitUntil: 'load' });
  const close = page.locator('form[action$="cash/close"]');
  if (await close.count()) {
    await close.locator('input[name="counted_cash"]').fill('0');
    await Promise.all([page.waitForLoadState('load'), close.locator('button').first().click()]);
  }
}

async function openCash(page) {
  await page.goto('cash', { waitUntil: 'load' });
  const open = page.locator('form[action$="cash/open"] button');
  if (await open.count()) { await Promise.all([page.waitForLoadState('load'), open.first().click()]); }
}

test.afterAll(async ({ browser }) => {
  const page = await browser.newPage();
  try { await login(page); await setSystemDate(page, null); } catch { /* best effort */ }
  await page.close();
});

// Every case leaves the branch on today with no open counter session, so later cases and other specs
// never meet a back-dated session (the one-session-per-date rule would refuse their cash).
test.afterEach(async ({ page }) => {
  try { await setSystemDate(page, null); await closeOpenCash(page); } catch { /* best effort */ }
});

test('SD-00 setup: filled stock that existed before the back date', async ({ page }) => {
  // Opening stock dated ten days ago, so cylinders are free on the back date (SD-06, SD-07, SD-08).
  await login(page);
  await setSystemDate(page, null);
  const d = new Date(today.getTime() - 10 * 86400000);
  await page.goto('inventory/opening', { waitUntil: 'load' });
  await page.locator('button[onclick="prepareAdd()"]').click();
  await page.fill('#inventoryDate', iso(d));
  await page.selectOption('#cylinderTypeId', { index: 1 });
  await page.fill('#quantity', '3');
  await page.fill('#actualGasWeight', '11.8');
  if (await page.locator('#cylinderCost').count()) await page.fill('#cylinderCost', '4000');
  await Promise.all([page.waitForLoadState('load'), page.getByRole('button', { name: 'Save Opening Inventory' }).click()]);
  await expect(page.locator('.alert-danger')).toHaveCount(0);
});

test('SD-01 top bar shows the System Date after the branch switch, today by default', async ({ page }) => {
  await login(page);
  await setSystemDate(page, null);
  const box = page.locator('#systemDateBox');
  await expect(box).toBeVisible();
  await expect(box).toHaveAttribute('data-state', 'today');
  await expect(page.locator('#systemDateLabel')).toHaveText(dmy(today));
  await expect(page.locator('#systemDateNotice')).toHaveCount(0);
  // Placed after the branch switch in the top bar.
  const order = await page.evaluate(() => {
    const bar = document.querySelector('.topbar');
    const b = bar.querySelector('#branchSwitch') || bar.querySelector('.dropdown, span.small');
    const s = bar.querySelector('#systemDateBox');
    return b && s ? (b.compareDocumentPosition(s) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0 : false;
  });
  expect(order).toBeTruthy();
});

test('SD-02 a back date is highlighted, shown on every page and kept until changed', async ({ page }) => {
  await login(page);
  await setSystemDate(page, back);
  await expect(page.locator('#systemDateBox')).toHaveAttribute('data-state', 'past');
  await expect(page.locator('#systemDateBadge')).toHaveText('Back-dated');
  await expect(page.locator('#systemDateNotice')).toContainText(dmy(back));
  for (const path of ['sales', 'purchases', 'cash', 'inventory', 'reports']) {
    await page.goto(path, { waitUntil: 'load' });
    await expect(page.locator('#systemDateLabel')).toHaveText(dmy(back));
  }
  // Sign out and in again: the date stays.
  await page.goto('logout');
  await login(page);
  await expect(page.locator('#systemDateLabel')).toHaveText(dmy(back));
});

test('SD-03 a future date is allowed and labelled Future', async ({ page }) => {
  await login(page);
  const future = new Date(today.getTime() + 2 * 86400000);
  await setSystemDate(page, future);
  await expect(page.locator('#systemDateBox')).toHaveAttribute('data-state', 'future');
  await expect(page.locator('#systemDateBadge')).toHaveText('Future');
  await setSystemDate(page, null);
  await expect(page.locator('#systemDateBox')).toHaveAttribute('data-state', 'today');
});

test('SD-04 screens open on the System Date (POS time, purchase date, receipt date, sale history)', async ({ page }) => {
  await login(page);
  await setSystemDate(page, back);
  await page.goto('sales', { waitUntil: 'load' });
  await expect(page.locator('input[name="transaction_at"]')).toHaveValue(new RegExp('^' + iso(back) + 'T'));
  await page.goto('purchases', { waitUntil: 'load' });
  await expect(page.locator('#purchaseDate')).toHaveValue(new RegExp('^' + iso(back) + 'T'));
  await page.goto('receipts', { waitUntil: 'load' });
  await expect(page.locator('#receiveDate')).toHaveValue(iso(back));
  await page.goto('sales/history', { waitUntil: 'load' });
  await expect(page.locator('#historyFrom')).toHaveValue(iso(back));
  await expect(page.locator('#historyTo')).toHaveValue(iso(back));
});

test('SD-05 counter cash: one session per System Date, cash of another date is refused with a popup', async ({ page }) => {
  await login(page);
  await setSystemDate(page, null);
  await closeOpenCash(page);
  await openCash(page); // session of today
  await setSystemDate(page, back);
  // A manual cash entry on the back date must not go into today's session.
  await page.goto('cash', { waitUntil: 'load' });
  const move = page.locator('form[action$="cash/move"]');
  await move.locator('input[name="amount"]').fill('10');
  await Promise.all([page.waitForLoadState('load'), move.locator('button').first().click()]);
  const modal = page.locator('#lpgConflictModal');
  await expect(modal).toBeVisible();
  await expect(modal).toContainText('Each counter session belongs to one date');
  await expect(modal).toContainText('What you can do');
  await expect(modal.getByRole('link', { name: 'Go to Counter Cash' })).toBeVisible();
  await expect(modal.getByRole('button', { name: /Change System Date to/ })).toBeVisible();
  // The popup's fix: go back to the session's date.
  // The button saves the date, then reloads the page: wait for that reload.
  await Promise.all([page.waitForEvent('load', { timeout: 60_000 }), modal.getByRole('button', { name: /Change System Date to/ }).click()]);
  await expect(page.locator('#systemDateBox')).toHaveAttribute('data-state', 'today');
});

test('SD-06 a back-dated cash sale is dated the System Date and goes into that day\'s session', async ({ page }) => {
  await login(page);
  await setSystemDate(page, null);
  await closeOpenCash(page);
  await setSystemDate(page, back);
  await openCash(page);
  await page.goto('cash', { waitUntil: 'load' });
  await expect(page.locator('body')).toContainText(dmy(back).slice(0, 6)); // session opened on the back date
  const t = await stockedType(page);
  test.skip(!t, 'No filled stock that was free on the back date.');
  const r = await postWalkinGas(page, t[0], 1, 300, `${iso(back)}T${pad(today.getHours())}:${pad(today.getMinutes())}`);
  expect(r.json.success, JSON.stringify(r.json)).toBeTruthy();
  await page.goto(`sales/history?from=${iso(back)}&to=${iso(back)}`, { waitUntil: 'load' });
  await expect(page.locator('body')).toContainText(r.json.sale_no);
  await closeOpenCash(page);
});

test('SD-07 a posting before a later movement of the same cylinder is blocked with an explained popup', async ({ page }) => {
  await login(page);
  await setSystemDate(page, null);
  await closeOpenCash(page);
  await openCash(page);
  const t = await stockedType(page);
  test.skip(!t, 'No filled stock.');
  const sale = await postWalkinGas(page, t[0], 1, 300, `${iso(today)}T${pad(today.getHours())}:${pad(today.getMinutes())}`);
  expect(sale.json.success, JSON.stringify(sale.json)).toBeTruthy();
  await closeOpenCash(page);
  // Go back three days and void today's sale: the void would put the gas back before the sale took it.
  await setSystemDate(page, back);
  await openCash(page);
  await page.goto('sales', { waitUntil: 'load' });
  const token = await csrf(page);
  await page.evaluate(({ id, token }) => {
    const f = document.createElement('form');
    f.method = 'post'; f.action = 'sales/void/' + id;
    f.innerHTML = `<input name="csrf_test_name" value="${token}"><input name="void_reason" value="System Date test">`;
    document.body.appendChild(f); f.submit();
  }, { id: sale.json.id, token });
  await page.waitForLoadState('load');
  const modal = page.locator('#lpgConflictModal');
  await expect(modal).toBeVisible();
  await expect(modal).toContainText('Why it was blocked');
  await expect(modal).toContainText('already used after ' + dmy(back));
  await expect(modal).toContainText(sale.json.sale_no);
  await expect(modal).toContainText('What you can do');
  await expect(modal).toContainText('Use another cylinder');
  // Fix offered in the popup: move the System Date to the later transaction's date.
  // The button saves the date, then reloads the page: wait for that reload.
  await Promise.all([page.waitForEvent('load', { timeout: 60_000 }), modal.getByRole('button', { name: /Change System Date to/ }).click()]);
  await expect(page.locator('#systemDateLabel')).toHaveText(dmy(today));
  await closeOpenCash(page);
});

test('SD-08 the POS only offers stock that was free on the System Date', async ({ page }) => {
  await login(page);
  await setSystemDate(page, null);
  const now = await stockedType(page);
  test.skip(!now, 'No filled stock.');
  // Far back, before any stock existed: nothing is offered.
  await setSystemDate(page, new Date(2000, 0, 3));
  const then = await stockedType(page);
  expect(then).toBeNull();
  await setSystemDate(page, null);
});

test('SD-09 each branch has its own System Date', async ({ page }) => {
  await login(page);
  const branches = await page.locator('.dropdown-menu button.dropdown-item').count();
  test.skip(branches < 2, 'Only one branch.');
  await setSystemDate(page, back);
  const first = (await page.locator('#branchSwitch').innerText()).trim();
  await page.locator('#branchSwitch').click();
  await Promise.all([page.waitForLoadState('load'), page.locator('.dropdown-menu button.dropdown-item:not([disabled])').first().click()]);
  await expect(page.locator('#systemDateBox')).toHaveAttribute('data-state', 'today');
  await page.locator('#branchSwitch').click();
  await Promise.all([page.waitForLoadState('load'), page.locator('.dropdown-menu button.dropdown-item', { hasText: first }).first().click()]);
  await expect(page.locator('#systemDateLabel')).toHaveText(dmy(back));
  await setSystemDate(page, null);
});

test('SD-10 every System Date change is in the audit log', async ({ page }) => {
  await login(page);
  await setSystemDate(page, back);
  await setSystemDate(page, null);
  await page.goto('audit', { waitUntil: 'load' });
  await expect(page.locator('body')).toContainText('SYSTEM_DATE_CHANGE');
});
