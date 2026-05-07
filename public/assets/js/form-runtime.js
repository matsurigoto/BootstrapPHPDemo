/**
 * 表單填答前端：條件顯示（QUESTION_RULES）即時運算 + 基本驗證
 * 規則格式：[{ source_question_id, operator, compare_value, action }]
 *   operator: EQ | NEQ | IN | GT | LT | CONTAINS
 *   action:   SHOW | HIDE
 * 預設行為：若有 SHOW 規則，題目預設「隱藏」直到符合；否則預設顯示，HIDE 規則符合時隱藏。
 */
(function () {
    'use strict';
    const form = document.getElementById('fill-form');
    if (!form) return;

    const blocks = Array.from(form.querySelectorAll('.q-block'));
    const blockMap = {};
    blocks.forEach(b => { blockMap[b.dataset.qid] = b; });

    function getValue(qid) {
        const block = blockMap[qid];
        if (!block) return null;
        // checkbox：多選回 array
        const checkboxes = block.querySelectorAll('input[type="checkbox"]:checked');
        if (checkboxes.length) return Array.from(checkboxes).map(c => c.value);
        const radio = block.querySelector('input[type="radio"]:checked');
        if (radio) return radio.value;
        const select = block.querySelector('select');
        if (select) return select.value;
        const inp = block.querySelector('input,textarea');
        if (inp) return inp.value;
        return null;
    }

    function match(op, sourceVal, cmp) {
        if (sourceVal == null) sourceVal = '';
        const arr = Array.isArray(sourceVal) ? sourceVal.map(String) : [String(sourceVal)];
        const s = String(sourceVal);
        const c = String(cmp ?? '');
        switch (op) {
            case 'EQ': return arr.includes(c);
            case 'NEQ': return !arr.includes(c);
            case 'IN': return c.split(',').map(v => v.trim()).some(v => arr.includes(v));
            case 'CONTAINS': return s.toLowerCase().includes(c.toLowerCase());
            case 'GT': return parseFloat(s) > parseFloat(c);
            case 'LT': return parseFloat(s) < parseFloat(c);
        }
        return false;
    }

    function evaluate() {
        blocks.forEach(block => {
            let rules = [];
            try { rules = JSON.parse(block.dataset.rules || '[]'); } catch (e) {}
            if (!rules.length) { block.style.display = ''; return; }
            const hasShow = rules.some(r => r.action === 'SHOW');
            let visible = !hasShow; // 預設：有 SHOW 規則就隱藏
            rules.forEach(r => {
                const sv = getValue(String(r.source_question_id));
                const m = match(r.operator, sv, r.compare_value);
                if (m && r.action === 'SHOW') visible = true;
                if (m && r.action === 'HIDE') visible = false;
            });
            block.style.display = visible ? '' : 'none';
            // 隱藏的題目移除 required，避免無法送出
            block.querySelectorAll('[required]').forEach(inp => {
                inp.dataset._required = '1';
                inp.removeAttribute('required');
            });
            if (visible) {
                block.querySelectorAll('[data-_required="1"]').forEach(inp => inp.setAttribute('required', ''));
            }
        });
    }

    form.addEventListener('input', evaluate);
    form.addEventListener('change', evaluate);
    evaluate();
})();
