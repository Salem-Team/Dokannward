/**
 * Product gallery + per-color photo pickers.
 * Lives in the Vite bundle so Turbo navigations always have the behavior,
 * and files are re-synced onto <input type="file"> right before submit
 * (preview ObjectURLs alone never reach PHP).
 *
 * Main image rules:
 * - Product gallery: first tile = storefront hero (is_primary on save).
 * - Color photos: first tile = color.image when the shopper picks that swatch.
 * Click "Set as Main" (or drag to front) to choose without guessing order.
 *
 * Save uses XHR so large image uploads show a real progress bar.
 */

import { assignFiles, bindDropzone } from './dropzone';
import {
    FILE_ACCEPT,
    isAcceptedImageFile,
} from './image-upload-accept';
import {
    normalizeImageUploadFile,
    optimizeImageForUpload,
    previewObjectUrl,
    uploadFileFingerprint,
} from './image-upload-normalize';
import { clearProductFormDraft, initProductFormDraft } from './product-form-draft';

const DEFAULT_MAX_MB = 2048;
const UPLOAD_CACHE_PREFIX = 'dokannward:staged-uploads:';

function formatBytes(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
    return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`;
}

function isAcceptedImage(file) {
    return isAcceptedImageFile(file);
}

function syncFiles(input, files) {
    if (!input) return false;
    try {
        assignFiles(input, files);
        return input.files?.length === files.length;
    } catch {
        return false;
    }
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

async function postJson(url) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.success === false) {
        throw new Error(data.message || 'Request failed');
    }
    return data;
}

const PRODUCT_MAIN_BADGE_HTML =
    '<span class="product-gallery-tile__badge" title="Shows on the website as the product cover">' +
    '<i class="fas fa-star"></i> Main</span>';

/** @param {string} url */
function makeSetPrimaryButton(url) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'product-gallery-tile__make-main is-always-on';
    btn.setAttribute('data-set-primary', url);
    btn.title = 'Use as website cover image';
    btn.textContent = 'Set as Main';
    return btn;
}

/** Demote every website-main badge/button without reloading the page. */
function clearWebsiteMainState() {
    document.querySelectorAll('[data-saved-product-gallery] .product-gallery-tile.is-main').forEach((tile) => {
        tile.classList.remove('is-main');
        const badge = tile.querySelector('.product-gallery-tile__badge');
        const url = tile.dataset.primaryUrl;
        if (badge && url) {
            badge.replaceWith(makeSetPrimaryButton(url));
        }
    });

    document.querySelectorAll('.color-photos__tile.is-website-main').forEach((tile) => {
        tile.classList.remove('is-website-main');
        const badge = tile.querySelector('.color-photos__main-badge.is-website');
        if (badge) {
            badge.classList.remove('is-website');
            if (tile.classList.contains('is-main')) {
                badge.innerHTML = '<i class="fas fa-star"></i> Color cover';
            }
        }
        const url = tile.dataset.colorMainUrl;
        if (url && !tile.querySelector('.color-photos__make-main')) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'color-photos__make-main is-always-on';
            btn.setAttribute('data-set-color-main', url);
            btn.title = 'Use as this color’s cover and the website Main image';
            btn.textContent = 'Set Main';
            const anchor = tile.querySelector('.color-photos__remove-input, .color-photos__remove');
            if (anchor) {
                tile.insertBefore(btn, anchor);
            } else {
                tile.appendChild(btn);
            }
        }
    });
}

/** @param {Element | null | undefined} tile */
function markProductGalleryTileMain(tile) {
    if (!tile || !(tile instanceof HTMLElement)) return;
    tile.classList.add('is-main');
    const btn = tile.querySelector('.product-gallery-tile__make-main');
    if (btn) {
        btn.replaceWith(
            document.createRange().createContextualFragment(PRODUCT_MAIN_BADGE_HTML),
        );
    }
    const caption = tile.querySelector('.product-gallery-tile__caption');
    if (caption) caption.textContent = 'Website cover';
}

/** @param {Element | null | undefined} tile */
function markColorPhotoTileWebsiteMain(tile) {
    if (!tile || !(tile instanceof HTMLElement)) return;
    tile.classList.add('is-website-main');
    tile.querySelector('.color-photos__make-main')?.remove();

    let badge = tile.querySelector('[data-color-main-badge]');
    if (badge) {
        badge.classList.add('is-website');
        badge.innerHTML = '<i class="fas fa-star"></i> Website Main';
        return;
    }

    badge = document.createElement('span');
    badge.className = 'color-photos__main-badge is-website';
    badge.dataset.colorMainBadge = '';
    badge.innerHTML = '<i class="fas fa-star"></i> Website Main';
    tile.querySelector('img')?.after(badge);
}

/** @param {string} photoId */
function syncWebsiteMainByPhotoId(photoId) {
    if (!photoId) return;
    const galleryTile = document.querySelector(
        `[data-saved-product-gallery] .product-gallery-tile[data-photo-id="${photoId}"]`,
    );
    const colorTile = document.querySelector(
        `.color-photos__tile[data-photo-id="${photoId}"]`,
    );
    if (galleryTile) markProductGalleryTileMain(galleryTile);
    if (colorTile) markColorPhotoTileWebsiteMain(colorTile);
}

/** @param {HTMLButtonElement} btn */
async function handleSetPrimary(btn) {
    const url = btn.getAttribute('data-set-primary');
    if (!url || btn.disabled) return;

    const tile = btn.closest('.product-gallery-tile');
    const photoId = tile?.dataset.photoId || '';

    btn.disabled = true;
    const label = btn.textContent;
    btn.textContent = 'Saving…';

    try {
        await postJson(url);
        clearWebsiteMainState();
        if (tile) markProductGalleryTileMain(tile);
        syncWebsiteMainByPhotoId(photoId);
        window.toast?.show?.('Main image updated', 'success');
    } catch (err) {
        btn.disabled = false;
        btn.textContent = label;
        window.dialog?.alert?.({
            title: 'Could not set main image',
            message: err.message || 'Please try again.',
            tone: 'warning',
        }) || window.alert(err.message || 'Could not set main image');
    }
}

/** @param {HTMLButtonElement} btn */
async function handleSetColorMain(btn) {
    const url = btn.getAttribute('data-set-color-main');
    if (!url || btn.disabled) return;

    const tile = btn.closest('.color-photos__tile');
    const photoId = tile?.dataset.photoId || '';

    btn.disabled = true;
    const label = btn.textContent;
    btn.textContent = '…';

    try {
        await postJson(url);
        clearWebsiteMainState();
        if (tile) markColorPhotoTileWebsiteMain(tile);
        syncWebsiteMainByPhotoId(photoId);
        window.toast?.show?.('Color cover and website Main updated', 'success');
    } catch (err) {
        btn.disabled = false;
        btn.textContent = label;
        window.dialog?.alert?.({
            title: 'Could not set color main',
            message: err.message || 'Please try again.',
            tone: 'warning',
        }) || window.alert(err.message || 'Could not set color main');
    }
}

/** @param {HTMLButtonElement} trigger */
async function handleDeleteProductImage(trigger) {
    const photoId = trigger.getAttribute('data-delete-product-image');
    if (!photoId) return;

    const ok = await (window.dialog?.confirm?.({
        title: 'Delete this image?',
        message: 'This action cannot be undone. The image will be removed from the product gallery.',
        confirmText: 'Delete image',
        tone: 'danger',
        eyebrow: 'Destructive action',
    }) ?? Promise.resolve(false));
    if (!ok) return;

    trigger.disabled = true;
    const tile = trigger.closest('.product-gallery-tile');

    try {
        const response = await fetch(`/admin/products/images/${photoId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'Failed to delete image');
        }
        tile?.remove();
        window.toast?.show?.('Image removed', 'success');
    } catch (err) {
        trigger.disabled = false;
        window.dialog?.alert?.({
            title: 'Delete failed',
            message: err.message || 'Failed to delete image',
            tone: 'warning',
        }) || window.toast?.show?.(err.message || 'Failed to delete image', 'error');
    }
}


function bindUploadFormGuards() {
    if (bindUploadFormGuards.bound) return;
    bindUploadFormGuards.bound = true;

    // Bubble-phase only: never capture-stop file `change`, or the input's own
    // listeners (gallery / color pickers) never see the selection.
    document.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || target.type !== 'file') return;
        // Keep form-level change handlers from treating a file pick as a field edit.
        event.stopPropagation();
    });
}
bindUploadFormGuards.bound = false;

function pendingItemsList(items) {
    return items.filter((item) => !item.uploaded);
}

function createDokanWardMediaApi(getItems, setItems, syncInput, fieldNameDefault) {
    return {
        sync() {
            return syncInput();
        },
        count() {
            return pendingItemsList(getItems()).length;
        },
        pendingCount() {
            return pendingItemsList(getItems()).length;
        },
        pendingBytes() {
            return pendingItemsList(getItems()).reduce((sum, item) => sum + item.file.size, 0);
        },
        inputCount() {
            return pendingItemsList(getItems()).length;
        },
        pendingItems() {
            return pendingItemsList(getItems());
        },
        files() {
            return pendingItemsList(getItems()).map((item) => item.file);
        },
        markUploaded(id) {
            const item = getItems().find((entry) => entry.id === id);
            if (item) item.uploaded = true;
            syncInput();
        },
        markAllUploaded() {
            getItems().forEach((item) => {
                item.uploaded = true;
            });
            syncInput();
        },
        clearUploaded() {
            const kept = [];
            getItems().forEach((item) => {
                if (item.uploaded) URL.revokeObjectURL(item.url);
                else kept.push(item);
            });
            setItems(kept);
            syncInput();
        },
        fieldName() {
            return fieldNameDefault();
        },
    };
}

function readUploadCache(productId) {
    try {
        const raw = sessionStorage.getItem(`${UPLOAD_CACHE_PREFIX}${productId}`);
        return raw ? JSON.parse(raw) : [];
    } catch {
        return [];
    }
}

function rememberStagedUpload(productId, file) {
    const fp = uploadFileFingerprint(file);
    const list = readUploadCache(productId);
    if (!list.includes(fp)) list.push(fp);
    try {
        sessionStorage.setItem(`${UPLOAD_CACHE_PREFIX}${productId}`, JSON.stringify(list));
    } catch {
        /* quota */
    }
}

function clearUploadCache(productId) {
    try {
        sessionStorage.removeItem(`${UPLOAD_CACHE_PREFIX}${productId}`);
    } catch {
        /* ignore */
    }
}

function isStagedOnServer(productId, file) {
    return readUploadCache(productId).includes(uploadFileFingerprint(file));
}

function productIdFromForm(form) {
    if (form?.dataset?.productId) return form.dataset.productId;
    const match = String(form?.action || '').match(/\/products\/([0-9a-f-]{36})\/?$/i);
    return match?.[1] || null;
}

function setupProductGallery(root) {
    if (!root || root.dataset.mediaReady === '1') return;
    root.dataset.mediaReady = '1';

    const dropzone = root.querySelector('[data-product-images-dz]') || root.querySelector('#product-images-dropzone');
    const input = root.querySelector('[data-product-images-input]') || root.querySelector('#product-images-input');
    const grid = root.querySelector('[data-product-images-grid]') || root.querySelector('#product-images-grid');
    const statusEl = root.querySelector('[data-product-images-status]') || root.querySelector('#product-images-status');
    if (!dropzone || !input || !grid) return;

    if (input.getAttribute('accept') !== FILE_ACCEPT) {
        input.setAttribute('accept', FILE_ACCEPT);
    }

    const maxBytes = (Number(root.dataset.maxMb) || DEFAULT_MAX_MB) * 1024 * 1024;
    /** @type {{ id: string, file: File, url: string }[]} */
    let items = [];
    let dragFrom = null;
    let ignoreNextChange = false;

    function setStatus(message, isError) {
        if (!statusEl) return;
        if (!message) {
            statusEl.hidden = true;
            statusEl.textContent = '';
            statusEl.classList.remove('is-error');
            return;
        }
        statusEl.hidden = false;
        statusEl.textContent = message;
        statusEl.classList.toggle('is-error', !!isError);
    }

    function syncInput() {
        ignoreNextChange = true;
        const ok = syncFiles(input, pendingItemsList(items).map((i) => i.file));
        queueMicrotask(() => {
            ignoreNextChange = false;
        });
        return ok;
    }

    function promoteToMain(itemId) {
        const from = items.findIndex((i) => i.id === itemId);
        if (from <= 0) return;
        const [moved] = items.splice(from, 1);
        items.unshift(moved);
        syncInput();
        render();
    }

    function render() {
        grid.innerHTML = '';
        grid.hidden = items.length === 0;

        items.forEach((item, index) => {
            const isMain = index === 0;
            const tile = document.createElement('div');
            tile.className = 'product-gallery-tile' + (isMain ? ' is-main' : '');
            tile.draggable = true;
            tile.dataset.id = item.id;
            tile.setAttribute('role', 'listitem');
            tile.setAttribute(
                'aria-label',
                `${isMain ? 'Main image: ' : 'Image: '}${item.file.name}`,
            );

            tile.innerHTML =
                '<img alt="">' +
                (isMain
                    ? '<span class="product-gallery-tile__badge" title="Shows on the website as the product cover"><i class="fas fa-star"></i> Main</span>'
                    : '<button type="button" class="product-gallery-tile__make-main" title="Use as website cover image">Set as Main</button>') +
                '<span class="product-gallery-tile__handle" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></span>' +
                '<button type="button" class="product-gallery-tile__remove" title="Remove" aria-label="Remove image">' +
                '<i class="fas fa-times"></i></button>' +
                '<span class="product-gallery-tile__caption"></span>';

            tile.querySelector('img').src = item.url;
            tile.querySelector('.product-gallery-tile__caption').textContent =
                `${isMain ? 'Website cover · ' : ''}${item.file.name} · ${formatBytes(item.file.size)}`;

            tile.querySelector('.product-gallery-tile__remove').addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                URL.revokeObjectURL(item.url);
                items = items.filter((i) => i.id !== item.id);
                syncInput();
                render();
            });

            const makeMainBtn = tile.querySelector('.product-gallery-tile__make-main');
            if (makeMainBtn) {
                makeMainBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    promoteToMain(item.id);
                });
            }

            tile.addEventListener('dragstart', (e) => {
                if (e.target.closest('button')) {
                    e.preventDefault();
                    return;
                }
                dragFrom = item.id;
                tile.classList.add('is-dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', item.id);
            });
            tile.addEventListener('dragend', () => {
                dragFrom = null;
                tile.classList.remove('is-dragging');
                grid.querySelectorAll('.is-drop-target').forEach((el) => el.classList.remove('is-drop-target'));
            });
            tile.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                tile.classList.add('is-drop-target');
            });
            tile.addEventListener('dragleave', () => tile.classList.remove('is-drop-target'));
            tile.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                tile.classList.remove('is-drop-target');
                const fromId = e.dataTransfer.getData('text/plain') || dragFrom;
                if (!fromId || fromId === item.id) return;
                const from = items.findIndex((i) => i.id === fromId);
                const to = items.findIndex((i) => i.id === item.id);
                if (from < 0 || to < 0 || from === to) return;
                const [moved] = items.splice(from, 1);
                items.splice(to, 0, moved);
                syncInput();
                render();
            });

            grid.appendChild(tile);
        });

        if (items.length) {
            setStatus(
                items.length === 1
                    ? '1 image · this is the website cover'
                    : `${items.length} images · click “Set as Main” or drag the cover to the front`,
                false,
            );
        } else {
            setStatus('');
        }
    }

    function addFiles(fileList) {
        void ingestFiles(fileList);
    }

    async function ingestFiles(fileList) {
        const incoming = Array.from(fileList || []);
        if (!incoming.length) return;

        setStatus('Optimizing photos for faster upload…', false);
        const productId = root.closest('[data-product-save-form]')?.dataset?.productId || null;
        const rejected = [];
        for (const file of incoming) {
            const normalized = await normalizeImageUploadFile(file);
            let candidate = normalized || file;
            candidate = await optimizeImageForUpload(candidate);

            if (!isAcceptedImage(candidate)) {
                rejected.push(`${file.name} (not an image)`);
                continue;
            }
            if (candidate.size > maxBytes) {
                rejected.push(`${file.name} (over ${Number(root.dataset.maxMb) || DEFAULT_MAX_MB} MB)`);
                continue;
            }
            const dup = items.some(
                (i) =>
                    i.file.name === candidate.name &&
                    i.file.size === candidate.size &&
                    i.file.lastModified === candidate.lastModified,
            );
            if (dup) continue;

            const alreadyStaged = productId && isStagedOnServer(productId, candidate);
            items.push({
                id: `img-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`,
                file: candidate,
                url: previewObjectUrl(candidate),
                uploaded: Boolean(alreadyStaged),
            });
        }

        syncInput();
        render();

        if (rejected.length) {
            setStatus(
                `Skipped: ${rejected.slice(0, 3).join(', ')}${rejected.length > 3 ? '…' : ''}`,
                true,
            );
        }
    }

    root.__dokannwardMedia = createDokanWardMediaApi(
        () => items,
        (next) => {
            items = next;
        },
        syncInput,
        () => input.getAttribute('name') || 'images[]',
    );

    bindDropzone(dropzone, {
        multiple: true,
        onFiles: addFiles,
    });

    input.addEventListener('change', () => {
        if (ignoreNextChange) return;
        addFiles(input.files);
    });
}

function setupSavedGallery(root) {
    if (!root || root.dataset.savedGalleryReady === '1') return;
    root.dataset.savedGalleryReady = '1';

    root.addEventListener('click', (event) => {
        const primaryBtn = event.target.closest('[data-set-primary]');
        if (primaryBtn instanceof HTMLButtonElement) {
            event.preventDefault();
            event.stopPropagation();
            void handleSetPrimary(primaryBtn);
            return;
        }

        const deleteBtn = event.target.closest('[data-delete-product-image]');
        if (deleteBtn instanceof HTMLButtonElement) {
            event.preventDefault();
            event.stopPropagation();
            void handleDeleteProductImage(deleteBtn);
        }
    });
}

function setupColorPhotos(root) {
    if (!root || root.dataset.colorPhotosReady === '1') return;
    root.dataset.colorPhotosReady = '1';

    const dz = root.querySelector('[data-color-photos-dz]');
    const input = root.querySelector('[data-color-photos-input]');
    const preview = root.querySelector('[data-color-photos-preview]');
    const countEl = root.querySelector('[data-color-photos-count]');
    const statusEl = root.querySelector('[data-color-photos-status]');
    const existingGrid = root.querySelector('[data-color-photos-existing]');
    if (!dz || !input || !preview) return;

    if (input.getAttribute('accept') !== FILE_ACCEPT) {
        input.setAttribute('accept', FILE_ACCEPT);
    }

    const maxBytes = DEFAULT_MAX_MB * 1024 * 1024;
    /** @type {{ id: string, file: File, url: string }[]} */
    let items = [];
    let dragFrom = null;
    let ignoreNextChange = false;

    function setStatus(message, isError) {
        if (!statusEl) return;
        if (!message) {
            statusEl.hidden = true;
            statusEl.textContent = '';
            statusEl.classList.remove('is-error');
            return;
        }
        statusEl.hidden = false;
        statusEl.textContent = message;
        statusEl.classList.toggle('is-error', !!isError);
    }

    function keptExisting() {
        return root.querySelectorAll('[data-color-photo-remove]:not(:checked)').length;
    }

    function refreshCount() {
        if (!countEl) return;
        const total = keptExisting() + items.length;
        countEl.textContent = total ? `${total} photo${total === 1 ? '' : 's'}` : 'None yet';
    }

    function refreshExistingMainBadges() {
        if (!existingGrid) return;
        const tiles = [...existingGrid.querySelectorAll('.color-photos__tile')].filter(
            (tile) => !tile.classList.contains('is-removing'),
        );
        tiles.forEach((tile, index) => {
            // CSS shows Main / Set Main from .is-main only (one cover at a time).
            tile.classList.toggle('is-main', index === 0);
        });
    }

    function syncInput() {
        ignoreNextChange = true;
        const ok = syncFiles(input, pendingItemsList(items).map((i) => i.file));
        queueMicrotask(() => {
            ignoreNextChange = false;
        });
        return ok;
    }

    function promoteToMain(itemId) {
        const from = items.findIndex((i) => i.id === itemId);
        if (from <= 0) return;
        const [moved] = items.splice(from, 1);
        items.unshift(moved);
        syncInput();
        render();
    }

    function render() {
        preview.innerHTML = '';
        preview.hidden = items.length === 0;
        const hasExistingKept = keptExisting() > 0;

        items.forEach((item, index) => {
            // New uploads only lead the color when there are no kept existing photos.
            const isMain = !hasExistingKept && index === 0;
            const tile = document.createElement('div');
            tile.className = 'color-photos__tile is-new' + (isMain ? ' is-main' : '');
            tile.draggable = true;
            tile.dataset.id = item.id;
            tile.title = `${item.file.name} · ${formatBytes(item.file.size)}`;
            tile.innerHTML =
                '<img alt="">' +
                (isMain
                    ? '<span class="color-photos__main-badge" data-color-main-badge><i class="fas fa-star"></i> Main</span>'
                    : '<button type="button" class="color-photos__make-main is-always-on" title="Use as this color’s cover and the website Main image">Set Main</button>') +
                '<button type="button" class="color-photos__remove" aria-label="Remove photo">' +
                '<i class="fas fa-times"></i></button>' +
                '<span class="color-photos__flag is-new">New</span>';
            tile.querySelector('img').src = item.url;

            tile.querySelector('.color-photos__remove').addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                URL.revokeObjectURL(item.url);
                items = items.filter((i) => i.id !== item.id);
                syncInput();
                render();
            });

            const makeMainBtn = tile.querySelector('.color-photos__make-main');
            if (makeMainBtn) {
                makeMainBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    promoteToMain(item.id);
                });
            }

            tile.addEventListener('dragstart', (e) => {
                if (e.target.closest('button')) {
                    e.preventDefault();
                    return;
                }
                dragFrom = item.id;
                tile.classList.add('is-dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', item.id);
            });
            tile.addEventListener('dragend', () => {
                dragFrom = null;
                tile.classList.remove('is-dragging');
                preview.querySelectorAll('.is-drop-target').forEach((el) => el.classList.remove('is-drop-target'));
            });
            tile.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                tile.classList.add('is-drop-target');
            });
            tile.addEventListener('dragleave', () => tile.classList.remove('is-drop-target'));
            tile.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                tile.classList.remove('is-drop-target');
                const fromId = e.dataTransfer.getData('text/plain') || dragFrom;
                if (!fromId || fromId === item.id) return;
                const from = items.findIndex((i) => i.id === fromId);
                const to = items.findIndex((i) => i.id === item.id);
                if (from < 0 || to < 0 || from === to) return;
                const [moved] = items.splice(from, 1);
                items.splice(to, 0, moved);
                syncInput();
                render();
            });

            preview.appendChild(tile);
        });

        refreshCount();
        refreshExistingMainBadges();

        if (items.length && !hasExistingKept) {
            setStatus(
                items.length === 1
                    ? 'Main for this color · becomes website cover after save'
                    : 'Drag or click Set Main — after save, Set Main also sets the website cover',
                false,
            );
        } else if (items.length && hasExistingKept) {
            setStatus('New photos append after current ones. Set Main on a saved photo for color + website cover.', false);
        } else if (!items.length) {
            setStatus('');
        }
    }

    function addFiles(fileList) {
        void ingestFiles(fileList);
    }

    async function ingestFiles(fileList) {
        const incoming = Array.from(fileList || []);
        if (!incoming.length) return;

        const rejected = [];
        for (const file of incoming) {
            const normalized = await normalizeImageUploadFile(file);
            let candidate = normalized || file;
            candidate = await optimizeImageForUpload(candidate);

            if (!isAcceptedImage(candidate)) {
                rejected.push(`${file.name} (not an image)`);
                continue;
            }
            if (candidate.size > maxBytes) {
                rejected.push(`${file.name} (over ${Number(root.dataset.maxMb) || DEFAULT_MAX_MB} MB)`);
                continue;
            }
            const dup = items.some(
                (i) =>
                    i.file.name === candidate.name &&
                    i.file.size === candidate.size &&
                    i.file.lastModified === candidate.lastModified,
            );
            if (dup) continue;

            items.push({
                id: `cp-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`,
                file: candidate,
                url: previewObjectUrl(candidate),
                uploaded: false,
            });
        }

        syncInput();
        render();

        if (rejected.length) {
            setStatus(
                `Skipped: ${rejected.slice(0, 3).join(', ')}${rejected.length > 3 ? '…' : ''}`,
                true,
            );
        }
    }

    root.__dokannwardMedia = createDokanWardMediaApi(
        () => items,
        (next) => {
            items = next;
        },
        syncInput,
        () => input.getAttribute('name') || 'color_images[]',
    );

    bindDropzone(dz, { multiple: true, onFiles: addFiles });

    input.addEventListener('change', () => {
        if (ignoreNextChange) return;
        addFiles(input.files);
    });

    root.querySelectorAll('[data-color-photo-remove]').forEach((box) => {
        box.addEventListener('change', () => {
            box.closest('.color-photos__tile')?.classList.toggle('is-removing', box.checked);
            refreshCount();
            refreshExistingMainBadges();
            render();
        });
        box.closest('.color-photos__tile')?.classList.toggle('is-removing', box.checked);
    });

    root.querySelectorAll('[data-set-color-main]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (btn instanceof HTMLButtonElement) void handleSetColorMain(btn);
        });
    });

    refreshCount();
    refreshExistingMainBadges();
}

function scanMediaPickers(root = document) {
    root.querySelectorAll('[data-product-images-uploader], #product-images-uploader').forEach(setupProductGallery);
    root.querySelectorAll('[data-saved-product-gallery]').forEach(setupSavedGallery);
    root.querySelectorAll('[data-color-photos]').forEach(setupColorPhotos);
}

/** @type {XMLHttpRequest | null} */
let activeUpload = null;

function ensureProgressOverlay() {
    let el = document.getElementById('dokannward-upload-progress');
    if (el) return el;

    el = document.createElement('div');
    el.id = 'dokannward-upload-progress';
    el.className = 'dokannward-upload-progress';
    el.hidden = true;
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'dokannward-upload-progress-title');
    el.innerHTML = `
        <div class="dokannward-upload-progress__card">
            <div class="dokannward-upload-progress__icon"><i class="fas fa-cloud-arrow-up"></i></div>
            <h2 id="dokannward-upload-progress-title" class="dokannward-upload-progress__title">Uploading images…</h2>
            <p class="dokannward-upload-progress__detail" data-upload-detail>Preparing…</p>
            <div class="dokannward-upload-progress__track" aria-hidden="true">
                <div class="dokannward-upload-progress__bar" data-upload-bar style="width:0%"></div>
            </div>
            <p class="dokannward-upload-progress__pct" data-upload-pct>0%</p>
        </div>`;
    document.body.appendChild(el);
    return el;
}

function showUploadProgress(pct, detail) {
    const el = ensureProgressOverlay();
    el.hidden = false;
    document.body.classList.add('dokannward-uploading');
    const bar = el.querySelector('[data-upload-bar]');
    const pctEl = el.querySelector('[data-upload-pct]');
    const detailEl = el.querySelector('[data-upload-detail]');
    const clamped = Math.max(0, Math.min(100, Math.round(pct)));
    if (bar) bar.style.width = `${clamped}%`;
    if (pctEl) pctEl.textContent = `${clamped}%`;
    if (detailEl && detail) detailEl.textContent = detail;
}

function hideUploadProgress() {
    const el = document.getElementById('dokannward-upload-progress');
    if (el) el.hidden = true;
    document.body.classList.remove('dokannward-uploading');
}

function formHasPendingFiles(form) {
    const pickers = [...form.querySelectorAll(
        '[data-product-images-uploader], #product-images-uploader, [data-color-photos]',
    )].filter((el) => el.__dokannwardMedia);

    if (pickers.some((el) => (el.__dokannwardMedia.pendingBytes?.() || el.__dokannwardMedia.count?.() || 0) > 0)) {
        return true;
    }

    return [...form.querySelectorAll('input[type="file"]')].some(
        (input) => (input.files?.length || 0) > 0,
    );
}

function flushProductFormFields(form) {
    form.querySelectorAll('[data-rich-editor]').forEach((wrap) => {
        const quill = wrap.__quill;
        const input = wrap.querySelector('textarea');
        if (quill instanceof Object && input instanceof HTMLTextAreaElement) {
            input.value = quill.root?.innerHTML || input.value;
        }
    });
    form.dispatchEvent(new CustomEvent('dokannward:product-save-sync', { bubbles: true }));
}

function productSaveSuccessUrl(form) {
    const fromForm = form?.dataset?.successUrl;
    if (fromForm) return fromForm;
    return `${window.location.origin}/admin/products`;
}

function isProductSaveSuccessUrl(url, form) {
    if (!url) return false;
    try {
        const expected = new URL(productSaveSuccessUrl(form));
        const final = new URL(url, window.location.origin);
        return final.origin === expected.origin && final.pathname === expected.pathname;
    } catch {
        return /\/admin\/products\/?$/.test(String(url).split('?')[0]);
    }
}

function renderProductSaveResponse(html) {
    hideUploadProgress();
    document.open();
    document.write(html);
    document.close();
}

function parseJsonResponse(text) {
    if (!text || text[0] !== '{' && text[0] !== '[') return null;
    try {
        return JSON.parse(text);
    } catch {
        return null;
    }
}

function formatValidationErrors(errors) {
    if (!errors || typeof errors !== 'object') return null;
    const lines = [];
    Object.entries(errors).forEach(([field, msgs]) => {
        const list = Array.isArray(msgs) ? msgs : [String(msgs)];
        list.forEach((msg) => {
            const label = field.replace(/\./g, ' · ').replace(/_/g, ' ');
            lines.push(`${label}: ${msg}`);
        });
    });
    return lines.length ? lines.slice(0, 8).join('\n') : null;
}

function parseValidationFromHtml(html) {
    if (!html) return null;
    try {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const picks = [
            ...doc.querySelectorAll('[data-product-save-form] .text-red-600'),
            ...doc.querySelectorAll('[data-product-save-form] .text-red-400'),
            ...doc.querySelectorAll('.text-red-600'),
            ...doc.querySelectorAll('.text-red-400'),
            ...doc.querySelectorAll('.invalid-feedback'),
            ...doc.querySelectorAll('.alert-danger'),
            ...doc.querySelectorAll('[role="alert"]'),
        ];
        const lines = [...new Set(
            picks.map((el) => el.textContent.replace(/\s+/g, ' ').trim()).filter(Boolean),
        )];
        return lines.length ? lines.slice(0, 8).join('\n') : null;
    } catch {
        return null;
    }
}

function parseFlashErrorFromHtml(html) {
    if (!html) return null;
    try {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const flash = doc.querySelector('[data-flash-error], .flash-error, .alert-error');
        const text = flash?.textContent?.replace(/\s+/g, ' ').trim();
        return text || null;
    } catch {
        return null;
    }
}

function productSaveErrorMessage(xhr) {
    const status = xhr.status;
    const text = xhr.responseText || '';
    const json = parseJsonResponse(text);

    if (json) {
        const validation = formatValidationErrors(json.errors);
        if (validation) return validation;
        if (json.message) return json.message;
    }

    const htmlValidation = parseValidationFromHtml(text);
    if (htmlValidation) return htmlValidation;

    const flashError = parseFlashErrorFromHtml(text);
    if (flashError) return flashError;

    if (status === 413) {
        return 'Upload too large. Use images up to 2 GB each, or upload fewer photos at once.';
    }
    if (status === 419) {
        return 'Session expired. Refresh the page and sign in again.';
    }
    if (status === 429) {
        return 'Too many requests. Wait a moment and try again.';
    }
    if (/MissingAppKeyException|encryption key has been specified/i.test(text)) {
        return 'The server is reloading its configuration. Wait one minute and try again.';
    }
    if (/PostTooLargeException|post_max_size|upload_max_filesize|entity too large/i.test(text)) {
        return 'Upload too large. Use images up to 2 GB each, or upload fewer photos at once.';
    }

    const title = text.match(/<title>([^<]+)<\/title>/i)?.[1]?.trim();
    if (title && !/50[03]|server error|error page/i.test(title)) {
        return title;
    }

    if (status >= 400) {
        return `Save failed (server error ${status}). Please try again.`;
    }
    return 'Save failed. Please try again.';
}

function showProductSaveError(form, xhr) {
    const message = productSaveErrorMessage(xhr);
    const isValidation = xhr.status === 422 || /required|must be|invalid|too large|sale price/i.test(message);
    window.dialog?.alert?.({
        title: isValidation ? 'Fix these fields' : 'Could not save product',
        message,
        tone: isValidation ? 'warning' : 'danger',
        eyebrow: isValidation ? 'Validation' : 'Save failed',
    }) || window.alert(message);

    if (xhr.responseText && xhr.status < 500 && !parseJsonResponse(xhr.responseText)) {
        renderProductSaveResponse(xhr.responseText);
    }
}

function appendPickerFiles(formData, pickers) {
    pickers.forEach((el) => {
        const api = el.__dokannwardMedia;
        if (!api) return;
        const files = typeof api.files === 'function' ? api.files() : [];
        const field = typeof api.fieldName === 'function' ? api.fieldName() : '';
        if (!field || !files.length) return;
        files.forEach((file) => {
            formData.append(field, file, file.name || 'image.jpg');
        });
    });
}

function buildProductFormData(form, pickers) {
    const formData = new FormData(form);

    form.querySelectorAll('input[type="file"][name]').forEach((input) => {
        formData.delete(input.name);
    });

    appendPickerFiles(formData, pickers);

    form.querySelectorAll('input[type="file"][name]').forEach((input) => {
        if (!(input instanceof HTMLInputElement) || !input.files?.length) return;
        const managed = input.closest('[data-product-images-uploader], [data-color-photos]');
        if (managed?.__dokannwardMedia) return;
        Array.from(input.files).forEach((file) => {
            formData.append(input.name, file, file.name || 'image.jpg');
        });
    });

    return formData;
}

function xhrUploadForm(url, formData, options = {}) {
    const { onUploadProgress, retries = 2 } = options;

    return new Promise((resolve, reject) => {
        const attempt = (left) => {
            const xhr = new XMLHttpRequest();
            activeUpload = xhr;
            xhr.open('POST', url);
            xhr.withCredentials = true;
            xhr.timeout = 0;
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json, text/html, application/xhtml+xml');

            xhr.upload.addEventListener('progress', (e) => {
                if (onUploadProgress) onUploadProgress(e);
            });

            xhr.upload.addEventListener('load', () => {
                showUploadProgress(94, 'Processing on server…');
            });

            xhr.addEventListener('load', () => {
                activeUpload = null;
                if (xhr.status >= 200 && xhr.status < 400) {
                    resolve(xhr);
                    return;
                }
                const err = new Error(productSaveErrorMessage(xhr));
                err.xhr = xhr;
                if (left > 0 && xhr.status >= 500) {
                    showUploadProgress(10, 'Retrying upload…');
                    setTimeout(() => attempt(left - 1), 1500);
                    return;
                }
                reject(err);
            });

            xhr.addEventListener('error', () => {
                activeUpload = null;
                if (left > 0) {
                    showUploadProgress(10, 'Connection lost — retrying…');
                    setTimeout(() => attempt(left - 1), 1500);
                    return;
                }
                reject(new Error('Network error while uploading. Check your connection and try again.'));
            });

            xhr.send(formData);
        };

        attempt(retries);
    });
}

async function uploadGalleryImagesStaged(productId, api, onProgress) {
    const pending = api.pendingItems();
    if (!pending.length) return;

    const url = `${window.location.origin}/admin/products/${productId}/images`;
    let done = 0;

    for (const item of pending) {
        if (isStagedOnServer(productId, item.file)) {
            api.markUploaded(item.id);
            done += 1;
            continue;
        }

        const fd = new FormData();
        fd.append('_token', csrfToken());
        fd.append('images[]', item.file, item.file.name || 'image.jpg');

        await xhrUploadForm(url, fd, {
            retries: 2,
            onUploadProgress: (e) => {
                if (!e.lengthComputable || !onProgress) return;
                const fileRatio = e.loaded / Math.max(e.total, 1);
                const overall = Math.min(88, Math.round(((done + fileRatio) / pending.length) * 88));
                onProgress(
                    overall,
                    `Photo ${done + 1} of ${pending.length} · ${formatBytes(e.loaded)} of ${formatBytes(e.total)}`,
                );
            },
        });

        rememberStagedUpload(productId, item.file);
        api.markUploaded(item.id);
        done += 1;
    }

    onProgress?.(90, 'Saving product details…');
}

function handleProductSaveResponse(form, xhr, submitBtns, productId, galleryApi) {
    const status = xhr.status;
    const finalUrl = xhr.responseURL || '';
    const successUrl = productSaveSuccessUrl(form);

    if (status >= 200 && status < 400) {
        if (isProductSaveSuccessUrl(finalUrl, form)) {
            showUploadProgress(100, 'Done');
            clearProductFormDraft(form);
            if (productId) clearUploadCache(productId);
            galleryApi?.clearUploaded?.();
            window.location.assign(finalUrl || successUrl);
            return;
        }

        const flashError = parseFlashErrorFromHtml(xhr.responseText);
        const htmlValidation = parseValidationFromHtml(xhr.responseText);
        const inlineError = flashError || htmlValidation;

        hideUploadProgress();
        submitBtns.forEach((btn) => {
            btn.disabled = false;
        });

        if (inlineError) {
            showProductSaveError(form, xhr);
            return;
        }

        if (xhr.responseText) {
            renderProductSaveResponse(xhr.responseText);
        }
        return;
    }

    hideUploadProgress();
    submitBtns.forEach((btn) => {
        btn.disabled = false;
    });
    showProductSaveError(form, xhr);
}

async function submitProductFormWithProgress(form) {
    if (activeUpload) return;

    flushProductFormFields(form);
    scanMediaPickers(form);

    const pickers = [
        ...form.querySelectorAll(
            '[data-product-images-uploader], #product-images-uploader, [data-color-photos]',
        ),
    ].filter((el) => el.__dokannwardMedia);

    pickers.forEach((el) => {
        el.__dokannwardMedia.sync();
    });

    const productId = productIdFromForm(form);
    const galleryPicker = form.querySelector('[data-product-images-uploader], #product-images-uploader');
    const galleryApi = galleryPicker?.__dokannwardMedia;
    const pendingBytes = pickers.reduce(
        (sum, el) => sum + (el.__dokannwardMedia?.pendingBytes?.() || 0),
        0,
    );
    const hasFiles = pendingBytes > 0;

    const submitBtns = [...form.querySelectorAll('[type="submit"]')];
    submitBtns.forEach((btn) => {
        btn.disabled = true;
    });

    try {
        if (hasFiles) {
            showUploadProgress(2, 'Preparing upload…');
        } else {
            showUploadProgress(8, 'Saving product…');
        }

        if (productId && galleryApi?.pendingCount?.() > 0) {
            await uploadGalleryImagesStaged(productId, galleryApi, (pct, msg) => {
                showUploadProgress(pct, msg);
            });
        }

        const formData = buildProductFormData(form, pickers);
        const stagedGallery = Boolean(productId && galleryApi?.pendingCount?.() === 0);
        const uploadBase = stagedGallery ? 90 : 0;
        const uploadSpan = stagedGallery ? 8 : 92;

        const xhr = await xhrUploadForm(form.action, formData, {
            retries: 2,
            onUploadProgress: (e) => {
                if (!e.lengthComputable) {
                    showUploadProgress(stagedGallery ? 92 : 35, 'Uploading…');
                    return;
                }
                const ratio = e.loaded / Math.max(e.total, 1);
                const pct = Math.min(
                    uploadBase + uploadSpan,
                    Math.round(uploadBase + ratio * uploadSpan),
                );
                showUploadProgress(
                    pct,
                    `Uploading ${formatBytes(e.loaded)} of ${formatBytes(e.total)}`,
                );
            },
        });

        handleProductSaveResponse(form, xhr, submitBtns, productId, galleryApi);
    } catch (err) {
        activeUpload = null;
        hideUploadProgress();
        submitBtns.forEach((btn) => {
            btn.disabled = false;
        });

        const message = err?.xhr
            ? productSaveErrorMessage(err.xhr)
            : (err?.message || 'Save failed. Please try again.');

        window.dialog?.alert?.({
            title: /network error/i.test(message) ? 'Upload interrupted' : 'Could not save product',
            message,
            tone: 'danger',
            eyebrow: /network error/i.test(message) ? 'Network' : 'Save failed',
        }) || window.alert(message);
    }
}

function bindSubmitSync() {
    if (bindSubmitSync.bound) return;
    bindSubmitSync.bound = true;

    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (form.enctype !== 'multipart/form-data') return;

            const isProductForm =
                form.hasAttribute('data-product-save-form') ||
                !!form.querySelector(
                    '[data-product-images-uploader], #product-images-uploader, [data-color-photos]',
                );

            if (!isProductForm) return;

            event.preventDefault();
            event.stopImmediatePropagation();
            void submitProductFormWithProgress(form);
        },
        true,
    );
}

/** Markup helper used by the color-row repeater in Blade. */
export function colorPhotosHtml(inputKey) {
    const inputId = `color-photos-input-${String(inputKey).replace(/[^a-zA-Z0-9_-]/g, '-')}`;
    return `
        <div class="color-photos" data-color-photos>
            <div class="color-photos__head">
                <span class="color-photos__label">Photos for this color</span>
                <span class="color-photos__count" data-color-photos-count>None yet</span>
            </div>
            <p class="color-photos__hint">First photo is the color cover on the website. Click <strong>Set Main</strong> or drag to choose.</p>
            <label for="${inputId}" class="color-photos__dz" data-color-photos-dz tabindex="0"
                aria-label="Add photos for this color">
                <input type="file" id="${inputId}" name="color_images[${inputKey}][]"
                    accept="${FILE_ACCEPT}"
                    multiple class="color-photos__input" data-color-photos-input>
                <i class="fas fa-images"></i>
                <span>Drop photos or <b>browse</b></span>
                <small>Any image type · Set Main on the cover · up to 2&nbsp;GB each</small>
            </label>
            <div class="color-photos__grid" data-color-photos-preview hidden></div>
            <p class="color-photos__status" data-color-photos-status hidden></p>
        </div>`;
}

export function initProductMedia() {
    window.colorPhotosHtml = colorPhotosHtml;
    bindUploadFormGuards();
    bindSubmitSync();
    initProductFormDraft();
    scanMediaPickers();

    document.addEventListener('turbo:load', () => {
        scanMediaPickers();
        initProductFormDraft();
    });
    document.addEventListener('turbo:render', () => scanMediaPickers());

    // Color rows injected by the create/edit repeater.
    if (!initProductMedia.observer) {
        initProductMedia.observer = new MutationObserver(() => scanMediaPickers());
        initProductMedia.observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
        });
    }
}
