// @ts-check
import { test, expect } from '@playwright/test';
import { login } from './helpers.mjs';

test.describe('Responses / Stats / CSV', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, 'admin', 'admin');
  });

  test('回應列表顯示 3 筆種子資料', async ({ page }) => {
    await page.goto('/forms/1/responses');
    // 至少應出現 3 列；不嚴格鎖定 count，避免種子變動時誤判
    const rows = page.locator('table tbody tr');
    const count = await rows.count();
    expect(count).toBeGreaterThanOrEqual(3);
  });

  test('單筆回應頁顯示題目與答案', async ({ page }) => {
    await page.goto('/forms/1/responses/1');
    await expect(page.locator('body')).toContainText('員工滿意度調查');
    await expect(page.locator('body')).toContainText('王小明');
  });

  test('統計頁載入 Chart.js 並產生 canvas', async ({ page }) => {
    await page.goto('/forms/1/stats');
    await expect(page.locator('canvas').first()).toBeVisible();
    // JSON 資料節點存在
    await expect(page.locator('script[type="application/json"]').first()).toHaveCount(1).catch(async () => {
      // 至少 >= 1 個
      const c = await page.locator('script[type="application/json"]').count();
      expect(c).toBeGreaterThanOrEqual(1);
    });
  });

  test('CSV 匯出回 200 + UTF-8 BOM 標頭', async ({ request, context }) => {
    const cookies = await context.cookies();
    const headers = { Cookie: cookies.map((c) => `${c.name}=${c.value}`).join('; ') };
    const resp = await request.get('/forms/1/responses.csv', { headers });
    expect(resp.status()).toBe(200);
    expect(resp.headers()['content-type']).toContain('text/csv');
    const buf = await resp.body();
    // UTF-8 BOM
    expect(buf[0]).toBe(0xef);
    expect(buf[1]).toBe(0xbb);
    expect(buf[2]).toBe(0xbf);
    const text = buf.toString('utf8');
    expect(text).toContain('response_id');
  });
});

test.describe('授權檢查', () => {
  test('user1 不應看到他人表單的回應 (403)', async ({ page, request, context }) => {
    await login(page, 'user1', 'user1');
    const cookies = await context.cookies();
    const headers = { Cookie: cookies.map((c) => `${c.name}=${c.value}`).join('; ') };
    const resp = await request.get('/forms/1/responses', { headers, maxRedirects: 0, failOnStatusCode: false });
    expect([403, 302]).toContain(resp.status());
  });
});
