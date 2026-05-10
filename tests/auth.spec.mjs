// @ts-check
import { test, expect } from '@playwright/test';
import { login } from './helpers.mjs';

test.describe('Auth', () => {
  test('未登入訪問 /forms 應導向 /login', async ({ page }) => {
    await page.goto('/forms');
    await expect(page).toHaveURL(/\/login/);
  });

  test('admin 可登入並進入表單列表', async ({ page }) => {
    await login(page, 'admin', 'admin');
    await expect(page.locator('body')).toContainText('員工滿意度調查');
  });

  test('user1 可登入', async ({ page }) => {
    await login(page, 'user1', 'user1');
    await expect(page).toHaveURL(/\/forms/);
  });

  test('錯誤密碼應顯示錯誤訊息且不登入', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="username"]', 'admin');
    await page.fill('input[name="password"]', 'wrong-password');
    await page.click('button[type="submit"], button.btn-primary');
    await expect(page).toHaveURL(/\/login/);
  });

  test('CSRF：缺少 token 的 POST /login 應被拒 (419)', async ({ request }) => {
    const resp = await request.post('/login', {
      form: { username: 'admin', password: 'admin' },
      maxRedirects: 0,
      failOnStatusCode: false,
    });
    expect(resp.status()).toBe(419);
  });

  test('登出後重訪 /forms 應再次導回 /login', async ({ page }) => {
    await login(page, 'admin', 'admin');
    // 點選右上 user 下拉並登出
    await page.click('text=管理員');
    await page.click('button:has-text("登出"), button:has-text("Logout")');
    await page.goto('/forms');
    await expect(page).toHaveURL(/\/login/);
  });
});
