@php
    /** @var string $name */
    /** @var string $label */
    /** @var string|null $value */
    /** @var string|null $helpText */
    $editorId = 'rich-editor-'.preg_replace('/[^a-z0-9_-]/i', '-', $name);
    $initialHtml = (string) ($value ?? '');
@endphp

<div class="space-y-2" data-rich-editor data-turbo="false">
    <label for="{{ $editorId }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
        {{ $label }}
    </label>
    @if (!empty($helpText))
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $helpText }}</p>
    @endif

    <div class="rich-editor__guide" role="note">
        <i class="fas fa-keyboard" aria-hidden="true"></i>
        <div>
            <p><strong>Enter</strong> — new paragraph on the next line (what you usually want).</p>
            <p><strong>Shift + Enter</strong> — soft line break inside the same paragraph.</p>
        </div>
    </div>

    <div id="{{ $editorId }}-toolbar" class="rich-editor__toolbar">
        <span class="ql-formats">
            <select class="ql-header">
                <option value="2">Heading</option>
                <option value="3">Subheading</option>
                <option selected>Normal</option>
            </select>
        </span>
        <span class="ql-formats">
            <button class="ql-bold" type="button"></button>
            <button class="ql-italic" type="button"></button>
            <button class="ql-underline" type="button"></button>
        </span>
        <span class="ql-formats">
            <button class="ql-list" value="ordered" type="button"></button>
            <button class="ql-list" value="bullet" type="button"></button>
        </span>
        <span class="ql-formats">
            <button class="ql-blockquote" type="button"></button>
            <button class="ql-link" type="button"></button>
            <button type="button" class="ql-softbreak" title="Insert line break (Shift+Enter)">
                <span aria-hidden="true">↵</span>
            </button>
            <button class="ql-clean" type="button"></button>
        </span>
    </div>
    <div id="{{ $editorId }}" class="rich-editor__surface"></div>

    {{-- Canonical payload for Quill (JSON survives quotes/HTML; textarea alone can be wiped by a failed paste). --}}
    <script type="application/json" data-rich-initial id="{{ $editorId }}-initial">@json($initialHtml)</script>
    <textarea name="{{ $name }}" id="{{ $editorId }}-input" class="hidden" aria-hidden="true">{{ $initialHtml }}</textarea>

    <div class="rich-editor__preview-wrap">
        <div class="rich-editor__preview-head">
            <span class="rich-editor__preview-label">Website preview</span>
            <span class="rich-editor__preview-hint">How this text will look on the live page</span>
        </div>
        <div class="rich-editor__preview shopify-policy-preview" data-rich-preview aria-live="polite"></div>
    </div>

    @error($name)
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

@once
    @push('styles')
        <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
        <style>
            .rich-editor__guide {
                display: flex;
                gap: 0.75rem;
                align-items: flex-start;
                padding: 0.75rem 0.9rem;
                border: 1px solid #e5e5e5;
                border-radius: 10px;
                background: #fafafa;
                font-size: 0.8rem;
                line-height: 1.45;
                color: #525252;
            }
            .rich-editor__guide i {
                margin-top: 0.15rem;
                color: #0a0a0a;
                opacity: 0.55;
            }
            .rich-editor__guide p { margin: 0; }
            .rich-editor__guide p + p { margin-top: 0.2rem; }
            .dark .rich-editor__guide {
                background: #111827;
                border-color: #374151;
                color: #d1d5db;
            }

            .rich-editor__toolbar.ql-toolbar {
                border: 1px solid var(--color-line, #e5e5e5);
                border-radius: 10px 10px 0 0;
                background: var(--color-paper, #fafafa);
            }
            .rich-editor__surface.ql-container {
                border: 1px solid var(--color-line, #e5e5e5);
                border-top: 0;
                border-radius: 0 0 10px 10px;
                min-height: 320px;
                font-size: 0.98rem;
                background: #fff;
            }
            .rich-editor__surface .ql-editor {
                min-height: 300px;
                line-height: 1.7;
                padding: 1.1rem 1.15rem 1.4rem;
                font-family: var(--font-policy);
            }
            .rich-editor__surface .ql-editor p {
                display: block;
                margin: 0 0 1rem;
            }
            .rich-editor__surface .ql-editor h2,
            .rich-editor__surface .ql-editor h3 {
                display: block;
                margin: 1.5rem 0 0.75rem;
                font-weight: 700;
            }
            .rich-editor__surface .ql-editor h2 { font-size: 1.25rem; }
            .rich-editor__surface .ql-editor h3 { font-size: 1.05rem; }
            .rich-editor__surface .ql-editor li { margin-bottom: 0.35rem; }
            .rich-editor__surface .ql-editor p:has(> br:only-child) {
                min-height: 1.1em;
                margin-bottom: 0.85rem;
            }

            .ql-snow .ql-toolbar button.ql-softbreak {
                width: 28px;
            }
            .ql-snow .ql-toolbar button.ql-softbreak span {
                font-size: 14px;
                font-weight: 700;
                line-height: 1;
            }

            .rich-editor__preview-wrap {
                margin-top: 1rem;
                border: 1px solid #e5e5e5;
                border-radius: 12px;
                overflow: hidden;
                background: #fff;
            }
            .rich-editor__preview-head {
                display: flex;
                flex-wrap: wrap;
                align-items: baseline;
                gap: 0.5rem 1rem;
                padding: 0.7rem 1rem;
                border-bottom: 1px solid #e5e5e5;
                background: #fafafa;
            }
            .rich-editor__preview-label {
                font-size: 0.72rem;
                font-weight: 700;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                color: #0a0a0a;
            }
            .rich-editor__preview-hint {
                font-size: 0.75rem;
                color: #737373;
            }
            .rich-editor__preview {
                padding: 1.35rem 1.25rem 1.6rem;
                max-height: 420px;
                overflow: auto;
                color: #0a0a0a;
                font-family: var(--font-policy);
                font-size: 1rem;
                line-height: 1.8;
                text-align: left;
            }
            .rich-editor__preview p {
                display: block;
                margin: 0 0 1.15rem;
            }
            .rich-editor__preview p:first-child {
                margin-bottom: 1.75rem;
                font-size: 0.78rem;
                font-weight: 700;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                color: #737373;
            }
            .rich-editor__preview h2 {
                display: block;
                font-size: 1.3rem;
                font-weight: 700;
                letter-spacing: -0.02em;
                margin: 2rem 0 0.85rem;
                padding-top: 1.1rem;
                border-top: 1px solid rgb(0 0 0 / 0.1);
            }
            .rich-editor__preview h2:first-child {
                border-top: 0;
                padding-top: 0;
            }
            .rich-editor__preview h3 {
                display: block;
                font-size: 1.05rem;
                font-weight: 700;
                margin: 1.35rem 0 0.55rem;
            }
            .rich-editor__preview ul,
            .rich-editor__preview ol {
                margin: 0 0 1.15rem;
                padding-left: 1.25rem;
            }
            .rich-editor__preview li { margin-bottom: 0.4rem; }
            .rich-editor__preview a {
                color: #0a0a0a;
                text-decoration: underline;
                text-underline-offset: 2px;
            }
            .rich-editor__preview blockquote {
                margin: 0 0 1.15rem;
                padding-left: 1rem;
                border-left: 2px solid #0a0a0a;
                opacity: 0.85;
            }
            .rich-editor__preview .ql-ui { display: none !important; }
            .rich-editor__preview p:has(> br:only-child) {
                min-height: 0.85em;
                margin-bottom: 0.85rem;
            }

            .dark .rich-editor__toolbar.ql-toolbar,
            .dark .rich-editor__surface.ql-container,
            .dark .rich-editor__preview-wrap {
                background: #111827;
                border-color: #374151;
                color: #f3f4f6;
            }
            .dark .rich-editor__preview-head { background: #0f172a; border-color: #374151; }
            .dark .rich-editor__preview { color: #f3f4f6; }
            .dark .ql-snow .ql-stroke { stroke: #d1d5db; }
            .dark .ql-snow .ql-fill { fill: #d1d5db; }
            .dark .ql-snow .ql-picker { color: #d1d5db; }
        </style>
    @endpush

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
        <script>
            (function () {
                function normalizePreviewHtml(html) {
                    if (!html || html === '<p><br></p>' || html === '<p></p>') {
                        return '<p style="opacity:0.45">Start writing — paragraphs will stack below each other here.</p>';
                    }
                    return html
                        .replace(/<span[^>]*class="[^"]*ql-ui[^"]*"[^>]*><\/span>/gi, '')
                        .replace(/\s*contenteditable="[^"]*"/gi, '')
                        .replace(/\s*data-list="[^"]*"/gi, '')
                        .replace(/\s*class="ql-[^"]*"/gi, '');
                }

                function isEmptyQuillHtml(html) {
                    const t = (html || '').trim();
                    return !t || t === '<p><br></p>' || t === '<p></p>' || t === '<p><br/></p>';
                }

                function readInitialHtml(wrap, input) {
                    const slot = wrap.querySelector('[data-rich-initial]');
                    if (slot) {
                        try {
                            const parsed = JSON.parse(slot.textContent || '""');
                            if (typeof parsed === 'string' && parsed.trim()) return parsed;
                        } catch (_) {}
                    }
                    return (input.value || '').trim();
                }

                function loadHtmlIntoQuill(quill, html) {
                    if (!html) return false;
                    try {
                        const delta = quill.clipboard.convert({ html: html });
                        quill.setContents(delta, Quill.sources.SILENT);
                        if (!isEmptyQuillHtml(quill.root.innerHTML)) return true;
                    } catch (_) {}
                    try {
                        quill.clipboard.dangerouslyPasteHTML(0, html, Quill.sources.SILENT);
                        if (!isEmptyQuillHtml(quill.root.innerHTML)) return true;
                    } catch (_) {}
                    // Last resort: paint HTML into the editor root so staff always see saved copy.
                    try {
                        quill.root.innerHTML = html;
                        return !isEmptyQuillHtml(quill.root.innerHTML);
                    } catch (_) {
                        return false;
                    }
                }

                function bootRichEditors() {
                    if (typeof Quill === 'undefined') return;

                    document.querySelectorAll('[data-rich-editor]').forEach((wrap) => {
                        if (wrap.dataset.booted === '1') return;
                        const surface = wrap.querySelector('.rich-editor__surface');
                        const toolbar = wrap.querySelector('.rich-editor__toolbar');
                        const input = wrap.querySelector('textarea');
                        const preview = wrap.querySelector('[data-rich-preview]');
                        if (!surface || !toolbar || !input) return;

                        // Quill mutates the surface node — skip if already upgraded mid-Turbo race.
                        if (surface.classList.contains('ql-container') && wrap.__quill) {
                            wrap.dataset.booted = '1';
                            return;
                        }

                        const initialHtml = readInitialHtml(wrap, input);

                        const quill = new Quill(surface, {
                            theme: 'snow',
                            modules: { toolbar },
                            placeholder: 'Press Enter to start a new line of text…',
                        });
                        wrap.__quill = quill;

                        const softBtn = toolbar.querySelector('.ql-softbreak');
                        if (softBtn) {
                            softBtn.addEventListener('click', (e) => {
                                e.preventDefault();
                                const range = quill.getSelection(true);
                                if (!range) return;
                                try {
                                    quill.insertEmbed(range.index, 'break', true, Quill.sources.USER);
                                    quill.setSelection(range.index + 1, Quill.sources.SILENT);
                                } catch (_) {
                                    quill.insertText(range.index, '\n', Quill.sources.USER);
                                    quill.setSelection(range.index + 1, Quill.sources.SILENT);
                                }
                            });
                        }

                        // Load CMS HTML BEFORE paste matchers — whitespace text nodes between
                        // block tags must not be rewritten or Quill can render an empty editor.
                        const loaded = loadHtmlIntoQuill(quill, initialHtml);
                        if (initialHtml && !loaded) {
                            input.value = initialHtml;
                            if (preview) preview.innerHTML = normalizePreviewHtml(initialHtml);
                        }

                        const Delta = Quill.import('delta');
                        quill.clipboard.addMatcher(Node.TEXT_NODE, (node, delta) => {
                            const text = node.data || '';
                            if (!text.includes('\n')) return delta;
                            // Keep structural whitespace between tags intact.
                            if (!text.replace(/\s/g, '').length) return delta;
                            let next = new Delta();
                            const parts = text.split(/\r?\n/);
                            parts.forEach((part, i) => {
                                if (part.length) next = next.insert(part);
                                if (i < parts.length - 1) next = next.insert('\n');
                            });
                            return next;
                        });

                        const sync = () => {
                            const html = quill.root.innerHTML;
                            if (isEmptyQuillHtml(html)) {
                                // Never destroy known CMS content on a blank Quill shell.
                                if (initialHtml && !wrap.dataset.userEdited) {
                                    input.value = initialHtml;
                                    if (preview) preview.innerHTML = normalizePreviewHtml(initialHtml);
                                    return;
                                }
                                input.value = '';
                            } else {
                                input.value = html;
                            }
                            if (preview) preview.innerHTML = normalizePreviewHtml(input.value || html);
                        };

                        quill.on('text-change', (delta, oldDelta, source) => {
                            if (source === Quill.sources.USER) {
                                wrap.dataset.userEdited = '1';
                            }
                            if (source === Quill.sources.SILENT) return;
                            sync();
                        });
                        sync();

                        const form = wrap.closest('form');
                        if (form) {
                            form.addEventListener('submit', () => {
                                const html = quill.root.innerHTML;
                                if (!isEmptyQuillHtml(html)) {
                                    input.value = html;
                                } else if (initialHtml && wrap.dataset.userEdited !== '1') {
                                    // Preserve DB content if Quill never received a successful load/edit.
                                    input.value = initialHtml;
                                } else {
                                    input.value = '';
                                }
                            });
                        }

                        wrap.dataset.booted = '1';
                    });
                }

                function ensureQuillThenBoot() {
                    if (typeof Quill !== 'undefined') {
                        bootRichEditors();
                        return;
                    }
                    // Turbo soft-nav may land here before the CDN script evaluates.
                    let tries = 0;
                    const timer = setInterval(() => {
                        tries += 1;
                        if (typeof Quill !== 'undefined' || tries > 40) {
                            clearInterval(timer);
                            bootRichEditors();
                        }
                    }, 50);
                }

                function resetEditorsForTurboCache() {
                    document.querySelectorAll('[data-rich-editor]').forEach((wrap) => {
                        const quill = wrap.__quill;
                        const input = wrap.querySelector('textarea');
                        const initialSlot = wrap.querySelector('[data-rich-initial]');
                        if (quill && input) {
                            const html = quill.root.innerHTML;
                            if (!isEmptyQuillHtml(html)) {
                                input.value = html;
                                if (initialSlot) {
                                    initialSlot.textContent = JSON.stringify(html);
                                }
                            }
                        }
                        delete wrap.__quill;
                        delete wrap.dataset.booted;
                        delete wrap.dataset.userEdited;
                    });
                }

                document.addEventListener('DOMContentLoaded', ensureQuillThenBoot);
                document.addEventListener('turbo:load', ensureQuillThenBoot);
                document.addEventListener('turbo:render', ensureQuillThenBoot);
                document.addEventListener('turbo:before-cache', resetEditorsForTurboCache);
            })();
        </script>
    @endpush
@endonce
