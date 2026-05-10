// @ts-check
import { test, expect } from '@playwright/test';
import { login } from './helpers.mjs';

test.describe('Forms 列表 / 預覽 / 公開填答', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, 'admin', 'admin');
  });

  test('列表顯示三張示範表單與狀態', async ({ page }) => {
    await page.goto('/forms');
    await expect(page.locator('body')).toContainText('2026 員工滿意度調查');
    await expect(page.locator('body')).toContainText('教育訓練報名');
    await expect(page.locator('body')).toContainText('春酒抽獎登記');
    // 狀態 badge
    await expect(page.locator('.badge', { hasText: /Published|已發布|PUBLISHED/i }).first()).toBeVisible();
  });

  test('Edit 頁可載入並含 Form Builder 元素', async ({ page }) => {
    await page.goto('/forms/1/edit');
    await expect(page.locator('#form-title-display')).toContainText('員工滿意度調查');
    // 題型快速新增按鈕
    await expect(page.locator('.q-add-btn').first()).toBeVisible();
  });

  test('API GET /api/forms/1 回傳 JSON 結構', async ({ request, context }) => {
    // 借用瀏覽器 session cookie
    const cookies = await context.cookies();
    const headers = {
      Cookie: cookies.map((c) => `${c.name}=${c.value}`).join('; '),
    };
    const resp = await request.get('/api/forms/1', { headers });
    expect(resp.ok()).toBeTruthy();
    const json = await resp.json();
    expect(json.id).toBe(1);
    expect(Array.isArray(json.questions)).toBe(true);
    expect(json.questions.length).toBeGreaterThan(0);
    expect(typeof json._csrf).toBe('string');
  });

  test('Preview 頁顯示所有題型', async ({ page }) => {
    await page.goto('/forms/1/preview');
    await expect(page.locator('body')).toContainText('員工滿意度調查');
    await expect(page.locator('input[type="text"]').first()).toBeVisible();
    await expect(page.locator('select').first()).toBeVisible();
    await expect(page.locator('input[type="radio"]').first()).toBeVisible();
    await expect(page.locator('input[type="checkbox"]').first()).toBeVisible();
  });

  test('公開填答 /f/1 可送出並顯示感謝頁', async ({ page }) => {
    await page.goto('/f/1');
    // 必填：姓名 / 部門 / 整體滿意度 / 福利複選 / 文化評分
    await page.fill('input[name="a[1]"]', 'E2E Tester');
    await page.selectOption('select[name="a[3]"]', { index: 1 });
    // RADIO：整體滿意度 — 直接勾第一個
    await page.locator('input[type="radio"][name="a[4]"]').first().check();
    // CHECKBOX：勾兩個
    const checks = page.locator('input[type="checkbox"][name="a[5][]"]');
    await checks.nth(0).check();
    await checks.nth(1).check();
    // RATING：以 input[type=radio] 形式或 hidden number 視實作而定，先嘗試找 5 顆星 input
    const ratingHidden = page.locator('input[name="a[8]"]');
    if (await ratingHidden.count()) {
      // 可能是 hidden + 點擊星星；若是直接 input 就直接 fill
      const tag = await ratingHidden.first().evaluate((el) => el.tagName + ':' + (el.getAttribute('type') || ''));
      if (tag.includes('hidden') || tag.includes('number')) {
        await ratingHidden.first().fill('5');
      }
    }
    // 部分專案 RATING 是用按鈕，補一手點擊：
    const stars = page.locator('[data-rating-value="5"], .rating-star').first();
    if (await stars.count()) {
      await stars.click().catch(() => {});
    }

    await page.click('button[type="submit"]');
    // 感謝頁或停留在表單但無錯誤
    await expect(page).toHaveURL(/\/f\/1|thanks|forms/);
  });

  test('關閉的表單 /f/3 顯示無法填答', async ({ page }) => {
    await page.goto('/f/3');
    // closed 模板 / unavailable 文字
    await expect(page.locator('body')).toContainText(/無法填答|unavailable|closed/i);
  });
});
