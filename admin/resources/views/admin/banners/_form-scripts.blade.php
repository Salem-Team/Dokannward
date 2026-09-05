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
    const titleInput = document.getElementById('title');
    const subtitleInput = document.getElementById('subtitle');
    const buttonTextInput = document.getElementById('button_text');
    const positionInput = document.getElementById('position');
    const liveImg = document.getElementById('banner-preview-img');
    const liveFallback = document.getElementById('banner-preview-fallback');
    const liveTitle = document.getElementById('banner-preview-title');
    const liveSubtitle = document.getElementById('banner-preview-subtitle');
    const liveCta = document.getElementById('banner-preview-cta');
    const liveMeta = document.getElementById('banner-preview-meta');
    const publishToggle = document.getElementById('is_active');

    let objectUrl = null;
    let urlTimer = null;
    let loadToken = 0;
    const MAX_BYTES = 5 * 1024 * 1024;

    function formatBytes(bytes) {
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

    function setClearVisible(on) {
        clearBtn?.classList.toggle('hidden', !on);
    }

    function setSource(mode) {
        sourceInput.value = mode;
        tabs.forEach((tab) => tab.classList.toggle('is-active', tab.dataset.imageTab === mode));
        panelUpload?.classList.toggle('hidden', mode !== 'upload');
        panelUrl?.classList.toggle('hidden', mode !== 'url');
        if (removeInput) removeInput.value = '0';
        refreshLive();
    }

    function showDropzonePreview(src, metaText) {
        if (!src) {
            emptyState?.classList.remove('hidden');
            if (emptyState) emptyState.hidden = false;
            previewState?.classList.add('hidden');
            if (previewState) previewState.hidden = true;
            previewImg?.removeAttribute('src');
            if (previewMeta) {
                previewMeta.hidden = true;
                previewMeta.textContent = '';
            }
            return;
        }
        if (previewImg) previewImg.src = src;
        emptyState?.classList.add('hidden');
        if (emptyState) emptyState.hidden = true;
        previewState?.classList.remove('hidden');
        if (previewState) previewState.hidden = false;
        if (previewMeta) {
            if (metaText) {
                previewMeta.hidden = false;
                previewMeta.textContent = metaText;
            } else {
                previewMeta.hidden = true;
            }
        }
    }

    function slotLabel(pos) {
        const n = Number(pos);
        if (!Number.isFinite(n) || n <= 0) return 'Homepage · Primary CTA';
        if (n === 1) return 'Homepage · Secondary CTA';
        return 'Extra · after primary slots when earlier ones are off';
    }

    function resolvePreviewSrc() {
        if (removeInput?.value === '1') return '';
        if (sourceInput.value === 'url') return (urlInput?.value || '').trim();
        const attr = (previewImg?.getAttribute('src') || '').trim();
        if (attr) return attr;
        // Property fallback for JS-assigned data/blob URLs.
        const prop = String(previewImg?.currentSrc || previewImg?.src || '').trim();
        if (!prop || prop === window.location.href) return '';
        return prop;
    }

    function setLiveImage(src) {
        if (src) {
            if (liveImg) {
                liveImg.src = src;
                liveImg.classList.remove('hidden');
                liveImg.hidden = false;
            }
            if (liveFallback) {
                liveFallback.classList.add('hidden');
                liveFallback.hidden = true;
            }
            return;
        }
        if (liveImg) {
            liveImg.classList.add('hidden');
            liveImg.hidden = true;
            liveImg.removeAttribute('src');
        }
        if (liveFallback) {
            liveFallback.classList.remove('hidden');
            liveFallback.hidden = false;
        }
    }

    function refreshLive() {
        const title = (titleInput?.value || '').trim() || 'Banner title';
        const subtitle = (subtitleInput?.value || '').trim() || 'Supporting line appears here.';
        const cta = (buttonTextInput?.value || '').trim() || 'Shop now';
        if (liveTitle) liveTitle.textContent = title;
        if (liveSubtitle) liveSubtitle.textContent = subtitle;
        if (liveCta) liveCta.textContent = cta;
        if (liveMeta) liveMeta.textContent = slotLabel(positionInput?.value);
        setLiveImage(resolvePreviewSrc());
    }

    function decodeImage(src) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = () => reject(new Error('load failed'));
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
            reader.onerror = () => reject(new Error('read failed'));
            reader.readAsDataURL(file);
        });
    }

    async function handleFile(file) {
        if (!file) return;
        if (!file.type.startsWith('image/')) {
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
        setProgress(6, 'Reading image…');

        try {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
            const src = await readFileWithProgress(file, (pct) => {
                if (token !== loadToken) return;
                setProgress(Math.max(8, pct * 0.8), 'Loading image…');
            });
            if (token !== loadToken) return;
            await decodeImage(src);
            if (token !== loadToken) return;
            showDropzonePreview(src, `${file.name} · ${formatBytes(file.size)}`);
            setClearVisible(true);
            setProgress(100, 'Preview ready');
            refreshLive();
            window.setTimeout(() => {
                if (token === loadToken) hideProgress();
            }, 260);
        } catch (_) {
            if (token !== loadToken) return;
            hideProgress();
            setProgress(0, 'Could not preview this file');
            window.setTimeout(hideProgress, 1000);
        }
    }

    async function handleUrl(raw) {
        const src = String(raw || '').trim();
        if (!src) {
            setUrlStatus('');
            refreshLive();
            return;
        }
        const token = ++loadToken;
        if (removeInput) removeInput.value = '0';
        setUrlStatus('Checking image…');
        setProgress(20, 'Fetching preview…');
        try {
            await decodeImage(src);
            if (token !== loadToken) return;
            showDropzonePreview(src, 'Remote / path image');
            setClearVisible(true);
            setUrlStatus('Preview ready', 'ok');
            setProgress(100, 'Preview ready');
            refreshLive();
            window.setTimeout(() => {
                if (token === loadToken) hideProgress();
            }, 240);
        } catch (_) {
            if (token !== loadToken) return;
            hideProgress();
            setUrlStatus('Could not load this image. Check the URL or path.', 'error');
            refreshLive();
        }
    }

    function clearImage() {
        loadToken += 1;
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;
        if (fileInput) fileInput.value = '';
        if (urlInput) urlInput.value = '';
        if (removeInput) removeInput.value = '1';
        hideProgress();
        setUrlStatus('');
        showDropzonePreview('');
        setClearVisible(false);
        refreshLive();
    }

    tabs.forEach((tab) => tab.addEventListener('click', () => setSource(tab.dataset.imageTab)));
    fileInput?.addEventListener('change', (e) => handleFile(e.target.files?.[0]));
    urlInput?.addEventListener('input', () => {
        if (sourceInput.value !== 'url') setSource('url');
        window.clearTimeout(urlTimer);
        urlTimer = window.setTimeout(() => handleUrl(urlInput.value), 400);
    });
    clearBtn?.addEventListener('click', clearImage);
    ;[titleInput, subtitleInput, buttonTextInput, positionInput].forEach((el) => {
        el?.addEventListener('input', refreshLive);
    });

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

    function syncPublishLabels() {
        const on = publishToggle?.checked;
        document.querySelector('.studio-visibility__state[data-on]')?.toggleAttribute('hidden', !on);
        document.querySelector('.studio-visibility__state[data-off]')?.toggleAttribute('hidden', !!on);
    }
    publishToggle?.addEventListener('change', syncPublishLabels);
    syncPublishLabels();

    if (sourceInput.value === 'upload' && (previewImg?.getAttribute('src') || '').trim()) {
        showDropzonePreview(previewImg.getAttribute('src'));
        setClearVisible(true);
    } else if (sourceInput.value === 'url' && (urlInput?.value || '').trim()) {
        handleUrl(urlInput.value);
    }
    refreshLive();
})();
</script>
