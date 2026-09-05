<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Accepts any real image upload — all image/* MIME types plus common extensions
 * when the browser/OS leaves mime empty or sends application/octet-stream
 * (WhatsApp document shares, macOS pickers, camera RAW, HEIC, …).
 */
class ProductImageFile implements ValidationRule
{
    /** @var list<string> */
    public const EXTENSIONS = [
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
    ];

    /** @var list<string> */
    private const BLOCKED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'zip', 'rar', '7z', 'gz', 'tar',
        'mp4', 'mov', 'avi', 'mkv', 'webm', 'mp3', 'wav', 'm4a',
        'txt', 'html', 'htm', 'xml', 'json', 'csv',
        'exe', 'dmg', 'apk', 'php', 'js', 'css', 'bin',
    ];

    public static function isImageExtension(string $ext): bool
    {
        $ext = strtolower(trim($ext));

        return $ext !== '' && in_array($ext, self::EXTENSIONS, true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        if (! $value->isValid()) {
            $fail('The image failed to upload. Try again or use a smaller file (under 2 GB).');

            return;
        }

        $mime = strtolower((string) ($value->getMimeType() ?: ''));
        $ext = $this->extensionOf($value);

        if ($mime !== '' && str_starts_with($mime, 'image/')) {
            return;
        }

        if ($this->hasImagePayload($value)) {
            return;
        }

        if (self::isImageExtension($ext)) {
            return;
        }

        if (
            ($mime === '' || $mime === 'application/octet-stream')
            && $ext !== ''
            && ! in_array($ext, self::BLOCKED_EXTENSIONS, true)
            && preg_match('/^[a-z0-9]{2,8}$/', $ext)
        ) {
            // Unknown image extension from a mobile picker — allow unless blocked.
            return;
        }

        $fail('Each product file must be an image. WhatsApp photos and all common image formats are fine.');
    }

    private function hasImagePayload(UploadedFile $file): bool
    {
        $path = $file->getRealPath();
        if (! is_string($path) || $path === '') {
            return false;
        }

        $info = @getimagesize($path);
        if (is_array($info) && ! empty($info['mime']) && str_starts_with((string) $info['mime'], 'image/')) {
            return true;
        }

        $detected = @mime_content_type($path);

        return is_string($detected) && str_starts_with($detected, 'image/');
    }

    /**
     * Last path extension, resilient to "WhatsApp Image … 9.17.53 PM (1).jpeg".
     */
    private function extensionOf(UploadedFile $file): string
    {
        $fromClient = strtolower(trim((string) $file->getClientOriginalExtension()));
        if ($fromClient !== '' && ! str_contains($fromClient, ' ') && strlen($fromClient) <= 8) {
            return $fromClient;
        }

        $name = str_replace('\\', '/', (string) $file->getClientOriginalName());
        $base = basename($name);
        if (preg_match('/\.([a-z0-9]+)$/i', $base, $match)) {
            return strtolower($match[1]);
        }

        return '';
    }
}
