/*
 * Media library: the browser (grid/list, folders, upload, detail drawer), the picker dialog and the form field.
 * Progressive enhancement; the server decides everything (permissions, limits, confirmation tokens). No colors here.
 * Events: `codeart:media-open` {token, multiple, type} opens the picker; it answers with `codeart:media-selected` {token, items}.
 */
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const json = (response) => response.json().catch(() => ({}));

export default function registerMedia(Alpine) {
    Alpine.data('codeartMedia', (cfg) => ({
        cfg,
        t: cfg.t,
        items: [],
        next: null,
        loading: false,
        loaded: false,
        view: localStorage.getItem('codeart.media.view') === 'list' ? 'list' : 'grid',
        filters: { q: '', type: cfg.type ?? '', sort: 'newest', folder: '', from: '', to: '', uploader: '' },
        folders: cfg.folders,
        selected: [],
        uploads: [],
        detail: null,
        detailBusy: false,
        message: '',
        blocked: null,
        dragging: false,
        timer: null,

        init() {
            if (cfg.mode === 'page') this.load(true); // the picker loads when it is opened
        },

        setView(view) {
            this.view = view;
            localStorage.setItem('codeart.media.view', view);
        },

        query(cursor) {
            const p = new URLSearchParams();
            for (const [k, v] of Object.entries(this.filters)) if (v !== '' && v != null) p.set(k, v);
            if (cursor) p.set('cursor', cursor);
            return p.toString();
        },

        async load(reset = false) {
            if (this.loading) return;
            this.loading = true;
            try {
                const response = await fetch(cfg.itemsUrl + '?' + this.query(reset ? null : this.next), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error();
                const data = await response.json();
                this.items = reset ? data.items : this.items.concat(data.items);
                this.next = data.next;
                if (reset) this.selected = this.selected.filter((id) => this.items.some((i) => i.id === id));
            } catch {
                this.message = this.t.load_failed;
            } finally {
                this.loading = false;
                this.loaded = true;
            }
        },

        refilter() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.load(true), 250);
        },

        openFolder(id) {
            this.filters.folder = id === null ? '' : String(id);
            this.load(true);
        },

        folderName(id) {
            return this.folders.find((f) => f.id === id)?.name ?? '';
        },

        isSelected(id) {
            return this.selected.includes(id);
        },

        toggle(item, event) {
            if (cfg.mode === 'picker' && !cfg.multiple) {
                this.selected = [item.id];
                return;
            }
            this.selected = this.isSelected(item.id) ? this.selected.filter((i) => i !== item.id) : [...this.selected, item.id];
        },

        select(item, event) {
            if (cfg.mode === 'page' && !event.ctrlKey && !event.metaKey && !event.shiftKey && this.selected.length === 0) {
                this.openDetail(item.id);
                return;
            }
            this.toggle(item, event);
        },

        // ---- upload -------------------------------------------------------------------------------------------
        pickFiles(event) {
            this.upload(Array.from(event.target.files));
            event.target.value = '';
        },

        drop(event) {
            this.dragging = false;
            if (cfg.can.upload) this.upload(Array.from(event.dataTransfer?.files ?? []));
        },

        upload(files, duplicate = 'ask') {
            for (const file of files) {
                const entry = { name: file.name, progress: 0, status: 'waiting', error: '', duplicate: null, file, id: Math.random().toString(36).slice(2) };
                if (cfg.limitBytes > 0 && file.size > cfg.limitBytes) {
                    entry.status = 'error';
                    entry.error = this.t.too_large.replace(':limit', cfg.limitHuman);
                } else if (file.size === 0) {
                    entry.status = 'error';
                    entry.error = this.t.empty;
                }
                this.uploads.push(entry);
            }
            this.pump(duplicate);
        },

        pump(duplicate = 'ask') {
            const active = this.uploads.filter((u) => u.status === 'sending').length;
            let slots = 3 - active;
            for (const entry of this.uploads) {
                if (slots <= 0) break;
                if (entry.status === 'waiting') {
                    slots--;
                    this.send(entry, duplicate);
                }
            }
        },

        send(entry, duplicate) {
            entry.status = 'sending';
            const xhr = new XMLHttpRequest();
            const body = new FormData();
            body.append('file', entry.file);
            body.append('duplicate', duplicate);
            if (this.filters.folder !== '') body.append('folder_id', this.filters.folder);
            xhr.open('POST', cfg.storeUrl);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
            xhr.upload.onprogress = (e) => { if (e.lengthComputable) entry.progress = Math.round((e.loaded / e.total) * 100); };
            xhr.onload = () => {
                let data = {};
                try { data = JSON.parse(xhr.responseText); } catch { /* not JSON: a server error page */ }
                if (xhr.status === 201 || (xhr.status === 200 && data.media)) {
                    entry.status = 'done';
                    entry.progress = 100;
                    this.items.unshift(data.media);
                    if (cfg.mode === 'picker') this.selected = cfg.multiple ? [...this.selected, data.media.id] : [data.media.id];
                } else if (xhr.status === 409 && data.duplicate) {
                    entry.status = 'duplicate';
                    entry.duplicate = data.duplicate;
                    entry.error = data.message;
                } else {
                    entry.status = 'error';
                    entry.error = data.message || (xhr.status === 413 ? this.t.too_large.replace(':limit', cfg.limitHuman) : this.t.upload_failed);
                }
                this.pump();
            };
            xhr.onerror = () => { entry.status = 'error'; entry.error = this.t.upload_failed; this.pump(); };
            xhr.send(body);
        },

        reuse(entry) {
            this.items = [entry.duplicate, ...this.items.filter((i) => i.id !== entry.duplicate.id)];
            if (cfg.mode === 'picker') this.selected = cfg.multiple ? [...this.selected, entry.duplicate.id] : [entry.duplicate.id];
            entry.status = 'done';
        },

        keepBoth(entry) {
            entry.status = 'waiting';
            this.pump('keep');
        },

        clearUploads() {
            this.uploads = this.uploads.filter((u) => ['sending', 'waiting', 'duplicate'].includes(u.status));
        },

        // ---- detail drawer --------------------------------------------------------------------------------------
        async openDetail(id) {
            this.detailBusy = true;
            const response = await fetch(cfg.showUrl.replace('__ID__', id), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (response.ok) {
                this.detail = await response.json();
                this.detail.locale = Object.keys(this.detail.translations)[0] ?? '';
                this.$nextTick(() => this.$refs.drawer?.showModal());
            }
            this.detailBusy = false;
        },

        closeDetail() {
            this.$refs.drawer?.close();
            this.detail = null;
        },

        async saveDetail() {
            if (!this.detail) return;
            this.detailBusy = true;
            const response = await fetch(cfg.updateUrl.replace('__ID__', this.detail.media.id), {
                method: 'PUT',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                credentials: 'same-origin',
                body: JSON.stringify({ translations: this.detail.translations }),
            });
            this.detailBusy = false;
            if (response.ok) {
                const data = await response.json();
                this.replaceItem(data.media);
                this.toast(this.t.saved, 'success');
            } else {
                this.toast(this.t.save_failed, 'danger');
            }
        },

        async replaceFile(event) {
            const file = event.target.files[0];
            event.target.value = '';
            if (!file || !this.detail) return;
            const body = new FormData();
            body.append('file', file);
            this.detailBusy = true;
            const response = await fetch(cfg.replaceUrl.replace('__ID__', this.detail.media.id), {
                method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, credentials: 'same-origin', body,
            });
            const data = await json(response);
            this.detailBusy = false;
            if (response.ok) {
                this.detail.media = { ...this.detail.media, ...data.media };
                this.replaceItem(data.media);
                this.toast(this.t.replaced, 'success');
            } else {
                this.toast(data.message || this.t.upload_failed, 'danger');
            }
        },

        replaceItem(media) {
            this.items = this.items.map((i) => (i.id === media.id ? media : i));
        },

        async copyUrl(url) {
            const absolute = new URL(url, window.location.origin).href;
            try { await navigator.clipboard.writeText(absolute); this.toast(this.t.copied, 'success'); } catch { window.prompt(this.t.copy_url, absolute); }
        },

        // ---- folders, move, delete ------------------------------------------------------------------------------
        async createFolder() {
            const name = window.prompt(this.t.folder_name);
            if (!name) return;
            const response = await this.post(cfg.folderUrl, { name, parent_id: this.filters.folder === '' ? null : Number(this.filters.folder) });
            if (response.ok) this.folders = (await response.json()).folders;
        },

        async renameFolder(folder) {
            const name = window.prompt(this.t.folder_name, folder.name);
            if (!name || name === folder.name) return;
            const response = await fetch(cfg.folderUpdateUrl.replace('__ID__', folder.id), {
                method: 'PUT', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, credentials: 'same-origin', body: JSON.stringify({ name }),
            });
            if (response.ok) this.folders = (await response.json()).folders;
        },

        async deleteFolder(folder) {
            const token = await this.askConfirmation(cfg.confirm.folder);
            if (!token) return;
            const response = await this.post(cfg.folderDeleteUrl, { id: folder.id, confirmation: token });
            if (response.ok) {
                this.folders = (await response.json()).folders;
                if (this.filters.folder === String(folder.id)) this.openFolder(null);
                else this.load(true);
            }
        },

        async moveSelected(event) {
            const value = event.target.value;
            event.target.value = '';
            if (value === '' || this.selected.length === 0) return;
            const response = await this.post(cfg.moveUrl, { ids: this.selected, folder_id: value === 'root' ? null : Number(value) });
            if (response.ok) {
                this.toast(this.t.moved, 'success');
                this.selected = [];
                this.load(true);
            }
        },

        async deleteSelected(force = false) {
            if (this.selected.length === 0) return;
            const token = await this.askConfirmation(force ? cfg.confirm.force : cfg.confirm.delete);
            if (!token) return;
            await this.runDelete(this.selected, force, token);
        },

        async runDelete(ids, force, token) {
            const response = await this.post(cfg.deleteUrl, { ids, force, confirmation: token });
            const data = await json(response);
            if (response.status === 428 || (!response.ok && response.status !== 409)) {
                this.toast(this.t.delete_failed, 'danger');
                return;
            }
            this.items = this.items.filter((i) => !(data.deleted ?? []).includes(i.id));
            this.selected = this.selected.filter((id) => !(data.deleted ?? []).includes(id));
            this.blocked = data.blocked?.length ? data.blocked : null;
            if (data.deleted?.length) this.toast(this.t.deleted, 'success');
            if (this.detail && (data.deleted ?? []).includes(this.detail.media.id)) this.closeDetail();
        },

        async deleteDetail() {
            if (!this.detail) return;
            this.selected = [this.detail.media.id];
            await this.deleteSelected(false);
        },

        forceBlocked() {
            this.selected = this.blocked.map((b) => b.id);
            this.blocked = null;
            return this.deleteSelected(true);
        },

        /** Server-issued confirmation: ask the confirmation step for a one-time token, after the user agrees in a dialog. */
        async askConfirmation(spec) {
            if (!(await this.dialogAnswer(spec))) return null;
            const body = new URLSearchParams({ target: spec.target, verb: spec.verb, title: spec.title, text: spec.text, confirm: spec.confirm, sig: spec.sig, _token: csrf() });
            const response = await fetch(cfg.confirmUrl, { method: 'POST', body, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            return response.ok ? (await response.json()).token : null;
        },

        /** The confirmation dialog (a native <dialog> of the page): resolves true when the person presses the confirm button. */
        dialogAnswer(spec) {
            return new Promise((resolve) => {
                this.ask = { title: spec.title, text: spec.text, button: spec.confirm };
                const dialog = this.$refs.confirmDialog;
                const finish = (answer) => { dialog.removeEventListener('close', onClose); resolve(answer); };
                const onClose = () => finish(dialog.returnValue === 'yes');
                dialog.returnValue = 'no';
                dialog.addEventListener('close', onClose);
                dialog.showModal();
            });
        },

        ask: { title: '', text: '', button: '' },

        post(url, data) {
            return fetch(url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                credentials: 'same-origin',
                body: JSON.stringify(data),
            });
        },

        toast(message, type = 'info') {
            window.dispatchEvent(new CustomEvent('codeart:toast', { detail: { message, type } }));
        },

        human(bytes) {
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
            return Math.max(1, Math.round(bytes / 1024)) + ' KB';
        },

        // ---- picker ---------------------------------------------------------------------------------------------
        confirmPick() {
            const items = this.selected.map((id) => this.items.find((i) => i.id === id)).filter(Boolean);
            window.dispatchEvent(new CustomEvent('codeart:media-selected', { detail: { token: this.pickerToken, items } }));
            this.$refs.dialog?.close();
        },

        pickerToken: null,
        open(detail) {
            this.pickerToken = detail.token;
            cfg.multiple = !!detail.multiple;
            this.filters.type = detail.type === 'image' ? 'image' : '';
            this.selected = [];
            this.load(true);
            this.$refs.dialog?.showModal();
        },
    }));

    /** The `<x-codeart::form.media>` field: hidden inputs hold the ids; the picker answers through window events. */
    Alpine.data('codeartMediaField', ({ items = [], multiple = false, type = 'image' }) => ({
        items,
        token: 'f' + Math.random().toString(36).slice(2),
        choose() {
            window.dispatchEvent(new CustomEvent('codeart:media-open', { detail: { token: this.token, multiple, type } }));
        },
        onSelected(event) {
            if (event.detail.token !== this.token) return;
            const picked = event.detail.items;
            this.items = multiple ? [...this.items, ...picked.filter((p) => !this.items.some((i) => i.id === p.id))] : picked.slice(0, 1);
            this.$el.dispatchEvent(new Event('input', { bubbles: true }));
        },
        remove(index) { this.items.splice(index, 1); this.$el.dispatchEvent(new Event('input', { bubbles: true })); },
        move(index, delta) {
            const target = index + delta;
            if (target < 0 || target >= this.items.length) return;
            const copy = [...this.items];
            [copy[index], copy[target]] = [copy[target], copy[index]];
            this.items = copy;
            this.$el.dispatchEvent(new Event('input', { bubbles: true }));
        },
    }));
}
