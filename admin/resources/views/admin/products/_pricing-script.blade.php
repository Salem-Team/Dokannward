<script>
(() => {
    const root = document.querySelector('[data-pricing-studio]');
    if (!root) return;

    const baseInput = root.querySelector('[data-pricing-base]');
    const saleInput = root.querySelector('[data-pricing-sale]');
    const preview = root.querySelector('[data-pricing-preview]');
    const eyebrow = root.querySelector('[data-pricing-eyebrow]');
    const nowEl = root.querySelector('[data-pricing-now]');
    const wasEl = root.querySelector('[data-pricing-was]');
    const badgeEl = root.querySelector('[data-pricing-badge]');
    const noteEl = root.querySelector('[data-pricing-note]');

    const money = (n) =>
        'LE ' + n.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

    const read = (input) => {
        const raw = (input?.value ?? '').trim();
        if (raw === '') return null;
        const n = Number(raw);
        return Number.isFinite(n) ? n : null;
    };

    const refresh = () => {
        const base = read(baseInput);
        const sale = read(saleInput);
        const onSale = base != null && sale != null && sale > 0 && sale < base;
        const current = onSale ? sale : (base ?? 0);
        const pct = onSale ? Math.round(((base - sale) / base) * 100) : 0;

        preview?.classList.toggle('is-on-sale', onSale);
        if (eyebrow) eyebrow.textContent = onSale ? 'Customer sees' : 'Storefront price';
        if (nowEl) nowEl.textContent = money(current);
        if (wasEl) {
            wasEl.textContent = money(base ?? 0);
            wasEl.classList.toggle('is-hidden', !onSale);
        }
        if (badgeEl) {
            badgeEl.textContent = '−' + pct + '%';
            badgeEl.classList.toggle('is-hidden', !onSale);
        }
        if (noteEl) {
            noteEl.textContent = onSale
                ? 'Saving ' + money(base - sale)
                : 'Add a sale price below the original to show a discount';
        }
    };

    baseInput?.addEventListener('input', refresh);
    saleInput?.addEventListener('input', refresh);
    refresh();
})();
</script>
