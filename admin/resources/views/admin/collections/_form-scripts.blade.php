<script>
(function () {
    const sourceInput = document.getElementById('image_source');
    const removeInput = document.getElementById('remove_image');
    const tabs = document.querySelectorAll('[data-image-tab]');
    const panelUpload = document.getElementById('image-panel-upload');
    const panelUrl = document.getElementById('image-panel-url');
    const fileInput = document.getElementById('image_file');
    const urlInput = document.getElementById('image_url');
    const dropzone = document.getElementById('image-dropzone');
    const emptyState = document.getElementById('image-dropzone-empty');
    const previewState = document.getElementById('image-dropzone-preview');
    const previewImg = document.getElementById('image-preview-img');
    const previewMeta = document.getElementById('image-preview-meta');
    const clearBtn = document.getElementById('image-clear');
    const progressRoot = document.getElementById('image-progress');
    const progressBar = document.getElementById('image-progress-bar');
    const progressPct = document.getElementById('image-progress-pct');
    const progressLabel = document.getElementById('image-progress-label');
    const progressTrack = progressRoot?.querySelector('[role="progressbar"]');
    const urlStatus = document.getElementById('image-url-status');
    const urlPreviewWrap = document.getElementById('image-url-preview');
    const urlPreviewImg = document.getElementById('image-url-preview-img');
    const tileImg = document.getElementById('collection-tile-img');
    const tileFallback = document.getElementById('collection-tile-fallback');
    const tileWordmark = document.getElementById('collection-tile-wordmark');
    const tileName = document.getElementById('collection-tile-name');
    const tileSlug = document.getElementById('collection-tile-slug');
    const tileLoading = document.getElementById('collection-tile-loading');
    const tileMedia = document.getElementById('collection-tile-media');
    const nameEn = document.getElementById('name_en');
    const nameAr = document.getElementById('name_ar');

    let objectUrl = null;
    let urlTimer = null;
    let loadToken = 0;
    let lastMeta = '';

    const MAX_BYTES = 5 * 1024 * 1024;

    function slugify(value) {
        return String(value || '')
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .replace(/-{2,}/g, '-');
    }

    function formatBytes(bytes) {
        if (!bytes && bytes !== 0) return '';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    function setProgress(pct, label) {
        if (!progressRoot) return;
        const value = Math.max(0, Math.min(100, Math.round(pct)));
        progressRoot.hidden = false;
        dropzone?.classList.add('is-loading');
        if (progressBar) progressBar.style.width = value + '%';
        if (progressPct) progressPct.textContent = value + '%';
        if (progressLabel && label) progressLabel.textContent = label;
        if (progressTrack) progressTrack.setAttribute('aria-valuenow', String(value));
    }

    function hideProgress() {
        if (!progressRoot) return;
        progressRoot.hidden = true;
        dropzone?.classList.remove('is-loading');
        if (progressBar) progressBar.style.width = '0%';
    }

    function setTileLoading(on) {
        if (!tileLoading) return;
        tileLoading.hidden = !on;
        tileMedia?.classList.toggle('is-loading', !!on);
    }

    function setClearVisible(on) {
        clearBtn?.classList.toggle('hidden', !on);
    }

    function setUrlStatus(message, kind) {
        if (!urlStatus) return;
        if (!message) {
            urlStatus.hidden = true;
            urlStatus.textContent = '';
            urlStatus.classList.remove('is-error', 'is-ok');
            return;
        }
        urlStatus.hidden = false;
        urlStatus.textContent = message;
        urlStatus.classList.toggle('is-error', kind === 'error');
        urlStatus.classList.toggle('is-ok', kind === 'ok');
    }

    function decodeImage(src) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.decoding = 'async';
            img.onload = () => resolve(img);
            img.onerror = () => reject(new Error('Could not load image'));
            img.src = src;
        });
    }

    function readFileWithProgress(file, onProgress) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onprogress = (event) => {
                if (!event.lengthComputable) return;
                onProgress((event.loaded / event.total) * 100);
            };
            reader.onload = () => resolve(String(reader.result || ''));
            reader.onerror = () => reject(new Error('Could not read file'));
            reader.readAsDataURL(file);
        });
    }

    function setSource(mode) {
        sourceInput.value = mode;
        tabs.forEach((tab) => tab.classList.toggle('is-active', tab.dataset.imageTab === mode));
        panelUpload.classList.toggle('hidden', mode !== 'upload');
        panelUrl.classList.toggle('hidden', mode !== 'url');
        if (removeInput) removeInput.value = '0';
        refreshTile();
    }

    function showDropzonePreview(src, metaText) {
        if (!src) {
            emptyState?.classList.remove('hidden');
            previewState?.classList.add('hidden');
            previewImg?.removeAttribute('src');
            if (previewMeta) {
                previewMeta.hidden = true;
                previewMeta.textContent = '';
            }
            lastMeta = '';
            return;
        }
        if (previewImg) previewImg.src = src;
        emptyState?.classList.add('hidden');
        previewState?.classList.remove('hidden');
        if (previewMeta) {
            if (metaText) {
                previewMeta.hidden = false;
                previewMeta.textContent = metaText;
                lastMeta = metaText;
            } else if (lastMeta) {
                previewMeta.hidden = false;
                previewMeta.textContent = lastMeta;
            } else {
                previewMeta.hidden = true;
            }
        }
    }

    function showUrlPreview(src) {
        if (!urlPreviewWrap || !urlPreviewImg) return;
        if (!src) {
            urlPreviewWrap.classList.add('hidden');
            urlPreviewImg.removeAttribute('src');
            return;
        }
        urlPreviewImg.src = src;
        urlPreviewWrap.classList.remove('hidden');
    }

    function applyTileSrc(src) {
        if (src) {
            tileImg.src = src;
            tileImg.classList.remove('hidden');
            tileFallback?.classList.add('hidden');
            setClearVisible(true);
        } else {
            tileImg.classList.add('hidden');
            tileImg.removeAttribute('src');
            tileFallback?.classList.remove('hidden');
            setClearVisible(false);
        }
    }

    function clearImage() {
        loadToken += 1;
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;
        if (fileInput) fileInput.value = '';
        if (urlInput) urlInput.value = '';
        if (removeInput) removeInput.value = '1';
        lastMeta = '';
        hideProgress();
        setTileLoading(false);
        setUrlStatus('');
        showDropzonePreview('');
        showUrlPreview('');
        setClearVisible(false);
        refreshTile();
    }

    function refreshTile() {
        const name = (nameEn?.value || '').trim() || 'New collection';
        const slug = (document.getElementById('slug_en')?.value || '').trim() || '…';
        if (tileName) tileName.textContent = name;
        if (tileWordmark) tileWordmark.textContent = name;
        if (tileSlug) tileSlug.textContent = '/collections/' + slug;

        let src = '';
        if (removeInput?.value === '1') {
            src = '';
        } else if (sourceInput.value === 'url') {
            src = (urlInput?.value || '').trim();
        } else if (previewImg?.getAttribute('src')) {
            src = (previewImg.getAttribute('src') || '').trim();
        }

        // Ignore empty / page-relative accidental src values.
        if (!src || src === window.location.href || src === window.location.pathname) {
            src = '';
        }

        applyTileSrc(src);
    }

    async function revealPreview(src, metaText) {
        const token = ++loadToken;
        setTileLoading(true);
        setProgress(92, 'Rendering high-quality preview…');
        try {
            const img = await decodeImage(src);
            if (token !== loadToken) return;
            const dims = img.naturalWidth && img.naturalHeight
                ? `${img.naturalWidth}×${img.naturalHeight}`
                : '';
            const meta = [metaText, dims].filter(Boolean).join(' · ');
            showDropzonePreview(src, meta);
            applyTileSrc(src);
            setClearVisible(true);
            setProgress(100, 'Preview ready');
            window.setTimeout(() => {
                if (token === loadToken) hideProgress();
            }, 280);
        } catch (_) {
            if (token !== loadToken) return;
            hideProgress();
            showDropzonePreview('');
            applyTileSrc('');
            setClearVisible(false);
            if (sourceInput.value === 'url') {
                setUrlStatus('Could not load this image URL. Check the link and try again.', 'error');
                showUrlPreview('');
            }
        } finally {
            if (token === loadToken) setTileLoading(false);
        }
    }

    async function handleFile(file) {
        if (!file) return;
        if (!file.type.startsWith('image/') && !/\.svg$/i.test(file.name || '')) {
            setProgress(0, 'Unsupported file type');
            window.setTimeout(hideProgress, 900);
            return;
        }
        if (file.size > MAX_BYTES) {
            setProgress(0, 'Image must be 5 MB or smaller');
            window.setTimeout(hideProgress, 1200);
            return;
        }

        const token = ++loadToken;
        if (removeInput) removeInput.value = '0';
        setSource('upload');
        setUrlStatus('');
        showUrlPreview('');
        setProgress(4, 'Reading image…');
        setTileLoading(true);

        try {
            let src;
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;

            // Prefer FileReader progress for large files; fall back to object URL for SVG.
            if (file.type === 'image/svg+xml' || /\.svg$/i.test(file.name || '')) {
                objectUrl = URL.createObjectURL(file);
                src = objectUrl;
                setProgress(70, 'Preparing SVG…');
            } else {
                src = await readFileWithProgress(file, (pct) => {
                    if (token !== loadToken) return;
                    setProgress(Math.max(6, pct * 0.75), 'Loading image…');
                });
            }

            if (token !== loadToken) return;
            const meta = `${file.name || 'Image'} · ${formatBytes(file.size)}`;
            await revealPreview(src, meta);
        } catch (_) {
            if (token !== loadToken) return;
            hideProgress();
            setTileLoading(false);
            setProgress(0, 'Could not preview this file');
            window.setTimeout(hideProgress, 1000);
        }
    }

    async function handleUrl(raw) {
        const src = String(raw || '').trim();
        if (!src) {
            showUrlPreview('');
            setUrlStatus('');
            refreshTile();
            return;
        }

        let parsed;
        try {
            parsed = new URL(src);
        } catch (_) {
            setUrlStatus('Enter a valid image URL (https://…)', 'error');
            showUrlPreview('');
            applyTileSrc('');
            return;
        }

        if (!/^https?:$/i.test(parsed.protocol)) {
            setUrlStatus('Only http(s) image links are allowed.', 'error');
            return;
        }

        const token = ++loadToken;
        if (removeInput) removeInput.value = '0';
        setUrlStatus('Fetching image…');
        setProgress(12, 'Fetching remote image…');
        setTileLoading(true);
        showUrlPreview('');

        // Soft progress while the network image loads.
        let fake = 12;
        const tick = window.setInterval(() => {
            if (token !== loadToken) {
                window.clearInterval(tick);
                return;
            }
            fake = Math.min(88, fake + 4 + Math.random() * 6);
            setProgress(fake, 'Downloading preview…');
        }, 160);

        try {
            const img = await decodeImage(src);
            window.clearInterval(tick);
            if (token !== loadToken) return;
            const dims = `${img.naturalWidth}×${img.naturalHeight}`;
            setProgress(96, 'Rendering…');
            showUrlPreview(src);
            showDropzonePreview(src, `Remote image · ${dims}`);
            applyTileSrc(src);
            setClearVisible(true);
            setUrlStatus(`Preview ready · ${dims}`, 'ok');
            setProgress(100, 'Preview ready');
            window.setTimeout(() => {
                if (token === loadToken) hideProgress();
            }, 260);
        } catch (_) {
            window.clearInterval(tick);
            if (token !== loadToken) return;
            hideProgress();
            showUrlPreview('');
            applyTileSrc('');
            setClearVisible(false);
            setUrlStatus('Could not load this image URL. Check the link and try again.', 'error');
        } finally {
            if (token === loadToken) setTileLoading(false);
        }
    }

    // ── Slug controls ──────────────────────────────────────────────
    const slugBlocks = Array.from(document.querySelectorAll('.studio-slug'));

    function slugFromName(locale) {
        if (locale === 'ar') {
            const fromAr = slugify(nameAr?.value || '');
            if (fromAr) return fromAr;
            return slugify(nameEn?.value || '');
        }
        return slugify(nameEn?.value || '');
    }

    slugBlocks.forEach((block) => {
        const locale = block.dataset.slugLocale;
        const input = block.querySelector('[data-slug-input]');
        const syncBtn = block.querySelector('[data-slug-sync]');
        const lockBtn = block.querySelector('[data-slug-lock]');
        const lockIcon = block.querySelector('[data-lock-icon]');
        const lockLabel = block.querySelector('[data-lock-label]');
        const live = block.querySelector('[data-slug-live]');
        const nameInput = locale === 'ar' ? nameAr : nameEn;

        let auto = !(input.value && input.value.trim());

        function setAuto(next) {
            auto = next;
            lockBtn.classList.toggle('is-active', auto);
            lockIcon.className = auto ? 'fas fa-lock-open' : 'fas fa-lock';
            lockLabel.textContent = auto ? 'Auto' : 'Manual';
            block.classList.toggle('is-manual', !auto);
        }

        function applyFromName() {
            const next = slugFromName(locale);
            input.value = next;
            if (live) live.textContent = next || '…';
            refreshTile();
        }

        function sanitizeInput() {
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const cleaned = slugify(input.value);
            if (cleaned !== input.value) {
                input.value = cleaned;
                try {
                    input.setSelectionRange(start, end);
                } catch (_) {}
            }
            if (live) live.textContent = cleaned || '…';
            refreshTile();
        }

        setAuto(auto);

        nameInput?.addEventListener('input', () => {
            if (auto) applyFromName();
            else refreshTile();
        });

        if (locale === 'ar') {
            nameEn?.addEventListener('input', () => {
                if (auto && !slugify(nameAr?.value || '')) applyFromName();
            });
        }

        input.addEventListener('input', () => {
            setAuto(false);
            sanitizeInput();
        });

        syncBtn?.addEventListener('click', () => {
            setAuto(true);
            applyFromName();
        });

        lockBtn?.addEventListener('click', () => {
            setAuto(!auto);
            if (auto) applyFromName();
        });

        if (auto && !input.value) applyFromName();
    });

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => setSource(tab.dataset.imageTab));
    });

    fileInput?.addEventListener('change', (e) => {
        const file = e.target.files?.[0];
        handleFile(file);
    });

    urlInput?.addEventListener('input', () => {
        if (removeInput) removeInput.value = '0';
        if (sourceInput.value !== 'url') setSource('url');
        window.clearTimeout(urlTimer);
        urlTimer = window.setTimeout(() => handleUrl(urlInput.value), 450);
    });

    urlInput?.addEventListener('change', () => {
        window.clearTimeout(urlTimer);
        handleUrl(urlInput.value);
    });

    clearBtn?.addEventListener('click', clearImage);
    nameEn?.addEventListener('input', refreshTile);

    if (window.DokanWardDropzone?.bindDropzone) {
        window.DokanWardDropzone.bindDropzone(dropzone, {
            onFile(file) {
                if (!file || !fileInput) return;
                const dt = new DataTransfer();
                dt.items.add(file);
                fileInput.files = dt.files;
                handleFile(file);
            },
        });
    } else {
        ;['dragenter', 'dragover'].forEach((evt) => {
            dropzone?.addEventListener(evt, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('is-dragover');
            });
        });
        dropzone?.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (e.relatedTarget && dropzone.contains(e.relatedTarget)) return;
            dropzone.classList.remove('is-dragover');
        });
        dropzone?.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('is-dragover');
            const file = e.dataTransfer?.files?.[0];
            if (!file || !fileInput) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            fileInput.files = dt.files;
            handleFile(file);
        });
    }

    if (sourceInput.value === 'upload' && (previewImg?.getAttribute('src') || '').trim()) {
        showDropzonePreview(previewImg.getAttribute('src'));
        setClearVisible(true);
    } else {
        hideProgress();
        setTileLoading(false);
        setClearVisible(false);
    }
    if (sourceInput.value === 'url' && (urlInput?.value || '').trim()) {
        handleUrl(urlInput.value);
    }
    refreshTile();

    const publishToggle = document.getElementById('is_published');
    const onLabel = document.querySelector('.studio-visibility__state[data-on]');
    const offLabel = document.querySelector('.studio-visibility__state[data-off]');
    function syncPublishLabels() {
        if (!publishToggle || !onLabel || !offLabel) return;
        const on = publishToggle.checked;
        onLabel.hidden = !on;
        offLabel.hidden = on;
    }
    publishToggle?.addEventListener('change', syncPublishLabels);
    syncPublishLabels();
})();
</script>
