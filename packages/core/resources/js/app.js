import Alpine from 'alpinejs';

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

Alpine.data('codeartRecoveryCodes', (filename = 'recovery-codes.txt') => ({
    copied: false,

    text() {
        return this.$refs.codes.innerText.trim().split(/\s*\n\s*/).join('\n') + '\n';
    },

    async copy() {
        try {
            await navigator.clipboard.writeText(this.text());
        } catch {
            const area = document.createElement('textarea');
            area.value = this.text();
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            area.remove();
        }
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
        this.theme = ['light', 'dark', 'auto'].includes(saved) ? saved : this.$root.dataset.themeDefault || 'light';
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
        event.preventDefault();
        confirmer.form?.querySelector('dialog')?.showModal();
        return;
    }
    const closer = target.closest('[data-modal-close]');
    if (closer) {
        closer.closest('dialog')?.close();
        return;
    }
    if (target instanceof HTMLDialogElement && target.hasAttribute('data-modal')) target.close(); // backdrop
});

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
 * the selection counter, select-all for the page, "all results" (sends the filter, never ids) and a confirm before a bulk run.
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

    /** Ask before running; the server does not depend on this (it checks permissions itself). */
    confirmBulk(event) {
        const select = event.target.querySelector('select[name="action"]');
        const text = select?.selectedOptions[0]?.dataset.confirm;
        if (text && !window.confirm(text)) event.preventDefault();
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

window.Alpine = Alpine;
Alpine.start();
