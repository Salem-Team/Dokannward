/**
 * Brand route veil helpers — navigation show/hide is owned by the
 * inline script in route-veil.blade.php (must not wait for this bundle).
 * This module covers Turbo/Alpine + dark-mode, tables, toasts, etc.
 */
import './bootstrap';
import * as Turbo from '@hotwired/turbo';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { assignFiles, bindDropzone } from './dropzone';
import { initProductMedia } from './product-media';
import { initProductQr } from './product-qr';

Alpine.plugin(collapse);
window.Alpine = Alpine;
window.Turbo = Turbo;
window.DokanWardDropzone = { bindDropzone, assignFiles };

/** Admin chrome — drawer closed by default; toggle from the topbar. */
Alpine.data('adminShell', () => ({
  sidebarOpen: false,
  profileOpen: false,
  init() {
    this.$watch('sidebarOpen', (open) => {
      document.documentElement.classList.toggle('admin-drawer-active', !!open);
    });
  },
  toggleSidebar() {
    this.sidebarOpen = !this.sidebarOpen;
  },
  closeSidebar() {
    this.sidebarOpen = false;
  },
}));

/** Admin topbar quick search — products, orders, customers. */
Alpine.data('deskSearch', () => ({
  q: '',
  open: false,
  loading: false,
  groups: [
    { key: 'products', label: 'Products', items: [] },
    { key: 'orders', label: 'Orders', items: [] },
    { key: 'customers', label: 'Customers', items: [] },
  ],
  activeKey: '',
  timer: null,
  requestId: 0,

  isEmpty() {
    return this.groups.every((group) => group.items.length === 0);
  },

  onFocus() {
    this.open = true;
    if (this.q.trim().length >= 2) {
      this.fetchResults();
    }
  },

  close() {
    this.open = false;
    this.activeKey = '';
  },

  onInput() {
    this.open = true;
    clearTimeout(this.timer);
    const term = this.q.trim();
    if (term.length < 2) {
      this.loading = false;
      this.groups.forEach((group) => { group.items = []; });
      this.activeKey = '';
      return;
    }
    this.timer = setTimeout(() => this.fetchResults(), 180);
  },

  async fetchResults() {
    const term = this.q.trim();
    if (term.length < 2) return;

    const id = ++this.requestId;
    this.loading = true;

    try {
      const response = await fetch(
        `/admin/desk-search?q=${encodeURIComponent(term)}`,
        {
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
        },
      );
      const data = await response.json();
      if (id !== this.requestId) return;

      this.groups = [
        { key: 'products', label: 'Products', items: data.products || [] },
        { key: 'orders', label: 'Orders', items: data.orders || [] },
        { key: 'customers', label: 'Customers', items: data.customers || [] },
      ];
      this.activeKey = this.firstActiveKey();
    } catch {
      if (id === this.requestId) {
        this.groups.forEach((group) => { group.items = []; });
      }
    } finally {
      if (id === this.requestId) {
        this.loading = false;
      }
    }
  },

  firstActiveKey() {
    for (const group of this.groups) {
      if (group.items.length > 0) {
        return `${group.key}-0`;
      }
    }
    return '';
  },

  flatItems() {
    const out = [];
    this.groups.forEach((group) => {
      group.items.forEach((item, index) => {
        out.push({ key: `${group.key}-${index}`, url: item.url });
      });
    });
    return out;
  },

  move(delta) {
    const items = this.flatItems();
    if (!items.length) return;
    const keys = items.map((item) => item.key);
    const current = keys.indexOf(this.activeKey);
    const next = current < 0 ? 0 : (current + delta + keys.length) % keys.length;
    this.activeKey = keys[next];
    this.open = true;
  },

  goActive() {
    const items = this.flatItems();
    const match = items.find((item) => item.key === this.activeKey) || items[0];
    if (!match?.url) return;
    this.close();
    if (window.brandRouteVeil) window.brandRouteVeil.show();
    if (window.Turbo?.visit) {
      Turbo.visit(match.url);
    } else {
      window.location.href = match.url;
    }
  },
}));

/**
 * Order return / credit-note desk.
 * Registered here (not in a Blade @push) so Turbo soft-nav always finds it
 * before Alpine.initTree runs.
 */
Alpine.data('returnForm', (config = {}) => ({
  remaining: Number(config.remaining || 0),
  items: Array.isArray(config.items) ? config.items : [],
  returnAll: Boolean(config.returnAll),
  restock: config.restock !== false && config.restock !== 0 && config.restock !== '0',
  refundAmount: Number(
    config.refundAmount != null ? config.refundAmount : config.remaining || 0,
  ),
  submitting: false,

  get suggested() {
    if (this.returnAll) {
      return this.roundMoney(this.remaining);
    }
    const sum = this.items
      .filter((item) => item.selected && Number(item.available) > 0)
      .reduce((acc, item) => {
        const qty = Math.min(
          Math.max(0, Number(item.qty) || 0),
          Number(item.available) || 0,
        );
        return acc + Number(item.unit || 0) * qty;
      }, 0);
    return Math.min(this.roundMoney(sum), this.roundMoney(this.remaining));
  },

  get hasSelection() {
    if (this.returnAll) {
      return this.items.some((item) => Number(item.available) > 0);
    }
    return this.items.some(
      (item) => item.selected && Number(item.available) > 0 && Number(item.qty) > 0,
    );
  },

  roundMoney(value) {
    return Math.round((Number(value) || 0) * 100) / 100;
  },

  clampAmount() {
    let amount = this.roundMoney(this.refundAmount);
    if (!Number.isFinite(amount) || amount < 0) amount = 0;
    if (amount > this.remaining) amount = this.remaining;
    this.refundAmount = amount;
  },

  syncAmount() {
    if (this.returnAll) {
      this.refundAmount = this.roundMoney(this.remaining);
      return;
    }
    if (this.suggested > 0) {
      this.refundAmount = this.suggested;
    }
  },

  useFull() {
    this.refundAmount = this.roundMoney(this.remaining);
  },

  useSuggested() {
    // Match selected lines only — never silently jump to full remaining.
    this.refundAmount = this.suggested;
  },

  toggleItem(item, checked) {
    item.selected = Boolean(checked);
    if (item.selected && (!item.qty || item.qty < 1)) {
      item.qty = item.available > 0 ? item.available : 1;
    }
    this.syncAmount();
  },

  formatMoney(value) {
    return this.roundMoney(value).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  },

  validateBeforeSubmit() {
    this.clampAmount();

    if (!this.hasSelection) {
      window.toast?.show?.(
        'Select returned items, or turn on “Return all remaining items”.',
        'error',
        4200,
      );
      return false;
    }

    if (this.refundAmount < 0.01) {
      window.toast?.show?.(
        'Enter a refund amount greater than zero.',
        'error',
        4200,
      );
      return false;
    }

    if (this.refundAmount > this.remaining + 0.001) {
      window.toast?.show?.(
        `Refund cannot exceed the remaining balance (${this.formatMoney(this.remaining)}).`,
        'error',
        4200,
      );
      return false;
    }

    this.submitting = true;
    return true;
  },

  init() {
    this.clampAmount();
    this.$watch('returnAll', (on) => {
      if (on) {
        this.refundAmount = this.roundMoney(this.remaining);
      } else {
        this.syncAmount();
      }
    });
  },
}));

// Soft Turbo visits: re-init Alpine after morph so x-data keeps working.
let softNav = false;
document.addEventListener('turbo:visit', () => {
  softNav = true;
});
document.addEventListener('turbo:before-cache', () => {
  if (window.Alpine && typeof Alpine.destroyTree === 'function') {
    try {
      Alpine.destroyTree(document.body);
    } catch {
      // ignore
    }
  }
});
document.addEventListener('turbo:render', () => {
  if (!softNav) return;
  softNav = false;
  if (window.Alpine && typeof Alpine.initTree === 'function') {
    try {
      Alpine.initTree(document.body);
    } catch {
      // ignore
    }
  }
});

Alpine.start();

// Dark Mode Management
const darkMode = {
    init() {
        // Default is light. Only enable dark when the user explicitly chose it.
        if (localStorage.getItem('darkMode') === 'true') {
            this.enable();
        } else {
            this.disable();
        }
    },

    enable() {
        document.documentElement.classList.add('dark');
        localStorage.setItem('darkMode', 'true');
    },

    disable() {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('darkMode', 'false');
    },

    toggle() {
        if (document.documentElement.classList.contains('dark')) {
            this.disable();
        } else {
            this.enable();
        }
    }
};

const modal = {
    open(modalId) {
        window.dispatchEvent(new CustomEvent(`open-modal-${modalId}`));
    },
    close(modalId) {
        window.dispatchEvent(new CustomEvent(`close-modal-${modalId}`));
    }
};

const toast = {
    show(message, type = 'info', duration = 3000) {
        let host = document.getElementById('admin-toast-host');
        if (!host) {
            host = document.createElement('div');
            host.id = 'admin-toast-host';
            host.className = 'admin-toast-host';
            host.setAttribute('aria-live', 'polite');
            document.body.appendChild(host);
        }

        const el = document.createElement('div');
        el.className = `admin-toast admin-toast--${type || 'info'}`;
        el.innerHTML = `
            <i class="fas ${toastIcon(type)} admin-toast__icon" aria-hidden="true"></i>
            <span class="admin-toast__text"></span>
            <button type="button" class="admin-toast__close" aria-label="Dismiss">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        `;
        el.querySelector('.admin-toast__text').textContent = String(message || '');
        el.querySelector('.admin-toast__close').addEventListener('click', () => dismissToast(el));

        host.appendChild(el);
        requestAnimationFrame(() => el.classList.add('is-in'));
        window.setTimeout(() => dismissToast(el), duration);
    },
};

function toastIcon(type) {
    return (
        {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle',
        }[type] || 'fa-info-circle'
    );
}

function dismissToast(el) {
    if (!el || el.dataset.leaving === '1') return;
    el.dataset.leaving = '1';
    el.classList.remove('is-in');
    el.classList.add('is-out');
    window.setTimeout(() => el.remove(), 280);
}

/**
 * Branded confirm / alert dialogs — replaces native browser alerts.
 */
const dialog = {
    _root: null,
    _resolver: null,
    _previousFocus: null,
    _onKeydown: null,

    ensure() {
        if (this._root && document.body.contains(this._root)) return this._root;

        const root = document.createElement('div');
        root.id = 'admin-dialog-root';
        root.className = 'admin-dialog';
        root.hidden = true;
        root.innerHTML = `
            <div class="admin-dialog__veil" data-dialog-veil></div>
            <div class="admin-dialog__panel" role="alertdialog" aria-modal="true"
                aria-labelledby="admin-dialog-title" aria-describedby="admin-dialog-message" tabindex="-1">
                <div class="admin-dialog__mark" data-dialog-mark aria-hidden="true">
                    <i data-dialog-icon></i>
                </div>
                <div class="admin-dialog__copy">
                    <p class="admin-dialog__eyebrow" data-dialog-eyebrow></p>
                    <h2 class="admin-dialog__title" id="admin-dialog-title" data-dialog-title></h2>
                    <p class="admin-dialog__message" id="admin-dialog-message" data-dialog-message></p>
                    <label class="admin-dialog__choice" data-dialog-choice-wrap hidden>
                        <input type="checkbox" class="admin-dialog__choice-input" data-dialog-choice>
                        <span class="admin-dialog__choice-text" data-dialog-choice-label></span>
                    </label>
                </div>
                <div class="admin-dialog__actions">
                    <button type="button" class="admin-dialog__btn admin-dialog__btn--ghost" data-dialog-cancel>Cancel</button>
                    <button type="button" class="admin-dialog__btn admin-dialog__btn--primary" data-dialog-confirm>Confirm</button>
                </div>
            </div>
        `;

        root.querySelector('[data-dialog-veil]').addEventListener('click', () => this._settle(false));
        root.querySelector('[data-dialog-cancel]').addEventListener('click', () => this._settle(false));
        root.querySelector('[data-dialog-confirm]').addEventListener('click', () => this._settle(true));
        root.querySelector('[data-dialog-choice]').addEventListener('change', () => this._syncChoiceGate());

        document.body.appendChild(root);
        this._root = root;
        return root;
    },

    open(options = {}) {
        const {
            title = 'Please confirm',
            message = 'Are you sure?',
            confirmText = 'Confirm',
            cancelText = 'Cancel',
            tone = 'danger',
            eyebrow = 'Confirmation',
            alertOnly = false,
            choice = null,
        } = typeof options === 'string' ? { message: options } : options;

        this.ensure();
        if (this._resolver) this._settle(false);

        const root = this._root;
        const tones = {
            danger: { icon: 'fas fa-trash-alt', mark: 'is-danger' },
            warning: { icon: 'fas fa-exclamation-triangle', mark: 'is-warning' },
            info: { icon: 'fas fa-info-circle', mark: 'is-info' },
            success: { icon: 'fas fa-check-circle', mark: 'is-success' },
        };
        const skin = tones[tone] || tones.danger;

        this._choiceConfig = choice && choice.label
            ? {
                label: String(choice.label),
                name: choice.name || 'confirm_choice',
                checked: choice.checked !== false && choice.checked !== 0 && choice.checked !== '0',
                required: choice.required === true || choice.required === 1 || choice.required === '1',
            }
            : null;

        root.dataset.tone = tone;
        root.querySelector('[data-dialog-eyebrow]').textContent = eyebrow;
        root.querySelector('[data-dialog-title]').textContent = title;
        root.querySelector('[data-dialog-message]').textContent = message;
        root.querySelector('[data-dialog-icon]').className = skin.icon;
        const mark = root.querySelector('[data-dialog-mark]');
        mark.className = `admin-dialog__mark ${skin.mark}`;

        const choiceWrap = root.querySelector('[data-dialog-choice-wrap]');
        const choiceInput = root.querySelector('[data-dialog-choice]');
        const choiceLabel = root.querySelector('[data-dialog-choice-label]');
        if (this._choiceConfig) {
            choiceWrap.hidden = false;
            choiceLabel.textContent = this._choiceConfig.label;
            choiceInput.checked = !!this._choiceConfig.checked;
        } else {
            choiceWrap.hidden = true;
            choiceInput.checked = false;
            choiceLabel.textContent = '';
        }

        const cancelBtn = root.querySelector('[data-dialog-cancel]');
        const confirmBtn = root.querySelector('[data-dialog-confirm]');
        cancelBtn.textContent = cancelText;
        confirmBtn.textContent = confirmText;
        confirmBtn.className = `admin-dialog__btn admin-dialog__btn--primary admin-dialog__btn--${tone}`;
        cancelBtn.hidden = !!alertOnly;
        confirmBtn.textContent = alertOnly ? confirmText || 'OK' : confirmText;
        this._syncChoiceGate();

        this._previousFocus = document.activeElement;
        root.hidden = false;
        document.documentElement.classList.add('admin-dialog-open');
        requestAnimationFrame(() => root.classList.add('is-open'));

        this._onKeydown = (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                this._settle(alertOnly ? true : false);
            } else if (e.key === 'Enter') {
                const tag = (e.target && e.target.tagName) || '';
                if (tag === 'TEXTAREA' || tag === 'BUTTON' || tag === 'INPUT') return;
                if (confirmBtn.disabled) return;
                e.preventDefault();
                this._settle(true);
            } else if (e.key === 'Tab') {
                trapDialogFocus(root, e);
            }
        };
        document.addEventListener('keydown', this._onKeydown, true);

        window.setTimeout(() => {
            if (alertOnly) {
                confirmBtn.focus();
            } else if (this._choiceConfig) {
                choiceInput.focus();
            } else {
                cancelBtn.focus();
            }
        }, 30);

        return new Promise((resolve) => {
            this._resolver = resolve;
        });
    },

    confirm(options = {}) {
        const opts = typeof options === 'string' ? { message: options } : { ...options };
        if (!opts.title) opts.title = 'Delete this item?';
        if (!opts.confirmText) opts.confirmText = 'Delete';
        if (!opts.tone) opts.tone = 'danger';
        if (!opts.eyebrow) opts.eyebrow = 'Destructive action';
        return this.open({ ...opts, alertOnly: false });
    },

    alert(options = {}) {
        const opts = typeof options === 'string' ? { message: options } : { ...options };
        if (!opts.title) opts.title = 'Notice';
        if (!opts.confirmText) opts.confirmText = 'OK';
        if (!opts.tone) opts.tone = 'info';
        if (!opts.eyebrow) opts.eyebrow = 'Notification';
        return this.open({ ...opts, alertOnly: true, choice: null }).then(() => undefined);
    },

    _syncChoiceGate() {
        if (!this._root) return;
        const confirmBtn = this._root.querySelector('[data-dialog-confirm]');
        const choiceInput = this._root.querySelector('[data-dialog-choice]');
        if (!confirmBtn) return;
        if (this._choiceConfig?.required) {
            confirmBtn.disabled = !choiceInput?.checked;
        } else {
            confirmBtn.disabled = false;
        }
    },

    _settle(result) {
        if (!this._root) return;
        const resolve = this._resolver;
        this._resolver = null;
        const choiceConfig = this._choiceConfig;
        const choiceChecked = !!this._root.querySelector('[data-dialog-choice]')?.checked;

        if (this._onKeydown) {
            document.removeEventListener('keydown', this._onKeydown, true);
            this._onKeydown = null;
        }

        this._root.classList.remove('is-open');
        document.documentElement.classList.remove('admin-dialog-open');
        this._choiceConfig = null;

        // Cancel / Escape / veil click — no mutation happened. Drop any route
        // loader that may have been armed by a prior submit intercept.
        if (!result && window.brandRouteVeil && typeof window.brandRouteVeil.hide === 'function') {
            window.brandRouteVeil.hide();
        }

        window.setTimeout(() => {
            if (this._root) this._root.hidden = true;
            if (this._previousFocus && typeof this._previousFocus.focus === 'function') {
                try {
                    this._previousFocus.focus();
                } catch (_) {
                    /* element may be gone after Turbo */
                }
            }
            this._previousFocus = null;
        }, 220);

        if (!resolve) return;
        if (!result) {
            resolve(false);
            return;
        }
        if (choiceConfig) {
            resolve({ ok: true, choice: choiceChecked, name: choiceConfig.name });
            return;
        }
        resolve(true);
    },
};

function trapDialogFocus(root, e) {
    const focusable = root.querySelectorAll(
        'button:not([hidden]):not([disabled]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
    );
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
}

/**
 * Forms with data-confirm="..." open the branded dialog before submitting.
 */
function bindConfirmForms() {
    if (bindConfirmForms.bound) return;
    bindConfirmForms.bound = true;

    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (form.dataset.confirmBypass === '1') {
                delete form.dataset.confirmBypass;
                return;
            }

            const message = form.getAttribute('data-confirm');
            if (!message) return;

            event.preventDefault();
            event.stopImmediatePropagation();

            // No network work yet — keep the route veil off while the dialog is open.
            if (window.brandRouteVeil && typeof window.brandRouteVeil.hide === 'function') {
                window.brandRouteVeil.hide();
            }

            const title = form.getAttribute('data-confirm-title') || 'Please confirm';
            const confirmText = form.getAttribute('data-confirm-confirm') || 'Delete';
            const cancelText = form.getAttribute('data-confirm-cancel') || 'Cancel';
            const tone = form.getAttribute('data-confirm-tone') || 'danger';
            const eyebrow = form.getAttribute('data-confirm-eyebrow') || 'Destructive action';
            const choiceLabel = form.getAttribute('data-confirm-choice');
            const choice = choiceLabel
                ? {
                    label: choiceLabel,
                    name: form.getAttribute('data-confirm-choice-name') || 'confirm_choice',
                    checked: form.getAttribute('data-confirm-choice-checked') !== '0',
                    required: form.getAttribute('data-confirm-choice-required') !== '0',
                }
                : null;

            dialog
                .confirm({ title, message, confirmText, cancelText, tone, eyebrow, choice })
                .then((result) => {
                    // Cancel / dismiss — never submit, never arm the loader.
                    if (!result || (result !== true && !result.ok)) return;

                    if (result !== true && result.name) {
                        let input = form.querySelector(`input[name="${result.name}"]`);
                        if (!input) {
                            input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = result.name;
                            form.appendChild(input);
                        }
                        input.value = result.choice ? '1' : '0';
                    }

                    form.dataset.confirmBypass = '1';
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                });
        },
        true,
    );
}
bindConfirmForms.bound = false;

const form = {
    validate(formElement) {
        const inputs = formElement.querySelectorAll('[required]');
        let isValid = true;
        inputs.forEach(input => {
            if (!input.value.trim()) {
                isValid = false;
                this.showError(input, 'This field is required');
            } else {
                this.clearError(input);
            }
        });
        return isValid;
    },
    showError(input, message) {
        const existingError = input.parentElement.querySelector('.error-message');
        if (existingError) {
            existingError.textContent = message;
        } else {
            const error = document.createElement('p');
            error.className = 'error-message mt-2 text-sm text-red-600 dark:text-red-400 flex items-center';
            error.innerHTML = `<i class="fas fa-exclamation-circle mr-1"></i>${message}`;
            input.parentElement.appendChild(error);
        }
        input.classList.add('border-red-500');
    },
    clearError(input) {
        const error = input.parentElement.querySelector('.error-message');
        if (error) error.remove();
        input.classList.remove('border-red-500');
    },
    async confirmSubmit(formElement, message = 'Are you sure?') {
        const ok = await dialog.confirm({
            title: 'Please confirm',
            message,
            confirmText: 'Continue',
            tone: 'warning',
            eyebrow: 'Confirmation',
        });
        if (ok && formElement) {
            formElement.dataset.confirmBypass = '1';
            if (typeof formElement.requestSubmit === 'function') {
                formElement.requestSubmit();
            } else {
                formElement.submit();
            }
        }
        return ok;
    },
};

const dataTable = {
    sort(table, columnIndex, direction = 'asc') {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.sort((a, b) => {
            const aValue = a.cells[columnIndex].textContent.trim();
            const bValue = b.cells[columnIndex].textContent.trim();
            return direction === 'asc' ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
        });
        rows.forEach(row => tbody.appendChild(row));
    },
    filter(table, searchTerm) {
        const tbody = table.querySelector('tbody');
        tbody.querySelectorAll('tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(searchTerm.toLowerCase()) ? '' : 'none';
        });
    }
};

const imagePreview = {
    show(input, previewContainer) {
        const files = input.files;
        previewContainer.innerHTML = '';
        Array.from(files).forEach((file, index) => {
            if (!file.type.startsWith('image/')) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                const div = document.createElement('div');
                div.className = 'relative group';
                div.innerHTML = `
                    <img src="${e.target.result}" class="w-full h-32 object-cover rounded-lg shadow-md">
                    ${index === 0 ? '<span class="absolute top-2 left-2 bg-blue-500 text-white text-xs px-2 py-1 rounded">Main</span>' : ''}
                `;
                previewContainer.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    }
};

function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return dialog.confirm({
        title: 'Delete this item?',
        message,
        confirmText: 'Delete',
        tone: 'danger',
        eyebrow: 'Destructive action',
    });
}

function initAutoDismiss() {
    document.querySelectorAll('[data-auto-dismiss]').forEach(alert => {
        const duration = parseInt(alert.dataset.autoDismiss) || 5000;
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, duration);
    });
}

/**
 * Sidebar badge zero-out — runs while the drawer is still open so the
 * odometer drain is actually visible, then navigates / collapses the panel.
 */
const navBadges = {
    running: new WeakSet(),
    observers: new WeakMap(),
    bound: false,

    skipKey(section) {
        return `dokannward.navBadge.skip.${section}`;
    },

    setDrawerOpen(open) {
        const root = document.querySelector('.admin-shell');
        if (!root || !window.Alpine || typeof Alpine.$data !== 'function') return;
        try {
            const data = Alpine.$data(root);
            if (data && typeof data.sidebarOpen === 'boolean') {
                data.sidebarOpen = !!open;
            }
        } catch (_) { /* drawer root not ready */ }
    },

    visit(href) {
        if (window.Turbo && typeof Turbo.visit === 'function') {
            Turbo.visit(href);
        } else {
            window.location.href = href;
        }
    },

    init() {
        this.bindNavClicks();

        document.querySelectorAll('[data-nav-badge][data-clearing="1"]').forEach((badge) => {
            const section = badge.dataset.navBadge || 'nav';
            if (sessionStorage.getItem(this.skipKey(section))) {
                sessionStorage.removeItem(this.skipKey(section));
                badge.remove();
                return;
            }
            this.clearWhenVisible(badge);
        });
    },

    bindNavClicks() {
        if (this.bound) return;
        this.bound = true;

        document.addEventListener('click', (event) => {
            const link = event.target.closest('[data-admin-nav] a.admin-nav-link');
            if (!link) return;

            const badge = link.querySelector('[data-nav-badge]');
            const href = link.getAttribute('href');
            if (!href) return;

            // No badge: close drawer and let Turbo/browser navigate normally.
            if (!badge) {
                this.setDrawerOpen(false);
                return;
            }

            // Already draining — block double-clicks.
            if (badge.classList.contains('is-zeroing')) {
                event.preventDefault();
                event.stopPropagation();
                return;
            }

            // Hold the drawer open, zero the badge, then navigate.
            event.preventDefault();
            event.stopPropagation();

            const section = badge.dataset.navBadge || 'nav';
            sessionStorage.setItem(this.skipKey(section), '1');

            this.clear(badge, {
                onComplete: () => {
                    this.setDrawerOpen(false);
                    window.setTimeout(() => this.visit(href), 120);
                },
            });
        }, true);
    },

    clearWhenVisible(badge) {
        if (!badge || this.running.has(badge)) return;

        const tryClear = () => {
            if (!badge.isConnected || this.running.has(badge)) return true;
            const visible = badge.getClientRects().length > 0
                && window.getComputedStyle(badge).visibility !== 'hidden';
            if (visible) {
                this.clear(badge);
                return true;
            }
            return false;
        };

        if (tryClear()) return;

        if (typeof IntersectionObserver === 'function') {
            const io = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting && entry.intersectionRatio > 0)) {
                    io.disconnect();
                    this.observers.delete(badge);
                    tryClear();
                }
            }, { threshold: 0.35 });
            io.observe(badge);
            this.observers.set(badge, io);
        }

        let frames = 0;
        const poll = () => {
            if (tryClear() || frames++ > 180) return;
            requestAnimationFrame(poll);
        };
        requestAnimationFrame(poll);
    },

    clear(badge, { onComplete } = {}) {
        if (!badge || this.running.has(badge)) return;
        this.running.add(badge);

        const existingIo = this.observers.get(badge);
        if (existingIo) {
            existingIo.disconnect();
            this.observers.delete(badge);
        }

        const start = Math.max(0, parseInt(badge.dataset.count || badge.textContent, 10) || 0);
        const finish = () => {
            badge.remove();
            if (typeof onComplete === 'function') onComplete();
        };

        if (start < 1) {
            finish();
            return;
        }

        const link = badge.closest('.admin-nav-link');
        badge.classList.add('is-zeroing');
        badge.setAttribute('aria-live', 'polite');
        if (link) link.classList.add('is-badge-clearing');

        let digit = badge.querySelector('.admin-nav-badge__digit');
        if (!digit) {
            digit = document.createElement('span');
            digit.className = 'admin-nav-badge__digit';
            digit.textContent = String(start);
            badge.textContent = '';
            badge.appendChild(digit);
        }

        const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReduced) {
            digit.textContent = '0';
            badge.classList.add('is-collapsing');
            window.setTimeout(() => {
                if (link) link.classList.remove('is-badge-clearing');
                finish();
            }, 180);
            return;
        }

        badge.classList.add('is-acknowledged');
        const settleMs = 320;
        const stride = start > 24 ? Math.ceil(start / 8) : start > 12 ? 2 : 1;
        const stepMs = start > 8 ? 62 : 96;
        let current = start;

        const collapse = () => {
            digit.textContent = '0';
            badge.classList.remove('is-ticking', 'is-acknowledged');
            badge.classList.add('is-collapsing');
            window.setTimeout(() => {
                if (link) link.classList.remove('is-badge-clearing');
                finish();
            }, 580);
        };

        const tick = () => {
            if (current <= 0) {
                collapse();
                return;
            }

            badge.classList.remove('is-ticking');
            void badge.offsetWidth;
            badge.classList.add('is-ticking');

            window.setTimeout(() => {
                current = Math.max(0, current - stride);
                digit.textContent = String(current);
                window.setTimeout(tick, stepMs);
            }, 78);
        };

        window.setTimeout(tick, settleMs);
    },
};

function bootAdminChrome() {
    darkMode.init();
    initAutoDismiss();
    navBadges.init();
    bindConfirmForms();
    dialog.ensure();
    initProductMedia();
    initProductQr();

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('.admin-topbar__search input[type="search"]');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootAdminChrome);
} else {
    bootAdminChrome();
}

document.addEventListener('turbo:load', () => {
    initAutoDismiss();
    navBadges.init();
    initProductQr();
});
document.addEventListener('turbo:render', () => {
    navBadges.init();
    initProductQr();
});

window.darkMode = darkMode;
window.modal = modal;
window.toast = toast;
window.dialog = dialog;
window.form = form;
window.dataTable = dataTable;
window.imagePreview = imagePreview;
window.confirmDelete = confirmDelete;
window.navBadges = navBadges;
window.DokanWardDropzone = { bindDropzone, assignFiles };
