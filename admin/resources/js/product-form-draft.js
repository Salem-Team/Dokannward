/**
 * Keeps unsaved product form text/select state in sessionStorage so a mobile
 * browser reload (common after opening the camera roll) does not wipe edits.
 * File previews stay in product-media.js memory — this module only guards fields.
 */

const DRAFT_PREFIX = 'dokannward:product-form:';
const MAX_AGE_MS = 1000 * 60 * 60 * 12; // 12 h

/** @param {HTMLFormElement} form */
function draftKey(form) {
    return `${DRAFT_PREFIX}${form.action}`;
}

/** @param {HTMLFormElement} form */
function readDraft(form) {
    try {
        const raw = sessionStorage.getItem(draftKey(form));
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        if (!parsed?.fields || typeof parsed.fields !== 'object') return null;
        if (Date.now() - (parsed.savedAt || 0) > MAX_AGE_MS) {
            sessionStorage.removeItem(draftKey(form));
            return null;
        }
        return parsed.fields;
    } catch {
        return null;
    }
}

/** @param {HTMLFormElement} form */
function writeDraft(form) {
    /** @type {Record<string, string | boolean | string[]>} */
    const fields = {};

    [...form.elements].forEach((el) => {
        if (!(el instanceof HTMLElement)) return;
        if (!('name' in el) || !el.name || el.disabled) return;
        if (el instanceof HTMLInputElement && el.type === 'file') return;
        if (el instanceof HTMLInputElement && el.type === 'checkbox') {
            if (el.name.endsWith('[]')) {
                const list = /** @type {string[]} */ (fields[el.name] || []);
                if (el.checked) list.push(el.value);
                fields[el.name] = list;
            } else {
                fields[el.name] = el.checked;
            }
            return;
        }
        if (el instanceof HTMLInputElement && el.type === 'radio') {
            if (el.checked) fields[el.name] = el.value;
            return;
        }
        if (el instanceof HTMLSelectElement && el.multiple) {
            fields[el.name] = [...el.selectedOptions].map((opt) => opt.value);
            return;
        }
        if ('value' in el) {
            fields[el.name] = String(el.value);
        }
    });

    try {
        sessionStorage.setItem(
            draftKey(form),
            JSON.stringify({ savedAt: Date.now(), fields }),
        );
    } catch {
        /* quota — ignore */
    }
}

/** @param {HTMLFormElement} form */
function clearDraft(form) {
    try {
        sessionStorage.removeItem(draftKey(form));
    } catch {
        /* ignore */
    }
}

/** @param {HTMLFormElement} form */
function restoreDraft(form) {
    const fields = readDraft(form);
    if (!fields) return;

    Object.entries(fields).forEach(([name, value]) => {
        const nodes = form.elements.namedItem(name);
        const list = nodes instanceof RadioNodeList ? [...nodes] : nodes ? [nodes] : [];

        list.forEach((node) => {
            if (!(node instanceof HTMLElement) || !('name' in node)) return;
            if (node instanceof HTMLInputElement && node.type === 'file') return;

            if (Array.isArray(value)) {
                if (node instanceof HTMLInputElement && node.type === 'checkbox') {
                    node.checked = value.includes(node.value);
                } else if (node instanceof HTMLOptionElement) {
                    node.selected = value.includes(node.value);
                }
                return;
            }

            if (node instanceof HTMLInputElement && node.type === 'checkbox') {
                node.checked = Boolean(value);
                return;
            }
            if (node instanceof HTMLInputElement && node.type === 'radio') {
                node.checked = node.value === value;
                return;
            }
            if ('value' in node) {
                node.value = String(value);
            }
        });
    });

    form.dispatchEvent(new CustomEvent('dokannward:draft-restored', { bubbles: true }));
}

/** @param {HTMLFormElement} form */
function shouldRestoreDraft(form, fields) {
    const nameInput = form.querySelector('[name="name"]');
    if (nameInput instanceof HTMLInputElement) {
        const current = String(nameInput.value || '').trim();
        const drafted = String(fields.name || '').trim();
        if (current !== '' && drafted !== '' && current !== drafted) {
            return false;
        }
    }
    return true;
}

/** @param {HTMLFormElement} form */
function bindDraft(form) {
    if (form.dataset.draftBound === '1') return;
    form.dataset.draftBound = '1';

    const existing = readDraft(form);
    if (existing && shouldRestoreDraft(form, existing)) {
        restoreDraft(form);
    }

    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;
        const fields = readDraft(form);
        if (fields && shouldRestoreDraft(form, fields)) {
            restoreDraft(form);
        }
    });

    let timer = null;
    const schedule = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => writeDraft(form), 350);
    };

    form.addEventListener('input', schedule);
    form.addEventListener('change', (event) => {
        const target = event.target;
        if (target instanceof HTMLInputElement && target.type === 'file') return;
        schedule();
    });

    form.addEventListener('submit', () => clearDraft(form));
}

export function initProductFormDraft() {
    document.querySelectorAll('[data-product-save-form]').forEach((form) => {
        if (form instanceof HTMLFormElement) bindDraft(form);
    });
}

export function clearProductFormDraft(form) {
    if (form instanceof HTMLFormElement) clearDraft(form);
}
