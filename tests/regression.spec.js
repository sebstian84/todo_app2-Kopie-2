import { test, expect } from '@playwright/test';

const APP_URL = 'https://aufgaben.runasp.net/';
const USERNAME = 'frost0xx';
const PASSWORD = '381984';

/**
 * Helper: Login if the login overlay is shown.
 */
async function loginIfNeeded(page) {
  const loginOverlay = page.locator('.login-overlay');
  if (await loginOverlay.isVisible({ timeout: 3000 }).catch(() => false)) {
    await loginOverlay.locator('input[type="text"]').fill(USERNAME);
    await loginOverlay.locator('input[type="password"]').fill(PASSWORD);
    await loginOverlay.locator('button:has-text("Anmelden")').click();
    await expect(loginOverlay).toBeHidden({ timeout: 10000 });
  }
}

test.describe('Flattask Regression Suite', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto(APP_URL, { waitUntil: 'networkidle' });
    await loginIfNeeded(page);
    // Verify we're past the login
    await expect(page.locator('.todo-list')).toBeVisible({ timeout: 10000 });
  });

  // ===== TC-AUTH: Authentication =====

  test('TC-AUTH-01: Login succeeds with valid credentials', async ({ page }) => {
    // If beforeEach passed, login worked. Double-check no login overlay.
    await expect(page.locator('.login-overlay')).toBeHidden();
  });

  // ===== TC-TODO: Task Management =====

  test('TC-TODO-01: Create a new todo and verify persistence', async ({ page }) => {
    const todoName = `Regression Test ${Date.now()}`;

    // Open new form via the "Neu" button
    await page.locator('button[title="Neu"]').first().click();
    await expect(page.locator('.new-todo-form')).toBeVisible();

    // Fill in the name
    await page.locator('.new-todo-form input[type="text"]').first().fill(todoName);

    // Submit
    await page.locator('.new-todo-form button[type="submit"]').click();

    // Verify it appears
    await expect(page.locator('.todo-item').filter({ hasText: todoName })).toBeVisible({ timeout: 5000 });

    // Reload and verify persistence
    await page.reload({ waitUntil: 'networkidle' });
    await loginIfNeeded(page);
    await expect(page.locator('.todo-item').filter({ hasText: todoName })).toBeVisible({ timeout: 10000 });
  });

  test('TC-TODO-02: Toggle todo status (open -> done)', async ({ page }) => {
    const firstTodo = page.locator('.todo-item').first();
    await expect(firstTodo).toBeVisible();

    // Click the status toggle
    await firstTodo.locator('button[title*="erledigt"], button[title*="offen"]').first().click();
    await page.waitForTimeout(500);
  });

  // ===== TC-NOTE: Notes / Diary =====

  test('TC-NOTE-01: Open notes view and verify it renders', async ({ page }) => {
    // Click StickyNote icon — try all possible containers
    await page.locator('button').filter({ has: page.locator('.lucide-sticky-note') }).first().click();

    await expect(page.locator('.notes-view')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('.notes-editor-card')).toBeVisible();
    await expect(page.locator('.note-date-header').first()).toContainText('Heute');
  });

  // ===== TC-TIME: Time Tracking =====

  test('TC-TIME-01: Open time tracking view and verify it renders', async ({ page }) => {
    await page.locator('button').filter({ has: page.locator('.lucide-alarm-clock') }).first().click();

    await expect(page.locator('.time-view')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('.timer-controls')).toBeVisible();
  });

  test('TC-TIME-02: Start and stop timer', async ({ page }) => {
    await page.locator('button').filter({ has: page.locator('.lucide-alarm-clock') }).first().click();
    await expect(page.locator('.time-view')).toBeVisible({ timeout: 5000 });

    // Stop if already running
    const stopBtn = page.locator('button.stop-btn');
    if (await stopBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
      await stopBtn.click();
      await page.waitForTimeout(500);
    }

    // Start
    await page.locator('button.start-btn').click();
    await expect(page.locator('.live-counter-large')).toBeVisible({ timeout: 3000 });

    // Wait 2s to let it tick
    await page.waitForTimeout(2000);

    // Stop
    await page.locator('button.stop-btn').click();
    await expect(page.locator('button.start-btn')).toBeVisible({ timeout: 3000 });
  });

  // ===== TC-VIEW: View Switching =====

  test('TC-VIEW-01: All main views are reachable without crash', async ({ page }) => {
    // Main view
    await expect(page.locator('.todo-list')).toBeVisible();

    // Notes
    await page.locator('button').filter({ has: page.locator('.lucide-sticky-note') }).first().click();
    await expect(page.locator('.notes-view')).toBeVisible({ timeout: 5000 });

    // Back
    await page.locator('.notes-view button:has-text("Zurück")').click();
    await expect(page.locator('.todo-list')).toBeVisible({ timeout: 5000 });

    // Time tracking
    await page.locator('button').filter({ has: page.locator('.lucide-alarm-clock') }).first().click();
    await expect(page.locator('.time-view')).toBeVisible({ timeout: 5000 });

    // Back
    await page.locator('.time-view button:has-text("Zurück")').click();
    await expect(page.locator('.todo-list')).toBeVisible({ timeout: 5000 });

    // Stats
    await page.locator('button[title="Statistik"]').click();
    await expect(page.locator('.stats-dashboard, .dashboard-header').first()).toBeVisible({ timeout: 5000 });
  });

});
