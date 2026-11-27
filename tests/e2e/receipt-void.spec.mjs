// Cash receipt void: where the cash reversal goes (owner decision 2026-11-27).
// When the receipt's counter session is not the one open now, the void popup asks: the receipt's own
// session (dated the receipt date, with a warning when that session is closed and counted) or the session
// open now (dated now).
//
// Run: npx playwright test tests/e2e/receipt-void.spec.mjs --headed
// Needs admin / admin123. Data: creates customer "RV Customer", posts small cash receipts, opens and closes
// counter sessions (leaves no session open).
import { test, expect } from '@playwright/test';

test.describe.configure({ mode: 'serial' });

async function login(page) {
  await page.goto('login', { waitUntil: 'load' });
  await page.fill('input[name="login"]', 'admin');
  await page.fill('input[name="password"]', 'admin123');
  await Promise.all([page.waitForURL(/\/(dashboard|branches\/select)/), page.click('form:has(input[name="login"]) button[type="submit"]')]);
  if (page.url().includes('branches/select')) {
    await Promise.all([page.waitForURL(/\/dashboard/), page.locator('.branch-card').first().click()]);
  }
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
  if (await open.count()) await Promise.all([page.waitForLoadState('load'), open.first().click()]);
  await expect(page.locator('form[action$="cash/close"]')).toHaveCount(1);
}

async function customerId(page) {
  await page.goto('customers', { waitUntil: 'load' });
  if (!(await page.locator('table tbody tr', { hasText: 'RV Customer' }).count())) {
    await page.locator('[data-bs-target="#form"]').first().click();
    await page.fill('#form input[name="name"]', 'RV Customer');
    await Promise.all([page.waitForLoadState('load'), page.locator('#form button.btn-primary').click()]);
  }
  await page.goto('receipts', { waitUntil: 'load' });
  return page.locator('#receiptCustomer option', { hasText: 'RV Customer' }).first().getAttribute('value');
}

/** Posts a cash receipt and returns its number. */
async function cashReceipt(page, id, amount) {
  await page.goto('receipts', { waitUntil: 'load' });
  await page.selectOption('#receiptCustomer', id);
  await page.fill('#receiptAmount', String(amount));
  await page.selectOption('#receiptMode', 'cash');
  await Promise.all([page.waitForLoadState('load'), page.locator('form[action$="receipts/save"] button[type="submit"], form[action$="receipts/save"] button.btn-primary').first().click()]);
  const overlay = page.locator('#customerReceiptOverlay.is-visible');
  if (await overlay.count()) await page.locator('#customerReceiptClose').click();
  const text = await page.locator('.alert-success').filter({ hasText: /Receipt R\d+/ }).first().innerText();
  const m = text.match(/R\d{14}-\d{3}/);
  expect(m, 'receipt number on screen').not.toBeNull();
  return m[0];
}

/** Opens the void popup for a receipt on the customer ledger. */
async function openVoid(page, id, receiptNo) {
  await page.goto(`customers/ledger/${id}`, { waitUntil: 'load' });
  const overlay = page.locator('#customerReceiptOverlay.is-visible');
  if (await overlay.count()) await page.locator('#customerReceiptClose').click();
  // The receipt rows (with Void) are on the ledger's Details tab.
  await page.locator('[data-bs-target="#customerDetails"]').first().click();
  await expect(page.locator('#customerDetails')).toBeVisible();
  await page.locator(`.sl-void-btn[data-ref="${receiptNo}"]`).first().click();
  await expect(page.locator('#slVoidModal')).toBeVisible();
}

test('RV-01 same session: no choice is asked, the reversal goes into it', async ({ page }) => {
  test.setTimeout(240_000);
  await login(page);
  await closeOpenCash(page);
  await openCash(page);
  const id = await customerId(page);
  const no = await cashReceipt(page, id, 50);
  await openVoid(page, id, no);
  await expect(page.locator('#slVoidCash')).toBeHidden();
  await page.fill('#slVoidForm textarea[name="void_reason"]', 'RV-01 test');
  await Promise.all([page.waitForLoadState('load'), page.locator('#slVoidForm button[type="submit"]').click()]);
  await expect(page.locator('.alert-success').filter({ hasText: 'Customer receipt voided' })).toHaveCount(1);
  await closeOpenCash(page);
});

test('RV-02 different session: the user chooses the session open now', async ({ page }) => {
  test.setTimeout(240_000);
  await login(page);
  await closeOpenCash(page);
  await openCash(page);
  const id = await customerId(page);
  const no = await cashReceipt(page, id, 60);
  await closeOpenCash(page);
  await openCash(page); // a new session: not the receipt's
  await openVoid(page, id, no);
  await expect(page.locator('#slVoidCash')).toBeVisible();
  await expect(page.locator('#slOrigLabel')).toContainText('closed');
  await page.check('#slCashCur');
  await expect(page.locator('#slCashClosedWarn')).toBeHidden();
  await page.fill('#slVoidForm textarea[name="void_reason"]', 'RV-02 test');
  await Promise.all([page.waitForLoadState('load'), page.locator('#slVoidForm button[type="submit"]').click()]);
  await expect(page.locator('.alert-success').filter({ hasText: 'Customer receipt voided' })).toHaveCount(1);
  // Cash History shows the reversal, posted into the session open now.
  await page.goto('cash/history', { waitUntil: 'load' });
  await expect(page.locator('body')).toContainText(`Cash reversal of voided customer receipt ${no} (current session)`);
  await closeOpenCash(page);
});

test('RV-03 different session: the receipt session is closed, choosing it shows the warning', async ({ page }) => {
  test.setTimeout(240_000);
  await login(page);
  await closeOpenCash(page);
  await openCash(page);
  const id = await customerId(page);
  const no = await cashReceipt(page, id, 70);
  await closeOpenCash(page);
  await openCash(page);
  await openVoid(page, id, no);
  await page.check('#slCashOrig');
  await expect(page.locator('#slCashClosedWarn')).toBeVisible();
  await expect(page.locator('#slCashClosedWarn')).toContainText('will no longer match the counted cash');
  await page.fill('#slVoidForm textarea[name="void_reason"]', 'RV-03 test');
  await Promise.all([page.waitForLoadState('load'), page.locator('#slVoidForm button[type="submit"]').click()]);
  await expect(page.locator('.alert-success').filter({ hasText: 'Customer receipt voided' })).toHaveCount(1);
  await closeOpenCash(page);
});

test('RV-04 a choice is required: the popup will not submit without one', async ({ page }) => {
  test.setTimeout(240_000);
  await login(page);
  await closeOpenCash(page);
  await openCash(page);
  const id = await customerId(page);
  const no = await cashReceipt(page, id, 80);
  await closeOpenCash(page);
  await openCash(page);
  await openVoid(page, id, no);
  await page.fill('#slVoidForm textarea[name="void_reason"]', 'RV-04 test');
  await page.locator('#slVoidForm button[type="submit"]').click();
  // The browser blocks the submit (radio required): the popup stays open, nothing is voided.
  await expect(page.locator('#slVoidModal')).toBeVisible();
  expect(await page.locator('#slCashOrig').evaluate(el => el.validity.valueMissing)).toBe(true);
  await page.keyboard.press('Escape');
  await closeOpenCash(page);
});
