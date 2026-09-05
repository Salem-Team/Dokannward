/**
 * WhatsApp and some mobile exports often ship JPEG bytes under a .png name
 * (or with an empty / octet-stream MIME). Browsers then fail gallery previews
 * and may attach the wrong type on save. Sniff magic bytes and rebuild File.
 */

/** @typedef {{ mime: string, ext: string }} ImageFormat */

/** @returns {ImageFormat | null} */
export function sniffImageFormat(bytes) {
    if (!bytes || bytes.length < 3) return null;

    if (bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) {
        return { mime: 'image/jpeg', ext: 'jpg' };
    }
    if (
        bytes.length >= 8 &&
        bytes[0] === 0x89 &&
        bytes[1] === 0x50 &&
        bytes[2] === 0x4e &&
        bytes[3] === 0x47
    ) {
        return { mime: 'image/png', ext: 'png' };
    }
    if (bytes[0] === 0x47 && bytes[1] === 0x49 && bytes[2] === 0x46) {
        return { mime: 'image/gif', ext: 'gif' };
    }
    if (
        bytes.length >= 12 &&
        bytes[0] === 0x52 &&
        bytes[1] === 0x49 &&
        bytes[2] === 0x46 &&
        bytes[3] === 0x46 &&
        bytes[8] === 0x57 &&
        bytes[9] === 0x45 &&
        bytes[10] === 0x42 &&
        bytes[11] === 0x50
    ) {
        return { mime: 'image/webp', ext: 'webp' };
    }
    if (bytes[0] === 0x42 && bytes[1] === 0x4d) {
        return { mime: 'image/bmp', ext: 'bmp' };
    }
    if (
        (bytes[0] === 0x49 && bytes[1] === 0x49) ||
        (bytes[0] === 0x4d && bytes[1] === 0x4d)
    ) {
        return { mime: 'image/tiff', ext: 'tiff' };
    }
    if (bytes[0] === 0x00 && bytes[1] === 0x00 && bytes[2] === 0x01 && bytes[3] === 0x00) {
        return { mime: 'image/x-icon', ext: 'ico' };
    }
    if (
        bytes.length >= 12 &&
        bytes[4] === 0x66 &&
        bytes[5] === 0x74 &&
        bytes[6] === 0x79 &&
        bytes[7] === 0x70
    ) {
        const brand = String.fromCharCode(bytes[8], bytes[9], bytes[10], bytes[11]).toLowerCase();
        if (brand.startsWith('avif') || brand.startsWith('avis')) {
            return { mime: 'image/avif', ext: 'avif' };
        }
        if (brand.startsWith('heic') || brand.startsWith('heix') || brand.startsWith('mif1')) {
            return { mime: 'image/heic', ext: 'heic' };
        }
    }
    if (
        bytes.length >= 12 &&
        bytes[0] === 0x00 &&
        bytes[1] === 0x00 &&
        bytes[2] === 0x00 &&
        bytes[3] === 0x0c &&
        bytes[4] === 0x4a &&
        bytes[5] === 0x58 &&
        bytes[6] === 0x4c &&
        bytes[7] === 0x20
    ) {
        return { mime: 'image/jxl', ext: 'jxl' };
    }

    return null;
}

export function fileExtension(name) {
    const base = String(name || '')
        .trim()
        .replace(/\\/g, '/')
        .split('/')
        .pop() || '';
    const match = base.match(/\.([a-z0-9]+)$/i);
    return match ? match[1].toLowerCase() : '';
}

function nameWithExtension(name, ext) {
    const trimmed = String(name || '').trim() || 'image';
    const current = fileExtension(trimmed);
    if (!current) return `${trimmed}.${ext}`;
    if (current === ext) return trimmed;
    return trimmed.replace(/\.[^.]+$/i, `.${ext}`);
}

/**
 * @param {File} file
 * @returns {Promise<File | null>} null when bytes are not a known raster image
 */
export async function normalizeImageUploadFile(file) {
    if (!file || typeof file.slice !== 'function') return null;

    let head;
    try {
        head = new Uint8Array(await file.slice(0, 16).arrayBuffer());
    } catch {
        return null;
    }

    const format = sniffImageFormat(head);
    if (!format) return null;

    const ext = fileExtension(file.name);
    const type = String(file.type || '').toLowerCase();
    const typeOk =
        !type ||
        type === 'application/octet-stream' ||
        type === format.mime;
    const extOk = !ext || ext === format.ext;

    if (typeOk && extOk) {
        if (!type || type === 'application/octet-stream') {
            return new File([file], nameWithExtension(file.name, format.ext), {
                type: format.mime,
                lastModified: file.lastModified,
            });
        }
        return file;
    }

    return new File([file], nameWithExtension(file.name, format.ext), {
        type: format.mime,
        lastModified: file.lastModified,
    });
}

/** Blob URL that renders even when the source File had a lying MIME type. */
export function previewObjectUrl(file) {
    return URL.createObjectURL(new Blob([file], { type: file.type || undefined }));
}

/**
 * Downscale large phone photos before upload so saves finish faster and
 * survive flaky mobile networks. Skips GIF/SVG and files already small.
 *
 * @param {File} file
 * @param {{ maxDimension?: number, skipBelowBytes?: number, quality?: number }} [opts]
 * @returns {Promise<File>}
 */
export async function optimizeImageForUpload(file, opts = {}) {
    const maxDimension = opts.maxDimension ?? 2048;
    const skipBelowBytes = opts.skipBelowBytes ?? 320 * 1024;
    const quality = opts.quality ?? 0.86;

    const normalized = await normalizeImageUploadFile(file);
    const candidate = normalized || file;
    if (!candidate?.size) return candidate;

    const type = String(candidate.type || '').toLowerCase();
    if (type === 'image/gif' || type === 'image/svg+xml') {
        return candidate;
    }
    if (candidate.size <= skipBelowBytes) {
        return candidate;
    }
    if (typeof createImageBitmap !== 'function') {
        return candidate;
    }

    try {
        const bitmap = await createImageBitmap(candidate);
        const longest = Math.max(bitmap.width, bitmap.height);
        const scale = longest > maxDimension ? maxDimension / longest : 1;
        const width = Math.max(1, Math.round(bitmap.width * scale));
        const height = Math.max(1, Math.round(bitmap.height * scale));
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            bitmap.close?.();
            return candidate;
        }
        ctx.drawImage(bitmap, 0, 0, width, height);
        bitmap.close?.();

        const blob = await new Promise((resolve) => {
            canvas.toBlob(resolve, 'image/jpeg', quality);
        });
        if (!blob || blob.size >= candidate.size * 0.97) {
            return candidate;
        }

        return new File([blob], nameWithExtension(candidate.name, 'jpg'), {
            type: 'image/jpeg',
            lastModified: candidate.lastModified,
        });
    } catch {
        return candidate;
    }
}

/** Stable key for dedupe / upload cache (name + bytes + mtime). */
export function uploadFileFingerprint(file) {
    if (!file) return '';
    return `${file.name}|${file.size}|${file.lastModified}`;
}
