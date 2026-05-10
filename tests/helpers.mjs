// @ts-check
import { expect } from '@playwright/test';

/**
 * 以表單登入。預設 admin/admin。
 * @param {import('@playwright/test').Page} page
 * @param {string} username
 * @param {string} password
 */
export async function login(page, username = 'admin', password = 'admin') {
  await page.goto('/login');
  await page.fill('input[name="username"]', username);
  await page.fill('input[name="password"]', password);
  await Promise.all([
    page.waitForURL(/\/forms(\?|$)/),
    page.click('button[type="submit"], button.btn-primary'),
  ]);
  await expect(page).toHaveURL(/\/forms/);
}
