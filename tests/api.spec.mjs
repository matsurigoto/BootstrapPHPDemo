// @ts-check
import { test, expect } from '@playwright/test';
import { login } from './helpers.mjs';

test.describe('Builder API 儲存', () => {
  test('admin 可透過 API 修改 Form 1 標題', async ({ page, context, request }) => {
    await login(page, 'admin', 'admin');
    const cookies = await context.cookies();
    const cookieHeader = cookies.map((c) => `${c.name}=${c.value}`).join('; ');

    const get = await request.get('/api/forms/1', { headers: { Cookie: cookieHeader } });
    const form = await get.json();
    const csrf = form._csrf;
    expect(typeof csrf).toBe('string');

    const newTitle = '2026 員工滿意度調查 (E2E)';
    const payload = {
      basic: {
        title: newTitle,
        description: form.description,
        locale_default: form.locale_default,
        audience_mode: form.audience_mode,
        require_login: form.require_login,
        allow_multi: form.allow_multi,
        start_at: null,
        end_at: null,
      },
      pages: [],
      questions: (form.questions || []).map((q, i) => ({
        id: q.id,
        client_id: 'q_' + q.id,
        sort_no: i,
        type: q.type,
        label: q.label,
        required: q.required,
        config: q.config || {},
        options: (q.options || []).map((o, j) => ({
          id: o.id, sort_no: j, label: o.label, opt_value: o.opt_value,
        })),
        rules: q.rules || [],
      })),
      audiences: [],
    };

    const save = await request.post('/api/forms/1', {
      data: payload,
      headers: {
        Cookie: cookieHeader,
        'X-CSRF-Token': csrf,
        'Content-Type': 'application/json',
      },
    });
    expect(save.status()).toBe(200);
    const j = await save.json();
    expect(j.ok).toBe(true);

    // 驗證 GET 後 title 已變
    const after = await request.get('/api/forms/1', { headers: { Cookie: cookieHeader } });
    const afterJson = await after.json();
    expect(afterJson.title).toBe(newTitle);
  });

  test('沒帶 CSRF 的 API 儲存應被擋 (419)', async ({ page, context, request }) => {
    await login(page, 'admin', 'admin');
    const cookies = await context.cookies();
    const cookieHeader = cookies.map((c) => `${c.name}=${c.value}`).join('; ');
    const resp = await request.post('/api/forms/1', {
      data: { basic: {}, pages: [], questions: [], audiences: [] },
      headers: { Cookie: cookieHeader, 'Content-Type': 'application/json' },
      failOnStatusCode: false,
    });
    expect(resp.status()).toBe(419);
  });
});
