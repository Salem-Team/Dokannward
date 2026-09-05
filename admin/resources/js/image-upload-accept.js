/**
 * Shared product-image accept rules for admin upload pickers.
 * Matches App\Rules\ProductImageFile on the server.
 */

/** @type {ReadonlySet<string>} */
export const IMAGE_EXTENSIONS = new Set([
    // Common web / phone
    'jpg', 'jpeg', 'jpe', 'jfif', 'pjpeg', 'pjp',
    'png', 'apng', 'gif', 'webp', 'avif', 'jxl',
    'bmp', 'dib', 'tif', 'tiff', 'heic', 'heif', 'heics', 'heifs', 'hif',
    'svg', 'svgz', 'ico', 'cur', 'icns',
    // JPEG 2000
    'jp2', 'j2k', 'jpx', 'jpf', 'jpm', 'mj2',
    // Netpbm / legacy
    'xbm', 'xpm', 'pbm', 'pgm', 'ppm', 'pnm', 'ras', 'rgb', 'rgba',
    // Desktop / design
    'psd', 'psb', 'eps', 'epsf', 'ai', 'emf', 'wmf',
    // Other raster
    'pcx', 'tga', 'wbmp', 'qoi', 'exr', 'hdr', 'ktx', 'ktx2', 'dds',
    // Camera RAW
    'cr2', 'cr3', 'nef', 'nrw', 'arw', 'srf', 'sr2', 'orf', 'rw2', 'pef', 'ptx',
    'raf', 'dng', 'raw', '3fr', 'erf', 'mrw', 'rwl', 'x3f', 'iiq', 'kdc', 'mef', 'mos', 'fff',
]);

/** Non-images that sometimes slip through drag-and-drop. */
const BLOCKED_EXTENSIONS = new Set([
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'zip', 'rar', '7z', 'gz', 'tar',
    'mp4', 'mov', 'avi', 'mkv', 'webm', 'mp3', 'wav', 'm4a',
    'txt', 'html', 'htm', 'xml', 'json', 'csv',
    'exe', 'dmg', 'apk', 'php', 'js', 'css', 'bin',
]);

/**
 * File picker accept string: image/* plus explicit extensions for Android /
 * WhatsApp document pickers that ignore image/*.
 */
export const FILE_ACCEPT = [
    'image/*',
    ...[...IMAGE_EXTENSIONS].map((ext) => `.${ext}`),
].join(',');

export function fileExtension(name) {
    const base = String(name || '')
        .trim()
        .replace(/\\/g, '/')
        .split('/')
        .pop() || '';
    const match = base.match(/\.([a-z0-9]+)$/i);
    return match ? match[1].toLowerCase() : '';
}

/** @param {File | Blob | null | undefined} file */
export function isAcceptedImageFile(file) {
    if (!file) return false;

    const type = String(file.type || '').toLowerCase();
    if (type.startsWith('image/')) return true;

    const ext = fileExtension(file.name);
    if (ext && IMAGE_EXTENSIONS.has(ext)) return true;

    // WhatsApp / some mobile exports: octet-stream or empty type with an image name.
    if ((!type || type === 'application/octet-stream') && ext) {
        if (BLOCKED_EXTENSIONS.has(ext)) return false;
        if (IMAGE_EXTENSIONS.has(ext)) return true;
        if (/^[a-z0-9]{2,8}$/.test(ext)) return true;
    }

    return false;
}
