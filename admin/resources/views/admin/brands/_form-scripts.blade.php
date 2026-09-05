<script>
(function () {
    const sourceInput = document.getElementById('logo_source');
    const tabs = document.querySelectorAll('[data-logo-tab]');
    const panelUpload = document.getElementById('logo-panel-upload');
    const panelUrl = document.getElementById('logo-panel-url');
    const fileInput = document.getElementById('logo_file');
    const urlInput = document.getElementById('logo_url');
    const dropzone = document.getElementById('logo-dropzone');
    const emptyState = document.getElementById('logo-dropzone-empty');
    const previewState = document.getElementById('logo-dropzone-preview');
    const previewImg = document.getElementById('logo-preview-img');
    const tileImg = document.getElementById('brand-tile-img');
    const tileFallback = document.getElementById('brand-tile-fallback');
    const tileWordmark = document.getElementById('brand-tile-wordmark');
    const tileName = document.getElementById('brand-tile-name');
    const tileCountry = document.getElementById('brand-tile-country');
    const nameInput = document.getElementById('name_en');
    const countryInput = document.getElementById('country');

    let objectUrl = null;

    function setSource(mode) {
        sourceInput.value = mode;
        tabs.forEach((tab) => tab.classList.toggle('is-active', tab.dataset.logoTab === mode));
        panelUpload.classList.toggle('hidden', mode !== 'upload');
        panelUrl.classList.toggle('hidden', mode !== 'url');
        refreshTile();
    }

    function showPreview(src) {
        if (!src) {
            emptyState.classList.remove('hidden');
            previewState.classList.add('hidden');
            previewImg.removeAttribute('src');
            return;
        }
        previewImg.src = src;
        emptyState.classList.add('hidden');
        previewState.classList.remove('hidden');
    }

    function refreshTile() {
        const name = (nameInput?.value || '').trim() || 'New brand';
        const country = (countryInput?.value || '').trim() || 'Origin pending';
        tileName.textContent = name;
        tileWordmark.textContent = name;
        tileCountry.textContent = country;

        let src = '';
        if (sourceInput.value === 'url') {
            src = (urlInput?.value || '').trim();
        } else if (previewImg?.getAttribute('src')) {
            src = previewImg.getAttribute('src');
        }

        if (src) {
            tileImg.src = src;
            tileImg.classList.remove('hidden');
            tileFallback.classList.add('hidden');
        } else {
            tileImg.classList.add('hidden');
            tileImg.removeAttribute('src');
            tileFallback.classList.remove('hidden');
        }
    }

    function handleFile(file) {
        if (!file || !file.type.startsWith('image/')) return;
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);
        showPreview(objectUrl);
        setSource('upload');
        refreshTile();
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => setSource(tab.dataset.logoTab));
    });

    fileInput?.addEventListener('change', (e) => {
        const file = e.target.files?.[0];
        handleFile(file);
    });

    urlInput?.addEventListener('input', () => {
        if (sourceInput.value === 'url') refreshTile();
    });

    nameInput?.addEventListener('input', refreshTile);
    countryInput?.addEventListener('input', refreshTile);

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

    // Initial sync (edit page may already have a logo URL)
    if (sourceInput.value === 'upload' && previewImg?.getAttribute('src')) {
        showPreview(previewImg.getAttribute('src'));
    }
    refreshTile();
})();
</script>
