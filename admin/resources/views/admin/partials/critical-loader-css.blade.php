{{--
  Critical first-paint CSS for the Dokan Ward brand route veil.
  Inlined so the full branded loader paints BEFORE Vite CSS / CDNs arrive —
  continuous motion keeps the wait feeling alive, not frozen.
--}}
<style id="dokannward-critical-loader">
:root {
  --zl-ink: #0a0a0a;
  --zl-ease: cubic-bezier(0.22, 1, 0.36, 1);
  --zl-lux: cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes zl-float {
  0%, 100% { transform: rotateY(-10deg) rotateX(5deg) translateY(0) scale(1); }
  50% { transform: rotateY(10deg) rotateX(-3deg) translateY(-10px) scale(1.03); }
}
@keyframes zl-ring {
  0% { opacity: 0.6; transform: scale(0.88); }
  70% { opacity: 0; transform: scale(1.32); }
  100% { opacity: 0; transform: scale(1.32); }
}
@keyframes zl-glow {
  0%, 100% { opacity: 0.55; transform: scale(0.92); }
  50% { opacity: 1; transform: scale(1.08); }
}
@keyframes zl-stripe-in {
  from { transform: scaleY(0); opacity: 0; }
  to { transform: scaleY(1); opacity: 1; }
}
@keyframes zl-eq {
  0%, 100% { transform: scaleY(0.45); }
  50% { transform: scaleY(1); }
}
@keyframes zl-shine {
  0% { transform: translateX(-130%) skewX(-18deg); opacity: 0; }
  30% { opacity: 0.7; }
  100% { transform: translateX(160%) skewX(-18deg); opacity: 0; }
}
@keyframes zl-drift {
  from { background-position: 0 0; }
  to { background-position: 28px 0; }
}
@keyframes zl-progress-shimmer {
  0% { background-position: 0% 0; }
  100% { background-position: 200% 0; }
}
@keyframes zl-panel-breathe {
  0%, 100% { box-shadow: 0 24px 60px rgb(0 0 0 / 0.08), 0 2px 8px rgb(0 0 0 / 0.04); }
  50% { box-shadow: 0 28px 72px rgb(0 0 0 / 0.14), 0 4px 14px rgb(0 0 0 / 0.06); }
}
/* Veil itself stays fully opaque on enter — only the brand panel animates in. */
@keyframes zl-veil-out { from { opacity: 1; } to { opacity: 0; } }
@keyframes zl-curtain-in { from { transform: translateY(-110%); } to { transform: translateY(0); } }
@keyframes zl-curtain-out { from { transform: translateY(0); } to { transform: translateY(-110%); } }
@keyframes zl-panel-in {
  from { opacity: 0; transform: translateY(18px) scale(0.96); filter: blur(8px); }
  to { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); }
}

#dokannward-route-veil,
.route-veil {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: grid;
  place-items: center;
  pointer-events: auto;
  opacity: 1;
  background: #f7f7f5;
}
.dark #dokannward-route-veil,
.dark .route-veil {
  background: #0a0a0a;
}
.route-veil--in { opacity: 1; pointer-events: auto; }
.route-veil--out { animation: zl-veil-out 0.45s var(--zl-ease) both; pointer-events: none; }
.route-veil.is-hidden { display: none !important; }

.route-veil__progress {
  position: absolute; top: 0; left: 0; right: 0; height: 2px;
  overflow: hidden; background: rgb(0 0 0 / 0.06); z-index: 2;
}
.dark .route-veil__progress { background: rgb(255 255 255 / 0.08); }
.route-veil__progress span {
  display: block; height: 100%; width: 100%;
  transform-origin: left center;
  background: linear-gradient(90deg, rgb(0 0 0 / 0.2), var(--zl-ink) 40%, rgb(0 0 0 / 0.15), var(--zl-ink) 70%, rgb(0 0 0 / 0.25));
  background-size: 200% 100%;
  transition: transform 0.35s var(--zl-ease);
  animation: zl-progress-shimmer 1.6s linear infinite;
}
.dark .route-veil__progress span {
  background: linear-gradient(90deg, rgb(255 255 255 / 0.2), #fff 40%, rgb(255 255 255 / 0.15), #fff 70%, rgb(255 255 255 / 0.25));
  background-size: 200% 100%;
}

.route-veil__curtain {
  position: absolute; inset: 0; display: grid;
  grid-template-columns: repeat(7, 1fr); overflow: hidden; z-index: 0;
}
.route-veil__curtain span {
  background-image: repeating-linear-gradient(115deg, rgb(0 0 0 / 0.055) 0 9px, transparent 9px 20px);
  background-size: 28px 28px;
  transform: translateY(0);
  animation:
    zl-curtain-in 0.55s var(--zl-ease) both,
    zl-drift 4.5s linear infinite;
}
.dark .route-veil__curtain span {
  background-image: repeating-linear-gradient(115deg, rgb(255 255 255 / 0.05) 0 9px, transparent 9px 20px);
  background-size: 28px 28px;
}
.route-veil__curtain span:nth-child(1) { animation-delay: 0ms, 0s; }
.route-veil__curtain span:nth-child(2) { animation-delay: 35ms, -0.4s; }
.route-veil__curtain span:nth-child(3) { animation-delay: 70ms, -0.8s; }
.route-veil__curtain span:nth-child(4) { animation-delay: 105ms, -1.2s; }
.route-veil__curtain span:nth-child(5) { animation-delay: 140ms, -1.6s; }
.route-veil__curtain span:nth-child(6) { animation-delay: 175ms, -2s; }
.route-veil__curtain span:nth-child(7) { animation-delay: 210ms, -2.4s; }
.route-veil--out .route-veil__curtain span {
  animation: zl-curtain-out 0.45s var(--zl-ease) both;
}

.route-veil__panel {
  position: relative; z-index: 1;
  padding: 2.1rem 2.6rem 1.85rem;
  border: 1px solid rgb(0 0 0 / 0.08);
  background: #fff;
  box-shadow: 0 24px 60px rgb(0 0 0 / 0.08), 0 2px 8px rgb(0 0 0 / 0.04);
  border-radius: 2px;
  min-width: min(92vw, 320px);
  animation:
    zl-panel-in 0.55s var(--zl-ease) 0.08s both,
    zl-panel-breathe 3.2s var(--zl-ease) 0.7s infinite;
}
.dark .route-veil__panel {
  border-color: rgb(255 255 255 / 0.1);
  background: rgb(20 20 20 / 0.96);
  box-shadow: 0 24px 60px rgb(0 0 0 / 0.45);
}
.route-veil--out .route-veil__panel { animation: zl-veil-out 0.35s ease both; }

.brand-loader {
  position: relative; display: flex; flex-direction: column;
  align-items: center; gap: 1.35rem; perspective: 900px;
}
.brand-loader__stage {
  position: relative; width: 240px; height: 100px;
  display: grid; place-items: center; transform-style: preserve-3d;
}
.brand-loader__glow {
  position: absolute; inset: 18% 6%; border-radius: 999px;
  background: radial-gradient(circle, rgb(0 0 0 / 0.14), transparent 70%);
  filter: blur(10px);
  animation: zl-glow 2.4s var(--zl-ease) infinite;
}
.dark .brand-loader__glow {
  background: radial-gradient(circle, rgb(255 255 255 / 0.16), transparent 70%);
}
.brand-loader__ring {
  position: absolute; inset: 8% 0; border-radius: 999px;
  border: 1px solid rgb(0 0 0 / 0.16);
  animation: zl-ring 1.7s var(--zl-lux) infinite;
}
.dark .brand-loader__ring { border-color: rgb(255 255 255 / 0.2); }
.brand-loader__ring--delayed { animation-delay: 0.55s; border-color: rgb(0 0 0 / 0.08); }
.brand-loader__logo-wrap {
  position: relative; width: 190px; height: 48px; transform-style: preserve-3d;
  animation: zl-float 2.6s var(--zl-ease) infinite;
  filter: drop-shadow(0 14px 22px rgb(0 0 0 / 0.14));
}
.brand-loader__logo { width: 100%; height: 100%; object-fit: contain; }
.dark .brand-loader__logo { filter: none; }
.brand-loader__shine { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
.brand-loader__shine::before {
  content: ""; position: absolute; top: -10%; bottom: -10%; left: 0; width: 42%;
  background: linear-gradient(90deg, transparent, rgb(255 255 255 / 0.7), transparent);
  animation: zl-shine 2s var(--zl-ease) infinite;
}
.brand-loader__stripes { display: flex; gap: 5px; align-items: flex-end; height: 16px; }
.brand-loader__stripes span {
  display: block; width: 3px; height: 100%; background: var(--zl-ink);
  transform-origin: bottom center;
  transform: scaleY(0);
  animation:
    zl-stripe-in 0.55s var(--zl-ease) both,
    zl-eq 0.9s ease-in-out infinite;
}
.dark .brand-loader__stripes span { background: #fff; }
.brand-loader__stripes span:nth-child(1) { height: 8px;  animation-delay: 0.12s, 0.12s; }
.brand-loader__stripes span:nth-child(2) { height: 12px; animation-delay: 0.18s, 0.28s; }
.brand-loader__stripes span:nth-child(3) { height: 16px; animation-delay: 0.24s, 0.05s; }
.brand-loader__stripes span:nth-child(4) { height: 11px; animation-delay: 0.30s, 0.42s; }
.brand-loader__stripes span:nth-child(5) { height: 7px;  animation-delay: 0.36s, 0.18s; }
.sr-only {
  position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
  overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
}
@media (prefers-reduced-motion: reduce) {
  .brand-loader__logo-wrap, .brand-loader__ring, .brand-loader__glow,
  .brand-loader__shine::before, .brand-loader__stripes span,
  .route-veil, .route-veil__curtain span, .route-veil__panel,
  .route-veil__progress span {
    animation: none !important;
  }
  .brand-loader__stripes span { opacity: 1; transform: none; }
  .route-veil__curtain span { transform: none; }
}
</style>
