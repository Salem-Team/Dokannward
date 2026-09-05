{{-- Full-screen brand route veil — critical CSS paints instantly; this script
     owns ALL navigation loading so we never wait on the Vite bundle.
     data-turbo-permanent keeps this node alive across soft navigations so the
     branded loader stays on screen during slow TTFB (no blank white). --}}
<div id="dokannward-route-veil" class="route-veil route-veil--in" aria-hidden="false" data-phase="in"
    data-turbo-permanent>
    <div class="route-veil__progress" aria-hidden="true">
        <span id="dokannward-route-progress" style="transform: scaleX(0.14)"></span>
    </div>

    <div class="route-veil__curtain" aria-hidden="true">
        <span></span><span></span><span></span><span></span><span></span><span></span><span></span>
    </div>

    <div class="route-veil__panel">
        @include('admin.partials.brand-loader', ['label' => $label ?? 'Loading'])
    </div>
</div>

<script>
(function () {
    'use strict';

    // Guard: Turbo permanent + body script re-eval must not double-bind.
    if (window.__dokannwardRouteVeilBound) {
        if (window.brandRouteVeil) window.brandRouteVeil.hide();
        return;
    }
    window.__dokannwardRouteVeilBound = true;

    var progress = 14;
    var shownAt = performance.now();
    var pulse = null;
    var hideTimer = null;

    function nodes() {
        return {
            veil: document.getElementById('dokannward-route-veil'),
            bar: document.getElementById('dokannward-route-progress'),
        };
    }

    function setBar(p) {
        progress = Math.max(0, Math.min(100, p));
        var bar = nodes().bar;
        if (bar) bar.style.transform = 'scaleX(' + (progress / 100) + ')';
    }

    function startPulse() {
        stopPulse();
        pulse = setInterval(function () {
            if (progress >= 88) return;
            setBar(progress + Math.random() * 6 + 1.5);
        }, 160);
    }

    function stopPulse() {
        if (pulse) { clearInterval(pulse); pulse = null; }
    }

    function restartAnim(node) {
        if (!node) return;
        node.style.animation = 'none';
        // Force reflow so the next animation name actually restarts.
        void node.offsetWidth;
        node.style.animation = '';
    }

    function show() {
        var el = nodes().veil;
        if (!el) return;
        if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
        el.classList.remove('is-hidden', 'route-veil--out');
        el.classList.add('route-veil--in');
        el.dataset.phase = 'in';
        el.setAttribute('aria-hidden', 'false');
        el.style.opacity = '1';

        // After a prior --out (fill-mode: both), the panel can stay at opacity 0
        // on this permanent Turbo node. Reset children so the brand card is visible
        // and continuous loader animations restart cleanly.
        var panel = el.querySelector('.route-veil__panel');
        if (panel) {
            panel.style.opacity = '1';
            panel.style.transform = 'none';
            panel.style.filter = 'none';
            restartAnim(panel);
        }
        el.querySelectorAll('.route-veil__curtain span').forEach(restartAnim);
        el.querySelectorAll(
            '.brand-loader__logo-wrap, .brand-loader__ring, .brand-loader__glow, .brand-loader__stripes span'
        ).forEach(restartAnim);
        var shine = el.querySelector('.brand-loader__shine');
        if (shine) restartAnim(shine);

        shownAt = performance.now();
        setBar(14);
        startPulse();
        try { sessionStorage.setItem('dokannward-nav-veil', '1'); } catch (e) {}
    }

    function hide() {
        var el = nodes().veil;
        if (!el) return;
        if (el.dataset.phase === 'out' || el.classList.contains('is-hidden')) return;
        var wait = Math.max(0, 380 - (performance.now() - shownAt));
        hideTimer = setTimeout(function () {
            stopPulse();
            setBar(100);
            setTimeout(function () {
                el.classList.remove('route-veil--in');
                el.classList.add('route-veil--out');
                el.dataset.phase = 'out';
                el.setAttribute('aria-hidden', 'true');
                setTimeout(function () {
                    el.classList.add('is-hidden');
                    setBar(14);
                    try { sessionStorage.removeItem('dokannward-nav-veil'); } catch (e) {}
                }, 400);
            }, 70);
        }, wait);
    }

    function isInternalAnchor(anchor, event) {
        if (!anchor || anchor.tagName !== 'A') return false;
        if (anchor.hasAttribute('data-no-loader')) return false;
        if (anchor.target && anchor.target !== '_self') return false;
        if (anchor.hasAttribute('download')) return false;
        if (event && (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0)) {
            return false;
        }

        var href = anchor.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0 || href.indexOf('javascript:') === 0) {
            return false;
        }

        try {
            var url = new URL(href, window.location.href);
            if (url.origin !== window.location.origin) return false;
            if (url.pathname === window.location.pathname && url.search === window.location.search) return false;
            return true;
        } catch (e) {
            return false;
        }
    }

    // Instant — never waits for Vite / Turbo.
    document.addEventListener('click', function (event) {
        var anchor = event.target && event.target.closest ? event.target.closest('a') : null;
        if (!isInternalAnchor(anchor, event)) return;
        show();
    }, true);

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || form.tagName !== 'FORM') return;
        if (form.hasAttribute('data-no-loader')) return;
        if (form.target && form.target !== '_self') return;
        // Confirm dialogs intercept the first submit. Do not flash the brand
        // loader until the admin actually confirms (data-confirm-bypass=1).
        if (form.hasAttribute('data-confirm') && form.getAttribute('data-confirm-bypass') !== '1') {
            return;
        }
        show();
    }, true);

    // Soft navigation via Turbo Drive.
    document.addEventListener('turbo:click', function () { show(); });
    document.addEventListener('turbo:visit', function () { show(); });
    document.addEventListener('turbo:submit-start', function () { show(); });
    document.addEventListener('turbo:before-render', function () { show(); });
    document.addEventListener('turbo:load', function () { hide(); });
    document.addEventListener('turbo:fetch-request-error', function () { hide(); });

    // First paint / pending hard-nav from previous page.
    var pending = false;
    try { pending = sessionStorage.getItem('dokannward-nav-veil') === '1'; } catch (e) {}
    if (pending) {
        show();
    } else {
        startPulse();
    }

    if (document.readyState === 'complete') {
        hide();
    } else {
        window.addEventListener('load', hide, { once: true });
        setTimeout(hide, 2800);
    }

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) hide();
    });

    window.brandRouteVeil = { show: show, hide: hide };
})();
</script>
