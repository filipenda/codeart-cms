import { Editor, Extension } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { Table, TableRow, TableHeader, TableCell } from '@tiptap/extension-table';
import contentCss from '../css/editor-content.css';

/*
 * Rich-text editor (TipTap, MIT) as an Alpine component, see docs/editor.md. Progressive enhancement: without JS the
 * field stays a plain textarea. The textarea is always what is submitted; the editor writes its HTML into it. The browser's
 * HTML is NEVER trusted: the server sanitizes on save (HtmlSanitizer). The content area lives in a shadow root so the
 * admin's CSS cannot reach the content and the site's CSS (codeart.editor_css) cannot reach the admin.
 */

const SAFE_SCHEMES = ['http:', 'https:', 'mailto:', 'tel:'];

/** http, https, mailto, tel or a relative address; everything else (javascript:, data: ...) is refused. */
export function safeUrl(value) {
    const url = String(value ?? '').trim();
    if (url === '' || /[\u0000-\u001f\u007f-\u009f]/.test(url)) return null;
    const probe = url.replace(/[\s\u00a0\u1680\u180e\u2000-\u200f\u2028-\u202f\u205f-\u206f\u3000\ufeff]+/g, '').toLowerCase();
    const scheme = /^([^:/?#]*):/.exec(probe);
    if (scheme) return SAFE_SCHEMES.includes(scheme[1] + ':') ? url : null;
    return probe.includes('\\') ? null : url;
}

const attr = (name, html = name) => ({
    default: null,
    parseHTML: (el) => el.getAttribute(html),
    renderHTML: (attrs) => (attrs[name] == null ? {} : { [html]: attrs[name] }),
});

/** An image that remembers its media library id (the server re-reads src/srcset/size from it). */
const MediaImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            mediaId: attr('mediaId', 'data-media-id'),
            srcset: attr('srcset'),
            sizes: attr('sizes'),
            width: attr('width'),
            height: attr('height'),
            loading: attr('loading'),
        };
    },
});

const LinkKey = Extension.create({
    name: 'linkShortcut',
    addKeyboardShortcuts() {
        return { 'Mod-k': () => (this.options.open(), true) };
    },
    addOptions() {
        return { open: () => {} };
    },
});

let counter = 0;

export default function registerEditor(Alpine) {
    Alpine.data('codeartEditor', (config = {}) => ({
        editor: null,
        ready: false,
        source: false,
        active: {},
        inTable: false,
        token: `editor-${++counter}`,
        link: { url: '', blank: false, nofollow: false, error: '' },
        labels: config.labels ?? {},
        shadow: null,

        init() {
            const input = this.$refs.input;
            const host = this.$refs.host;
            if (!input || !host) return;

            input.removeAttribute('required'); // a hidden required field would block the submit; the server validates
            this.shadow = host.attachShadow({ mode: 'open' });
            const style = document.createElement('style');
            style.textContent = contentCss;
            this.shadow.append(style);
            for (const href of config.css ?? []) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = href;
                this.shadow.append(link);
            }
            const mount = document.createElement('div');
            mount.className = 'codeart-content';
            this.shadow.append(mount);

            const self = this;
            this.editor = new Editor({
                element: mount,
                content: input.value,
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [2, 3, 4] },
                        link: { openOnClick: false, autolink: false, linkOnPaste: true, HTMLAttributes: { target: null, rel: null }, isAllowedUri: (url) => safeUrl(url) !== null },
                    }),
                    MediaImage.configure({ inline: false, allowBase64: false }),
                    Table.configure({ resizable: false }),
                    TableRow,
                    TableHeader,
                    TableCell,
                    LinkKey.configure({ open: () => self.openLink() }),
                ],
                editorProps: {
                    attributes: {
                        role: 'textbox',
                        'aria-multiline': 'true',
                        'aria-label': config.label ?? '',
                        'data-placeholder': '',
                    },
                },
                onUpdate: () => this.write(),
                onTransaction: () => this.refresh(),
                onSelectionUpdate: () => this.refresh(),
            });

            input.hidden = true;
            this.ready = true;
            this.refresh();
            window.addEventListener('codeart:media-selected', (event) => this.insertMedia(event.detail));
        },

        destroy() {
            this.editor?.destroy();
        },

        write() {
            const input = this.$refs.input;
            input.value = this.editor.isEmpty ? '' : this.editor.getHTML();
            input.dispatchEvent(new Event('input', { bubbles: true }));
        },

        refresh() {
            const e = this.editor;
            if (!e) return;
            this.active = {
                bold: e.isActive('bold'), italic: e.isActive('italic'), underline: e.isActive('underline'), strike: e.isActive('strike'),
                bulletList: e.isActive('bulletList'), orderedList: e.isActive('orderedList'), blockquote: e.isActive('blockquote'),
                link: e.isActive('link'), code: e.isActive('code') || e.isActive('codeBlock'),
                block: e.isActive('heading', { level: 2 }) ? 'h2' : e.isActive('heading', { level: 3 }) ? 'h3' : e.isActive('heading', { level: 4 }) ? 'h4' : 'p',
                canUndo: e.can().undo(), canRedo: e.can().redo(),
            };
            this.inTable = e.isActive('table');
        },

        run(command) {
            const chain = this.editor.chain().focus();
            ({
                bold: () => chain.toggleBold(), italic: () => chain.toggleItalic(), underline: () => chain.toggleUnderline(), strike: () => chain.toggleStrike(),
                bulletList: () => chain.toggleBulletList(), orderedList: () => chain.toggleOrderedList(), blockquote: () => chain.toggleBlockquote(),
                code: () => (this.editor.isActive('code') || !this.editor.state.selection.empty ? chain.toggleCode() : chain.toggleCodeBlock()),
                rule: () => chain.setHorizontalRule(), undo: () => chain.undo(), redo: () => chain.redo(),
                clear: () => chain.clearNodes().unsetAllMarks(),
                table: () => chain.insertTable({ rows: 3, cols: 3, withHeaderRow: true }),
                rowAfter: () => chain.addRowAfter(), colAfter: () => chain.addColumnAfter(),
                rowDelete: () => chain.deleteRow(), colDelete: () => chain.deleteColumn(), tableDelete: () => chain.deleteTable(),
            }[command] ?? (() => chain))().run();
        },

        block(value) {
            const chain = this.editor.chain().focus();
            (value === 'p' ? chain.setParagraph() : chain.setHeading({ level: Number(value.slice(1)) })).run();
        },

        openLink() {
            const attrs = this.editor.getAttributes('link');
            this.link = { url: attrs.href ?? '', blank: attrs.target === '_blank', nofollow: /nofollow/.test(attrs.rel ?? ''), error: '' };
            this.$refs.linkDialog.showModal();
            this.$nextTick(() => this.$refs.linkUrl.focus());
        },

        applyLink() {
            const url = safeUrl(this.link.url);
            if (url === null) {
                this.link.error = this.labels.link_invalid ?? 'Invalid address';
                return;
            }
            const rel = [this.link.blank ? 'noopener noreferrer' : '', this.link.nofollow ? 'nofollow' : ''].filter(Boolean).join(' ') || null;
            this.editor.chain().focus().extendMarkRange('link').setLink({ href: url, target: this.link.blank ? '_blank' : null, rel }).run();
            this.$refs.linkDialog.close();
        },

        removeLink() {
            this.editor.chain().focus().extendMarkRange('link').unsetLink().run();
            this.$refs.linkDialog.close();
        },

        pickImage() {
            window.dispatchEvent(new CustomEvent('codeart:media-open', { detail: { token: this.token, multiple: true, type: 'image' } }));
        },

        insertMedia(detail) {
            if (!detail || detail.token !== this.token || !this.editor) return;
            for (const item of detail.items ?? []) {
                const src = safeUrl(item.url);
                if (src === null) continue;
                this.editor.chain().focus().insertContent({
                    type: 'image',
                    attrs: {
                        src, alt: item.alt || item.name || '', mediaId: String(item.id), srcset: item.srcset || null,
                        sizes: item.srcset ? '(max-width: 800px) 100vw, 800px' : null,
                        width: item.width ?? null, height: item.height ?? null, loading: 'lazy',
                    },
                }).run();
            }
        },

        /** HTML source view (super-admin only; the server sanitizes whatever comes back anyway). */
        toggleSource() {
            const area = this.$refs.source;
            if (!this.source) {
                area.value = this.editor.getHTML();
            } else {
                this.editor.commands.setContent(area.value, { emitUpdate: true });
            }
            this.source = !this.source;
            if (this.source) this.$nextTick(() => area.focus());
            else this.editor.commands.focus();
        },
    }));

    // Unsaved-changes guard for every form marked data-dirty-guard (the publishing form has it).
    const snapshot = (form) => JSON.stringify([...new FormData(form)].filter(([k]) => !['_token', 'intent', 'preview_locale'].includes(k)).map(([k, v]) => [k, typeof v === 'string' ? v : v.name]));
    document.addEventListener('DOMContentLoaded', () => {
        for (const form of document.querySelectorAll('form[data-dirty-guard]')) {
            let saved = null;
            setTimeout(() => (saved = snapshot(form)), 0);
            form.addEventListener('submit', () => (saved = null));
            window.addEventListener('beforeunload', (event) => {
                if (saved !== null && snapshot(form) !== saved) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        }
    });
}
