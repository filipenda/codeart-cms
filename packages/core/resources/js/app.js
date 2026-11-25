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

window.Alpine = Alpine;
Alpine.start();
