/**
 * Unique product QR codes for admin show/edit desks.
 * Encodes the public /scan/{slug} URL with the Dokan Ward mark centered in the code.
 */
import QRCode from 'qrcode';

const QR_INK = '#0a0a0a';
const QR_PAPER = '#ffffff';
const EXPORT_SIZE = 1200;
/** Outer white pad as a fraction of QR width (error correction H tolerates ~30%). */
const LOGO_PAD_RATIO = 0.34;
/** Drawn logo size inside the pad. */
const LOGO_DRAW_RATIO = 0.26;

function setStatus(root, message, isError = false) {
    const el = root.querySelector('[data-qr-status]');
    if (!el) return;
    if (!message) {
        el.hidden = true;
        el.textContent = '';
        el.classList.remove('is-error', 'is-ok');
        return;
    }
    el.hidden = false;
    el.textContent = message;
    el.classList.toggle('is-error', !!isError);
    el.classList.toggle('is-ok', !isError);
}

function loadImage(src) {
    return new Promise((resolve, reject) => {
        if (!src) {
            reject(new Error('Missing logo src'));
            return;
        }
        const img = new Image();
        img.decoding = 'async';
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error(`Failed to load logo: ${src}`));
        img.src = src;
    });
}

/**
 * Paint a white framed pad + Dokan Ward mark in the QR quiet center.
 */
function drawBrandMark(canvas, logo) {
    if (!canvas || !logo) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const size = canvas.width;
    const pad = Math.round(size * LOGO_PAD_RATIO);
    const draw = Math.round(size * LOGO_DRAW_RATIO);
    const cx = size / 2;
    const cy = size / 2;
    const padX = Math.round(cx - pad / 2);
    const padY = Math.round(cy - pad / 2);
    const logoX = Math.round(cx - draw / 2);
    const logoY = Math.round(cy - draw / 2);
    const border = Math.max(2, Math.round(size * 0.006));
    const halo = Math.max(4, Math.round(size * 0.012));

    // Soft white halo so modules don't crowd the mark
    ctx.fillStyle = QR_PAPER;
    ctx.fillRect(padX - halo, padY - halo, pad + halo * 2, pad + halo * 2);

    ctx.fillStyle = QR_PAPER;
    ctx.fillRect(padX, padY, pad, pad);
    ctx.strokeStyle = QR_INK;
    ctx.lineWidth = border;
    ctx.strokeRect(
        padX + border / 2,
        padY + border / 2,
        pad - border,
        pad - border,
    );

    ctx.drawImage(logo, logoX, logoY, draw, draw);
}

async function paintQrWithLogo(canvas, url, logo) {
    const size = canvas.width;
    await QRCode.toCanvas(canvas, url, {
        errorCorrectionLevel: 'H',
        margin: 2,
        width: size,
        color: {
            dark: QR_INK,
            light: QR_PAPER,
        },
    });
    if (logo) {
        drawBrandMark(canvas, logo);
    }
    return canvas;
}

async function paint(root, logo) {
    const canvas = root.querySelector('[data-qr-canvas]');
    const url = root.dataset.qrUrl || '';
    if (!canvas || !url) return null;

    const size = Math.min(320, Math.max(240, canvas.clientWidth || 280));
    canvas.width = size;
    canvas.height = size;

    await paintQrWithLogo(canvas, url, logo);
    return canvas;
}

async function exportPngDataUrl(url, logo) {
    const canvas = document.createElement('canvas');
    canvas.width = EXPORT_SIZE;
    canvas.height = EXPORT_SIZE;
    await paintQrWithLogo(canvas, url, logo);
    return canvas.toDataURL('image/png');
}

function downloadPng(dataUrl, name) {
    const link = document.createElement('a');
    link.download = `dokannward-${name || 'product'}-qr.png`;
    link.href = dataUrl;
    link.click();
}

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function printLabel(root, dataUrl) {
    const title = escapeHtml(root.dataset.qrTitle || 'Dokan Ward product');
    const sku = escapeHtml(root.dataset.qrSku || '');
    const url = escapeHtml(root.dataset.qrUrl || '');
    const win = window.open('', '_blank', 'noopener,noreferrer,width=520,height=720');
    if (!win) {
        setStatus(root, 'Allow pop-ups to print the QR label.', true);
        return;
    }

    win.document.write(`<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Dokan Ward · ${title}</title>
  <style>
    @page { margin: 10mm; size: auto; }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "Helvetica Neue", Arial, sans-serif;
      color: #0a0a0a;
      background: #f7f6f4;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .sheet {
      width: 90mm;
      margin: 8mm auto;
      background: #fff;
      border: 1px solid #d8d8d8;
      overflow: hidden;
    }
    .stripes {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      height: 7mm;
    }
    .stripes span { background: #0a0a0a; }
    .stripes span:nth-child(even) { background: #fff; border-inline: 1px solid #0a0a0a; }
    .inner {
      padding: 8mm 7mm 9mm;
      text-align: center;
    }
    .brand {
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.34em;
      text-transform: uppercase;
      margin: 0 0 2mm;
    }
    .tag {
      display: inline-block;
      margin: 0 0 5mm;
      padding: 1.2mm 2.4mm;
      border: 1px solid #0a0a0a;
      font-size: 8px;
      font-weight: 700;
      letter-spacing: 0.18em;
      text-transform: uppercase;
    }
    .qr-wrap {
      position: relative;
      width: 48mm;
      height: 48mm;
      margin: 0 auto 5mm;
      padding: 2mm;
      border: 1px solid #0a0a0a;
    }
    .qr-wrap img {
      width: 100%;
      height: 100%;
      image-rendering: pixelated;
      display: block;
    }
    h1 {
      margin: 0 0 2mm;
      font-size: 12px;
      line-height: 1.3;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }
    .sku {
      margin: 0 0 4mm;
      font-size: 9px;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: #6b6b6b;
    }
    .rule {
      width: 18mm;
      height: 1px;
      margin: 0 auto 3.5mm;
      background: repeating-linear-gradient(90deg, #0a0a0a 0 2px, transparent 2px 4px);
    }
    .url {
      margin: 0;
      font-size: 7px;
      line-height: 1.4;
      word-break: break-all;
      color: #6b6b6b;
      letter-spacing: 0.02em;
    }
    .foot {
      margin-top: 4mm;
      font-size: 7.5px;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: #6b6b6b;
    }
  </style>
</head>
<body>
  <div class="sheet">
    <div class="stripes" aria-hidden="true">
      <span></span><span></span><span></span><span></span><span></span>
    </div>
    <div class="inner">
      <p class="brand">Dokan Ward</p>
      <span class="tag">Authenticated</span>
      <div class="qr-wrap">
        <img src="${dataUrl}" alt="QR code with Dokan Ward logo" />
      </div>
      <h1>${title}</h1>
      ${sku ? `<p class="sku">${sku}</p>` : ''}
      <div class="rule" aria-hidden="true"></div>
      <p class="url">${url}</p>
      <p class="foot">Scan for dossier</p>
    </div>
  </div>
  <script>window.onload = () => { window.focus(); window.print(); };</script>
</body>
</html>`);
    win.document.close();
}

async function copyLink(root) {
    const url = root.dataset.qrUrl || '';
    const label = root.querySelector('[data-qr-copy-label]');
    try {
        await navigator.clipboard.writeText(url);
        if (label) label.textContent = 'Copied';
        setStatus(root, 'Scan link copied.', false);
        window.setTimeout(() => {
            if (label) label.textContent = 'Copy link';
            setStatus(root, '');
        }, 1800);
    } catch {
        setStatus(root, 'Could not copy the link. Copy it manually from above.', true);
    }
}

async function setupProductQr(root) {
    if (!root || root.dataset.qrReady === '1') return;
    root.dataset.qrReady = '1';

    try {
        const logoSrc = root.dataset.qrLogo || '';
        let logo = null;
        try {
            logo = await loadImage(logoSrc);
        } catch (error) {
            console.warn(error);
        }

        const canvas = await paint(root, logo);
        if (!canvas) return;
        const url = root.dataset.qrUrl || '';

        // Logo is baked into the canvas — hide the decorative HTML seal.
        const seal = root.querySelector('[data-qr-seal]');
        if (seal && logo) {
            seal.hidden = true;
        }

        root.querySelector('[data-qr-download]')?.addEventListener('click', async () => {
            try {
                const dataUrl = await exportPngDataUrl(url, logo);
                downloadPng(dataUrl, root.dataset.qrName || 'product');
                setStatus(root, 'High-resolution QR downloaded.', false);
            } catch (error) {
                console.error(error);
                setStatus(root, 'Download failed. Try again.', true);
            }
        });

        root.querySelector('[data-qr-print]')?.addEventListener('click', async () => {
            try {
                const dataUrl = await exportPngDataUrl(url, logo);
                printLabel(root, dataUrl);
            } catch (error) {
                console.error(error);
                setStatus(root, 'Print label failed. Try again.', true);
            }
        });

        root.querySelector('[data-qr-copy]')?.addEventListener('click', () => {
            void copyLink(root);
        });
    } catch (error) {
        setStatus(root, 'Could not generate QR code. Refresh and try again.', true);
        console.error(error);
    }
}

export function initProductQr(root = document) {
    root.querySelectorAll('[data-product-qr]').forEach((el) => {
        void setupProductQr(el);
    });
}
