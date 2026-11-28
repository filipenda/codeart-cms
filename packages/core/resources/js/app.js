import Alpine from 'alpinejs';
import registerEditor from './editor.js';
import registerMedia from './media.js';

/*
 * Progressive enhancement only: every screen works without this file (plain forms posting to Fortify).
 * Components: codeartOtp (six boxes, one submitted value), codeartRecoveryCodes (copy / download).
 * No colors and no styling here; classes live in the Blade views.
 */

const digitsOnly = (text) => String(text ?? '').replace(/\D/g, '');

Alpine.data('codeartOtp', (length = 6) => ({
    length,
    enabled: false,
    digits: [],

    init() {
        const main = this.$refs.main;
        this.digits = Array.from({ length }, () => '');
        [...digitsOnly(main.value).slice(0, length)].forEach((c, i) => (this.digits[i] = c));
        this.enabled = true;
        main.addEventListener('focus', () => this.enabled && this.focusBox(this.firstEmpty()));
    },

    boxes() {
        return [...this.$root.querySelectorAll('[data-otp-box]')];
    },

    focusBox(i) {
        const box = this.boxes()[Math.max(0, Math.min(i, this.length - 1))];
        box?.focus();
        box?.select();
    },

    firstEmpty() {
        const i = this.digits.findIndex((d) => d === '');
        return i === -1 ? this.length - 1 : i;
    },

    sync() {
        this.$refs.main.value = this.digits.join('');
    },

    fill(from, text) {
        const chars = [...digitsOnly(text)].slice(0, this.length - from);
        chars.forEach((c, k) => (this.digits[from + k] = c));
        this.sync();
        return from + chars.length;
    },

    onInput(i, event) {
        const value = digitsOnly(event.target.value);
        if (value === '') {
            this.digits[i] = '';
            event.target.value = '';
            this.sync();
            return;
        }
        const next = this.fill(i, value);
        event.target.value = this.digits[i];
        this.focusBox(next);
    },

    onKeydown(i, event) {
        if (event.key === 'Backspace' && this.digits[i] === '' && i > 0) {
            event.preventDefault();
            this.digits[i - 1] = '';
            this.sync();
            this.focusBox(i - 1);
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            this.focusBox(i - 1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            this.focusBox(i + 1);
        }
    },

    onPaste(i, event) {
        event.preventDefault();
        const next = this.fill(0, event.clipboardData?.getData('text'));
        this.boxes().forEach((box, k) => (box.value = this.digits[k]));
        this.focusBox(next);
    },
}));

/** Copy text to the clipboard; falls back to a hidden textarea + execCommand where the Clipboard API is missing. Resolves true on success. */
async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch {
        const area = Object.assign(document.createElement('textarea'), { value: text });
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        let ok = false;
        try {
            ok = document.execCommand('copy');
        } catch {
            ok = false;
        }
        area.remove();
        return ok;
    }
}

Alpine.data('codeartCopy', (value = '') => ({
    copied: false,
    failed: false,

    async copy() {
        this.failed = !(await copyText(value));
        this.copied = !this.failed;
        setTimeout(() => ((this.copied = false), (this.failed = false)), 2500);
    },
}));

/*
 * Password strength: the server passes the configured rules ([{key, value}]); the score is simple on purpose
 * (rules met + length headroom). The server stays the only judge; this is feedback while typing.
 */
Alpine.data('codeartPasswordStrength', (rules = [], inputId = '') => ({
    rules,
    value: '',
    met: {},
    score: 0,

    init() {
        const input = document.getElementById(inputId);
        if (!input) return;
        this.value = input.value;
        input.addEventListener('input', () => ((this.value = input.value), this.evaluate()));
        this.evaluate();
    },

    check(rule, v) {
        switch (rule.key) {
            case 'min_length': return [...v].length >= Number(rule.value);
            case 'mixed_case': return /\p{Ll}/u.test(v) && /\p{Lu}/u.test(v);
            case 'number': return /\p{N}/u.test(v);
            case 'symbol': return /[^\p{L}\p{N}\s]/u.test(v);
            default: return true;
        }
    },

    evaluate() {
        const v = this.value;
        this.met = Object.fromEntries(this.rules.map((r) => [r.key, v !== '' && this.check(r, v)]));
        const all = v !== '' && this.rules.every((r) => this.met[r.key]);
        const min = Number((this.rules.find((r) => r.key === 'min_length') || { value: 8 }).value);
        const len = [...v].length;
        const kinds = [/\p{Ll}/u, /\p{Lu}/u, /\p{N}/u, /[^\p{L}\p{N}\s]/u].filter((re) => re.test(v)).length;
        let score = v === '' ? 0 : 1;
        if (all) score = 3;
        else if (v !== '' && this.rules.some((r) => this.met[r.key])) score = 2;
        if (all && len >= min + 4) score += 1;
        if (all && len >= min + 8 && kinds >= 3) score += 1;
        this.score = Math.min(score, 5);
    },

    level() {
        return this.score <= 2 ? 'weak' : this.score === 3 ? 'medium' : 'strong';
    },
}));

Alpine.data('codeartRecoveryCodes', (filename = 'recovery-codes.txt') => ({
    copied: false,

    text() {
        return this.$refs.codes.innerText.trim().split(/\s*\n\s*/).join('\n') + '\n';
    },

    async copy() {
        if (!(await copyText(this.text()))) return;
        this.copied = true;
        setTimeout(() => (this.copied = false), 2500);
    },

    download() {
        const url = URL.createObjectURL(new Blob([this.text()], { type: 'text/plain' }));
        const link = Object.assign(document.createElement('a'), { href: url, download: filename });
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    },
}));

/*
 * Admin shell: sidebar (collapse on desktop, drawer on mobile), theme choice, command palette.
 * Every localStorage access is in try/catch (private mode, blocked storage). Without this file the shell is still
 * usable: the buttons that need it are hidden, links and forms work.
 */
const store = {
    get(key) {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch {
            /* ignore */
        }
    },
};

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select, textarea, summary, [tabindex]:not([tabindex="-1"])';

/** Keep Tab inside `container` (modal drawer / palette). */
function trapTab(event, container) {
    if (event.key !== 'Tab' || !container) return;
    const items = [...container.querySelectorAll(FOCUSABLE)].filter((el) => el.offsetParent !== null || el === document.activeElement);
    if (items.length === 0) {
        event.preventDefault();
        return;
    }
    const first = items[0];
    const last = items[items.length - 1];
    if (event.shiftKey && (document.activeElement === first || !container.contains(document.activeElement))) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && (document.activeElement === last || !container.contains(document.activeElement))) {
        event.preventDefault();
        first.focus();
    }
}

Alpine.data('codeartShell', () => ({
    drawer: false,
    paletteOpen: false,
    isMobile: false,
    collapsed: false,
    theme: 'light',
    opener: null,

    init() {
        const mq = window.matchMedia('(min-width: 1024px)');
        this.isMobile = !mq.matches;
        mq.addEventListener('change', (e) => {
            this.isMobile = !e.matches;
            if (!this.isMobile) this.drawer = false;
        });
        this.collapsed = document.documentElement.dataset.sidebar === 'collapsed';
        const saved = store.get('codeart.theme');
        const chosen = this.$root.dataset.themeUser || saved; // the saved profile choice wins over this browser's memory
        this.theme = ['light', 'dark', 'auto'].includes(chosen) ? chosen : this.$root.dataset.themeDefault || 'light';
    },

    get collapsedDesktop() {
        return !this.isMobile && this.collapsed;
    },

    toggleSidebar() {
        if (this.isMobile) {
            this.drawer ? this.closeDrawer() : this.openDrawer();
            return;
        }
        this.setCollapsed(!this.collapsed);
    },

    expandSidebar() {
        this.setCollapsed(false);
    },

    setCollapsed(value) {
        this.collapsed = value;
        document.documentElement.dataset.sidebar = value ? 'collapsed' : 'expanded';
        store.set('codeart.sidebar', value ? 'collapsed' : 'expanded');
    },

    openDrawer() {
        this.opener = document.activeElement;
        this.drawer = true;
        this.$nextTick(() => this.$refs.sidebar.querySelector('nav a, nav button')?.focus());
    },

    closeDrawer() {
        if (!this.drawer) return;
        this.drawer = false;
        this.opener?.focus?.();
        this.opener = null;
    },

    setTheme(mode) {
        this.theme = mode;
        document.documentElement.setAttribute('data-theme', mode);
        store.set('codeart.theme', mode);
        // Signed-in staff: remember the choice on the account too (best effort; the browser keeps working without it).
        const url = this.$root.dataset.themeUrl;
        if (url) {
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ theme: mode }),
                credentials: 'same-origin',
            }).catch(() => {});
        }
    },

    openPalette() {
        this.opener = document.activeElement;
        this.paletteOpen = true;
    },

    closePalette() {
        if (!this.paletteOpen) return;
        this.paletteOpen = false;
        this.opener?.focus?.();
        this.opener = null;
    },

    onKeydown(event) {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            this.paletteOpen ? this.closePalette() : this.openPalette();
            return;
        }
        if (event.key === 'Escape') {
            if (this.paletteOpen) this.closePalette();
            else if (this.drawer) this.closeDrawer();
            return;
        }
        if (this.drawer && this.isMobile) trapTab(event, this.$refs.sidebar);
    },
}));

/** Dropdown (<x-codeart::dropdown>, also the user menu): aria-expanded button, arrows/Home/End move between items, Esc closes and refocuses, tabbing out closes. */
Alpine.data('codeartDropdown', () => ({
    open: false,

    items() {
        return [...this.$refs.panel.querySelectorAll('[data-menu-item]')].filter((el) => el.offsetParent !== null);
    },

    toggle() {
        this.open ? this.close(true) : this.show();
    },

    show() {
        this.open = true;
        this.$nextTick(() => this.items()[0]?.focus());
    },

    close(refocus = true) {
        if (!this.open) return;
        this.open = false;
        if (refocus) this.$refs.button.focus();
    },

    onFocusOut(event) {
        if (this.open && event.relatedTarget && !this.$root.contains(event.relatedTarget)) this.close(false);
    },

    onKey(event) {
        if (event.key === 'Escape' && this.open) {
            event.preventDefault();
            event.stopPropagation();
            this.close(true);
            return;
        }
        if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
        if (!this.open) {
            if (event.target === this.$refs.button && event.key !== 'ArrowUp' && event.key !== 'Home') {
                event.preventDefault();
                this.show();
            }
            return;
        }
        event.preventDefault();
        const items = this.items();
        if (items.length === 0) return;
        const at = items.indexOf(document.activeElement);
        let next;
        if (event.key === 'Home') next = 0;
        else if (event.key === 'End') next = items.length - 1;
        else if (event.key === 'ArrowDown') next = at < 0 ? 0 : (at + 1) % items.length;
        else next = at <= 0 ? items.length - 1 : at - 1;
        items[next].focus();
    },
}));

Alpine.data('codeartPalette', () => ({
    all: [],
    query: '',
    index: 0,

    init() {
        try {
            this.all = JSON.parse(document.getElementById('codeart-palette-items')?.textContent || '[]');
        } catch {
            this.all = [];
        }
        this.$watch('query', () => (this.index = 0));
    },

    get results() {
        const words = this.query.toLowerCase().split(/\s+/).filter(Boolean);
        if (words.length === 0) return this.all;
        return this.all.filter((item) => {
            const hay = `${item.label} ${item.group ?? ''}`.toLowerCase();
            return words.every((w) => hay.includes(w));
        });
    },

    onOpen() {
        this.query = '';
        this.index = 0;
        this.$nextTick(() => this.$refs.paletteInput?.focus());
    },

    onPaletteKey(event) {
        const count = this.results.length;
        if (event.key === 'Tab') {
            event.preventDefault(); // the input is the only stop; options are reached with the arrows
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (count) this.index = (this.index + 1) % count;
            this.scrollToActive();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (count) this.index = (this.index - 1 + count) % count;
            this.scrollToActive();
        } else if (event.key === 'Home' && count) {
            event.preventDefault();
            this.index = 0;
            this.scrollToActive();
        } else if (event.key === 'End' && count) {
            event.preventDefault();
            this.index = count - 1;
            this.scrollToActive();
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const item = this.results[this.index];
            if (item) window.location.assign(item.url);
        }
    },

    scrollToActive() {
        this.$nextTick(() => document.getElementById('palette-option-' + this.index)?.scrollIntoView({ block: 'nearest' }));
    },
}));

/**
 * Modals are native <dialog> elements: showModal() gives the focus trap, Esc, inert page and focus restore.
 * Triggers: [data-modal-open="id"], closers: [data-modal-close]; a click on the backdrop closes too.
 * Confirm forms: the submit button marked [data-confirm-trigger] opens the dialog inside its form instead of submitting.
 */
document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;
    const opener = target.closest('[data-modal-open]');
    if (opener) {
        event.preventDefault();
        document.getElementById(opener.getAttribute('data-modal-open'))?.showModal();
        return;
    }
    const confirmer = target.closest('[data-confirm-trigger]');
    if (confirmer) {
        // The server confirms: ask the confirmation step for a token (JSON), put it in the dialog's form and open the dialog.
        // Without JS this click is a plain submit of the step form, which answers with a confirmation page.
        event.preventDefault();
        openConfirm(confirmer);
        return;
    }
    const closer = target.closest('[data-modal-close]');
    if (closer) {
        closer.closest('dialog')?.close();
        return;
    }
    if (target instanceof HTMLDialogElement && target.hasAttribute('data-modal')) target.close(); // backdrop
});

async function openConfirm(trigger) {
    const step = trigger.closest('[data-confirm-step]');
    const form = trigger.closest('[data-confirm-step]')?.parentElement?.querySelector('[data-confirm-form]');
    if (!step || !form) return;
    trigger.disabled = true;
    try {
        const response = await fetch(step.action, {
            method: 'POST',
            body: new FormData(step),
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(String(response.status));
        form.querySelector('[data-confirm-token]').value = (await response.json()).token;
        form.querySelector('dialog')?.showModal();
    } catch {
        step.submit(); // fall back to the confirmation page
    } finally {
        trigger.disabled = false;
    }
}

/** Toasts: items come from the server flash (JSON) and from the window event `codeart:toast` ({message, type, timeout}). */
Alpine.data('codeartToasts', (initial = []) => ({
    items: [],
    seq: 0,

    init() {
        initial.forEach((toast) => this.add(toast));
    },

    add({ message, type = 'info', timeout }) {
        if (!message) return;
        const id = ++this.seq;
        this.items.push({ id, message, type });
        const ms = timeout ?? (type === 'danger' ? 8000 : 5000);
        if (ms > 0) setTimeout(() => this.remove(id), ms);
    },

    remove(id) {
        this.items = this.items.filter((item) => item.id !== id);
    },
}));

/**
 * Data table enhancements (the table works without them): debounced search and auto-submit of the toolbar form,
 * the selection counter, select-all for the page, "all results" (sends the filter, never ids) and the confirm dialog before a bulk run.
 */
Alpine.data('codeartTable', ({ total = 0, rows = 0 } = {}) => ({
    total,
    rows,
    count: 0,
    pageAll: false,
    allMatching: false,
    timer: null,

    init() {
        const form = this.$refs.form;
        if (form) {
            form.querySelectorAll('[data-filter-auto]').forEach((el) => el.addEventListener('change', () => this.submit()));
            form.querySelectorAll('[data-filter-search]').forEach((el) => el.addEventListener('input', () => this.later()));
        }
        this.sync();
        window.addEventListener('pageshow', () => this.sync());
    },

    boxes() {
        return Array.from(this.$root.querySelectorAll('[data-row-select]'));
    },

    sync() {
        const boxes = this.boxes();
        const checked = boxes.filter((box) => box.checked).length;
        this.pageAll = boxes.length > 0 && checked === boxes.length;
        const all = this.$root.querySelector('[data-select-all]');
        if (all) {
            all.checked = this.pageAll;
            all.indeterminate = checked > 0 && !this.pageAll;
        }
        if (!this.pageAll) this.allMatching = false;
        this.count = this.allMatching ? this.total : checked;
    },

    toggleAll(event) {
        this.boxes().forEach((box) => (box.checked = event.target.checked));
        this.sync();
    },

    selectedLabel() {
        const template = this.$root.querySelector('[data-selected-label]')?.dataset.selectedLabel ?? ':count';
        return template.replace(':count', new Intl.NumberFormat(document.documentElement.lang || undefined).format(this.allMatching ? this.total : this.count));
    },

    /**
     * Before a bulk run: a destructive action asks the server for a token (JSON answer with the texts and the row count) and
     * the dialog shows them; any other action with a confirm text only opens the dialog. The dialog's Confirm button submits
     * the form again with the token. Nothing here replaces the server: a destructive request without a valid token does nothing.
     */
    async confirmBulk(event) {
        const form = event.target;
        if (form.dataset.confirmed === '1') {
            delete form.dataset.confirmed;
            return;
        }
        const option = form.querySelector('select[name="action"]')?.selectedOptions[0];
        const destructive = option?.dataset.destructive === '1';
        const text = option?.dataset.confirm;
        if (!destructive && !text) return;

        event.preventDefault();
        const dialog = document.getElementById(form.id.replace(/-bulk$/, '-confirm'));
        const set = (selector, value) => {
            const node = dialog.querySelector(selector);
            node.textContent = value ?? '';
            node.closest('p').hidden = value == null;
        };
        const token = form.querySelector('[data-bulk-token]');
        token.disabled = true;
        token.value = '';
        let info = { text, count: null, filter: null };

        if (destructive) {
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!response.ok) throw new Error(String(response.status));
                info = await response.json();
                token.value = info.token;
                token.disabled = false;
            } catch {
                form.submit(); // the server answers with the confirmation page
                return;
            }
        }

        dialog.querySelector('[data-bulk-text]').textContent = info.text ?? text;
        set('[data-bulk-count]', info.count != null ? new Intl.NumberFormat(document.documentElement.lang || undefined).format(info.count) : null);
        set('[data-bulk-filter]', info.filter ?? null);
        dialog.querySelector('[data-bulk-confirm]').onclick = () => {
            dialog.close();
            form.dataset.confirmed = '1';
            form.requestSubmit();
        };
        dialog.showModal();
    },

    later() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.submit(), 400);
    },

    submit() {
        clearTimeout(this.timer);
        this.$refs.form?.requestSubmit();
    },
}));

registerEditor(Alpine);
registerMedia(Alpine);

window.Alpine = Alpine;
Alpine.start();
