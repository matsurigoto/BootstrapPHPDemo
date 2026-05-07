/**
 * FormHub 表單建構器
 * - 從 window.FORMHUB_INIT 載入初始 schema
 * - 在畫布上顯示題目卡片，可拖曳排序、編輯、刪除
 * - 儲存時 PUT JSON 到 /api/forms/{id}
 */
(function () {
    'use strict';

    const T = window.FORMHUB_LANG;
    const BASE = window.FORMHUB_BASE || '';
    const data = window.FORMHUB_INIT;
    let qSeq = 1;
    let pSeq = 1;
    const canvas = document.getElementById('builder-canvas');

    // 初始化 client_id
    (data.questions || []).forEach((q) => { q.client_id = 'q_' + (qSeq++); });
    (data.pages || []).forEach((p) => { p.client_id = 'p_' + (pSeq++); });

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function questionCard(q) {
        const opts = (q.options || []).map((o, i) => `
            <div class="input-group input-group-sm mb-1" data-opt-row>
                <input class="form-control" data-opt-label value="${escapeHtml(o.label)}" placeholder="Label">
                <input class="form-control" data-opt-value value="${escapeHtml(o.opt_value || o.value || '')}" placeholder="Value">
                <button type="button" class="btn btn-outline-danger" data-opt-del><i class="bi bi-x"></i></button>
            </div>`).join('');

        const showOpts = ['SELECT','CHECKBOX','RADIO'].includes(q.type);
        const cfg = q.config || {};

        return `
        <div class="card mb-2 q-card" data-cid="${q.client_id}" data-id="${q.id || ''}" data-type="${q.type}">
            <div class="card-body">
                <div class="d-flex align-items-start gap-2">
                    <span class="q-handle text-muted" style="cursor:grab;font-size:1.3rem;"><i class="bi bi-grip-vertical"></i></span>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-light text-dark border"><i class="bi bi-tag"></i> ${T['q.type.' + q.type] || q.type}</span>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-q-del><i class="bi bi-trash"></i></button>
                        </div>
                        <div class="mb-2">
                            <input class="form-control" data-q-label value="${escapeHtml(q.label)}" placeholder="${T['q.label']}">
                        </div>
                        <div class="mb-2">
                            <input class="form-control form-control-sm" data-q-help value="${escapeHtml(q.help_text || '')}" placeholder="${T['q.help_text']}">
                        </div>
                        <div class="d-flex flex-wrap gap-3 align-items-center mb-2">
                            <label class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="checkbox" data-q-required ${q.required === 'Y' ? 'checked' : ''}>
                                <span class="form-check-label">${T['q.required']}</span>
                            </label>
                            ${configFields(q.type, cfg)}
                        </div>
                        ${showOpts ? `
                        <div class="border rounded p-2 mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong class="small">${T['q.options']}</strong>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-opt-add><i class="bi bi-plus"></i> ${T['q.add_option']}</button>
                            </div>
                            <div data-opt-list>${opts}</div>
                        </div>` : ''}
                        <details class="small">
                            <summary class="text-muted">${T['q.rules']}</summary>
                            <div data-rules class="mt-2"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-1" data-rule-add>+ Rule</button>
                        </details>
                    </div>
                </div>
            </div>
        </div>`;
    }

    function configFields(type, cfg) {
        switch (type) {
            case 'NUMBER':
                return `<input type="number" class="form-control form-control-sm" style="max-width:100px" data-cfg="min" placeholder="${T['q.config.min']}" value="${cfg.min ?? ''}">
                        <input type="number" class="form-control form-control-sm" style="max-width:100px" data-cfg="max" placeholder="${T['q.config.max']}" value="${cfg.max ?? ''}">`;
            case 'TEXT':
            case 'TEXTAREA':
                return `<input type="text" class="form-control form-control-sm" style="max-width:200px" data-cfg="placeholder" placeholder="${T['q.config.placeholder']}" value="${escapeHtml(cfg.placeholder || '')}">`;
            case 'RATING':
                return `<input type="number" min="3" max="10" class="form-control form-control-sm" style="max-width:100px" data-cfg="stars" placeholder="${T['q.config.stars']}" value="${cfg.stars ?? 5}">`;
            case 'FILE':
                return `<input type="text" class="form-control form-control-sm" style="max-width:200px" data-cfg="accept" placeholder="${T['q.config.accept']} (.pdf,.jpg)" value="${escapeHtml(cfg.accept || '')}">`;
            case 'CHECKBOX':
                return `<input type="number" class="form-control form-control-sm" style="max-width:80px" data-cfg="min" placeholder="${T['q.config.min']}" value="${cfg.min ?? ''}">
                        <input type="number" class="form-control form-control-sm" style="max-width:80px" data-cfg="max" placeholder="${T['q.config.max']}" value="${cfg.max ?? ''}">`;
            default: return '';
        }
    }

    function ruleRow(r, sourceList) {
        const opts = sourceList.map(q =>
            `<option value="${q.client_id}" ${r.source_client_id === q.client_id ? 'selected' : ''}>${escapeHtml(q.label || q.client_id)}</option>`
        ).join('');
        const op = (o) => `<option value="${o}" ${r.operator === o ? 'selected' : ''}>${o}</option>`;
        const ac = (o) => `<option value="${o}" ${r.action === o ? 'selected' : ''}>${o}</option>`;
        return `<div class="input-group input-group-sm mb-1" data-rule-row>
            <select class="form-select" data-rule-src><option value="">— source —</option>${opts}</select>
            <select class="form-select" style="max-width:90px" data-rule-op>${['EQ','NEQ','IN','GT','LT','CONTAINS'].map(op).join('')}</select>
            <input class="form-control" data-rule-val placeholder="value" value="${escapeHtml(r.compare_value || '')}">
            <select class="form-select" style="max-width:90px" data-rule-act>${['SHOW','HIDE'].map(ac).join('')}</select>
            <button type="button" class="btn btn-outline-danger" data-rule-del><i class="bi bi-x"></i></button>
        </div>`;
    }

    function render() {
        canvas.innerHTML = '';
        // 簡化：暫不分頁 UI 顯示，所有題目線性
        const list = document.createElement('div');
        list.id = 'q-list';
        canvas.appendChild(list);
        if (!data.questions.length) {
            list.innerHTML = '<div class="text-muted text-center py-4">點選左側題型新增題目</div>';
            return;
        }
        data.questions.forEach((q) => {
            list.insertAdjacentHTML('beforeend', questionCard(q));
        });
        // rules
        data.questions.forEach((q) => {
            const card = list.querySelector(`[data-cid="${q.client_id}"]`);
            const rulesEl = card.querySelector('[data-rules]');
            const sourceList = data.questions.filter(x => x.client_id !== q.client_id);
            (q.rules || []).forEach((r) => {
                if (!r.source_client_id && r.source_question_id) {
                    const m = data.questions.find(qq => Number(qq.id) === Number(r.source_question_id));
                    if (m) r.source_client_id = m.client_id;
                }
                rulesEl.insertAdjacentHTML('beforeend', ruleRow(r, sourceList));
            });
        });

        new Sortable(list, {
            animation: 150,
            handle: '.q-handle',
            onEnd: () => {
                const order = Array.from(list.querySelectorAll('[data-cid]')).map(el => el.dataset.cid);
                data.questions.sort((a, b) => order.indexOf(a.client_id) - order.indexOf(b.client_id));
                data.questions.forEach((q, i) => q.sort_no = i);
            }
        });
    }

    // 事件：新增題目
    document.querySelectorAll('.q-add-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const t = btn.dataset.type;
            const q = {
                client_id: 'q_' + (qSeq++),
                id: null,
                type: t,
                label: T['q.type.' + t] || t,
                help_text: '',
                required: 'N',
                sort_no: data.questions.length,
                config: t === 'RATING' ? { stars: 5 } : {},
                options: ['SELECT','RADIO','CHECKBOX'].includes(t) ? [
                    { label: '選項 1', opt_value: '1', sort_no: 0 },
                    { label: '選項 2', opt_value: '2', sort_no: 1 },
                ] : [],
                rules: [],
            };
            data.questions.push(q);
            render();
        });
    });

    // 委派事件
    canvas.addEventListener('click', (e) => {
        const card = e.target.closest('[data-cid]');
        if (!card) return;
        const cid = card.dataset.cid;
        const q = data.questions.find(x => x.client_id === cid);
        if (!q) return;

        if (e.target.closest('[data-q-del]')) {
            if (!confirm('刪除此題？')) return;
            data.questions = data.questions.filter(x => x.client_id !== cid);
            render();
        } else if (e.target.closest('[data-opt-add]')) {
            q.options.push({ label: '新選項', opt_value: '', sort_no: q.options.length });
            render();
        } else if (e.target.closest('[data-opt-del]')) {
            const row = e.target.closest('[data-opt-row]');
            const list = card.querySelector('[data-opt-list]');
            const idx = Array.from(list.children).indexOf(row);
            q.options.splice(idx, 1);
            render();
        } else if (e.target.closest('[data-rule-add]')) {
            q.rules.push({ source_client_id: '', operator: 'EQ', compare_value: '', action: 'SHOW' });
            render();
        } else if (e.target.closest('[data-rule-del]')) {
            const row = e.target.closest('[data-rule-row]');
            const wrapper = card.querySelector('[data-rules]');
            const idx = Array.from(wrapper.children).indexOf(row);
            q.rules.splice(idx, 1);
            render();
        }
    });

    // 同步輸入到 model（送出前抓取）
    function sync() {
        document.querySelectorAll('#q-list [data-cid]').forEach((card) => {
            const cid = card.dataset.cid;
            const q = data.questions.find(x => x.client_id === cid);
            if (!q) return;
            q.label = card.querySelector('[data-q-label]').value;
            q.help_text = card.querySelector('[data-q-help]').value;
            q.required = card.querySelector('[data-q-required]').checked ? 'Y' : 'N';
            const cfg = {};
            card.querySelectorAll('[data-cfg]').forEach((inp) => {
                const v = inp.value;
                if (v !== '') cfg[inp.dataset.cfg] = inp.type === 'number' ? Number(v) : v;
            });
            q.config = cfg;
            const optList = card.querySelector('[data-opt-list]');
            if (optList) {
                q.options = Array.from(optList.querySelectorAll('[data-opt-row]')).map((row, i) => ({
                    label: row.querySelector('[data-opt-label]').value,
                    value: row.querySelector('[data-opt-value]').value,
                    sort_no: i,
                }));
            }
            const rulesWrap = card.querySelector('[data-rules]');
            if (rulesWrap) {
                q.rules = Array.from(rulesWrap.querySelectorAll('[data-rule-row]')).map(row => ({
                    source_client_id: row.querySelector('[data-rule-src]').value,
                    operator:       row.querySelector('[data-rule-op]').value,
                    compare_value:  row.querySelector('[data-rule-val]').value,
                    action:         row.querySelector('[data-rule-act]').value,
                })).filter(r => r.source_client_id);
            }
        });
    }

    // 儲存
    document.getElementById('btn-save').addEventListener('click', async () => {
        sync();
        const payload = {
            basic: {
                title: document.getElementById('f-title').value,
                description: document.getElementById('f-desc').value,
                locale_default: document.getElementById('f-locale').value,
                audience_mode: document.getElementById('f-audience').value,
                require_login: document.getElementById('f-login').value,
                allow_multi: document.getElementById('f-multi').value,
                start_at: document.getElementById('f-start').value || null,
                end_at:   document.getElementById('f-end').value || null,
            },
            pages: data.pages.map((p, i) => ({ id: p.id || null, client_id: p.client_id, sort_no: i, title: p.title || null })),
            questions: data.questions.map((q, i) => ({
                id: q.id || null,
                client_id: q.client_id,
                sort_no: i,
                type: q.type,
                label: q.label,
                help_text: q.help_text || null,
                required: q.required,
                config: q.config || {},
                options: q.options || [],
                rules: q.rules || [],
            })),
            audiences: parseAudiences(document.getElementById('f-aud-list').value),
        };

        try {
            const res = await fetch(BASE + '/api/forms/' + data.id, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.FORMHUB_CSRF,
                },
                body: JSON.stringify(payload),
            });
            const j = await res.json();
            if (!res.ok || j.error) {
                alert(T['common.fail'] + ': ' + (j.message || j.error || res.status));
                return;
            }
            // 把回傳的 q_map / page_map 套回 model 的 id
            const map = j.result || {};
            (data.questions || []).forEach((q) => {
                if (!q.id && map.q_map && map.q_map[q.client_id]) q.id = map.q_map[q.client_id];
            });
            (data.pages || []).forEach((p) => {
                if (!p.id && map.page_map && map.page_map[p.client_id]) p.id = map.page_map[p.client_id];
            });
            // 更新標題顯示
            document.getElementById('form-title-display').textContent = payload.basic.title;
            // toast
            showToast(T['common.success']);
        } catch (err) {
            alert(T['common.fail'] + ': ' + err.message);
        }
    });

    function parseAudiences(text) {
        return String(text || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean).map(line => {
            const idx = line.indexOf(':');
            if (idx <= 0) return null;
            return { principal_type: line.substring(0, idx).toUpperCase(), principal_value: line.substring(idx + 1).trim() };
        }).filter(Boolean);
    }

    function showToast(msg) {
        const div = document.createElement('div');
        div.className = 'toast-container position-fixed top-0 end-0 p-3';
        div.innerHTML = `<div class="toast align-items-center text-bg-success border-0 show"><div class="d-flex"><div class="toast-body">${escapeHtml(msg)}</div><button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`;
        document.body.appendChild(div);
        setTimeout(() => div.remove(), 2500);
    }

    document.getElementById('btn-add-page').addEventListener('click', () => {
        const title = prompt('分頁標題') || '';
        data.pages.push({ client_id: 'p_' + (pSeq++), id: null, title, sort_no: data.pages.length });
        alert('分頁已加入（簡化版未顯示分頁卡片，將套用至 sort_no）');
    });

    render();
})();
