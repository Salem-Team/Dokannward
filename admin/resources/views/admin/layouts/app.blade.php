<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"
    x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }"
    x-init="
        if (localStorage.getItem('darkMode') === null) localStorage.setItem('darkMode', 'false');
        $watch('darkMode', val => {
            localStorage.setItem('darkMode', val ? 'true' : 'false');
            document.dispatchEvent(new CustomEvent('admin:theme-change'));
        })
    "
    :class="{ 'dark': darkMode }">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('admin.dashboard')) — {{ $brandDisplayName ?? config('app.name', 'Default Company') }}</title>

    @include('admin.partials.favicon')

    {{-- Full branded loader paints before any external CSS/JS --}}
    @include('admin.partials.critical-loader-css')
    <link rel="preload" href="{{ ($brandLogoUrl ?? asset('images/brand-logo.png')) }}" as="image" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Cabin:wght@400;500;600;700&family=Merriweather+Sans:wght@400;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Cabin:wght@400;500;600;700&family=Merriweather+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    </noscript>

    @vite(['resources/css/app.css', 'resources/js/admin.js'])

    <style>
        .turbo-progress-bar { display: none !important; visibility: hidden !important; }
        :root { {!! $brandCssVariables ?? '' !!} }
    </style>

    {{-- Font Awesome kept deferred; Alpine/Turbo ship from the Vite bundle above. --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    </noscript>

    @if (app()->getLocale() === 'ar')
        <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;600&display=swap"
            rel="stylesheet">
    @endif

    @stack('styles')
</head>

<body
    class="bg-zibra-paper dark:bg-gray-900 transition-colors duration-200 font-body {{ app()->getLocale() === 'ar' ? 'lang-ar' : '' }}">

    @include('admin.partials.route-veil', ['label' => 'Loading'])

    <div x-data="adminShell()"
        x-cloak
        class="admin-shell flex flex-col h-screen overflow-hidden"
        :class="{ 'is-drawer-open': sidebarOpen }">

        @include('admin.partials.sidebar')

        <div class="admin-shell__stage flex-1 flex flex-col overflow-hidden min-w-0">
            @include('admin.partials.topbar')

            <main class="flex-1 overflow-y-auto admin-shell-main">
                @if (isset($breadcrumbs))
                    <nav class="admin-breadcrumb mb-4 sm:mb-6" aria-label="Breadcrumb">
                        <ol class="admin-breadcrumb__list">
                            @foreach ($breadcrumbs as $index => $crumb)
                                @if ($index > 0)
                                    <li><i class="fas fa-chevron-right text-xs"></i></li>
                                @endif
                                <li>
                                    @if (isset($crumb['url']))
                                        <a href="{{ $crumb['url'] }}"
                                            class="hover:text-zibra-ink dark:hover:text-white transition-colors">{{ $crumb['label'] }}</a>
                                    @else
                                        <span class="text-zibra-ink dark:text-white font-medium">{{ $crumb['label'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </nav>
                @endif

                @if (session('success') || session('error'))
                    <div class="admin-flash-stack mb-6 space-y-3" role="status" aria-live="polite">
                        @if (session('success'))
                            <div x-data="{ show: true }" x-show="show" x-transition
                                x-init="setTimeout(() => show = false, 5600)"
                                class="admin-flash admin-flash--success flex items-start justify-between gap-3 rounded-xl border px-4 py-3">
                                <div class="flex items-start gap-3 min-w-0">
                                    <i class="fas fa-check-circle mt-0.5 shrink-0" aria-hidden="true"></i>
                                    <span class="text-sm font-medium leading-relaxed">{{ session('success') }}</span>
                                </div>
                                <button type="button" @click="show = false" class="shrink-0 opacity-70 hover:opacity-100" aria-label="Dismiss">
                                    <i class="fas fa-times" aria-hidden="true"></i>
                                </button>
                            </div>
                        @endif

                        @if (session('error'))
                            <div x-data="{ show: true }" x-show="show" x-transition
                                class="admin-flash admin-flash--error flex items-start justify-between gap-3 rounded-xl border px-4 py-3">
                                <div class="flex items-start gap-3 min-w-0">
                                    <i class="fas fa-exclamation-circle mt-0.5 shrink-0" aria-hidden="true"></i>
                                    <span class="text-sm font-medium leading-relaxed">{{ session('error') }}</span>
                                </div>
                                <button type="button" @click="show = false" class="shrink-0 opacity-70 hover:opacity-100" aria-label="Dismiss">
                                    <i class="fas fa-times" aria-hidden="true"></i>
                                </button>
                            </div>
                        @endif
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                // Bust query forces browsers to re-fetch SW after deploys.
                navigator.serviceWorker
                    .register('/sw-admin.js?v=10', { scope: '/admin' })
                    .catch(function () {});
            });
        }
    </script>

    <script>
        (function () {
            function csrfToken() {
                const meta = document.querySelector('meta[name="csrf-token"]');
                return meta ? meta.content : '';
            }

            function jsonFetch(url, options) {
                const headers = Object.assign({
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                }, (options && options.headers) || {});

                return fetch(url, Object.assign({}, options, { headers }))
                    .then(async (response) => {
                        let data = null;
                        try { data = await response.json(); } catch (_) {}
                        if (!response.ok) {
                            throw new Error((data && data.message) || ('Request failed (' + response.status + ')'));
                        }
                        return data || {};
                    });
            }

            function resolveAdminLink(link) {
                if (!link || link === '#') return null;
                try {
                    const url = new URL(link, window.location.origin);
                    if (url.pathname.indexOf('/admin') === 0) {
                        return url.pathname + url.search + url.hash;
                    }
                    if (url.origin === window.location.origin) {
                        return url.pathname + url.search + url.hash;
                    }
                } catch (_) {}
                if (link.charAt(0) === '/') return link;
                return null;
            }

            function closeNotificationsDrawer() {
                document.dispatchEvent(new CustomEvent('admin:notifications-close'));
                document.documentElement.classList.remove('admin-notif-open');
            }

            // Safety: never leave a stuck drawer/backdrop after Turbo navigations.
            document.addEventListener('turbo:before-visit', closeNotificationsDrawer);
            document.addEventListener('turbo:load', closeNotificationsDrawer);

            function setUnreadBadge(count) {
                const root = document.getElementById('admin-notifications');
                if (!root) return;
                const button = root.querySelector('.admin-notif__bell');
                let badge = root.querySelector('[data-unread-badge]');
                const n = Math.max(0, Number(count) || 0);

                if (n <= 0) {
                    if (badge) badge.remove();
                    return;
                }

                if (!badge && button) {
                    badge = document.createElement('span');
                    badge.setAttribute('data-unread-badge', '');
                    badge.className = 'admin-notif__badge';
                    button.appendChild(badge);
                }

                if (badge) {
                    badge.dataset.count = String(n);
                    badge.textContent = n > 9 ? '9+' : String(n);
                }
            }

            function go(link) {
                const target = resolveAdminLink(link);
                if (!target) return;
                closeNotificationsDrawer();
                if (window.brandRouteVeil) window.brandRouteVeil.show();
                if (window.Turbo && typeof Turbo.visit === 'function') {
                    Turbo.visit(target);
                } else {
                    window.location.href = target;
                }
            }

            window.markAsReadAndNavigate = function (el) {
                if (!el) return;
                const id = el.getAttribute('data-notification-id');
                const link = el.getAttribute('data-link') || '#';
                const wasUnread = el.getAttribute('data-unread') === '1';

                closeNotificationsDrawer();

                jsonFetch('/admin/notifications/' + encodeURIComponent(id) + '/read', { method: 'POST' })
                    .then((data) => {
                        el.classList.remove('is-unread');
                        el.setAttribute('data-unread', '0');
                        const dot = el.querySelector('[data-unread-dot]');
                        if (dot) dot.remove();

                        if (typeof data.unread_count === 'number') {
                            setUnreadBadge(data.unread_count);
                        } else if (wasUnread) {
                            const badge = document.querySelector('#admin-notifications [data-unread-badge]');
                            const current = badge ? parseInt(badge.dataset.count || '0', 10) : 0;
                            setUnreadBadge(Math.max(0, current - 1));
                        }

                        go(link);
                    })
                    .catch(() => {
                        // Still navigate even if mark-read fails (e.g. already deleted).
                        go(link);
                    });
            };

            window.markAllAsRead = function () {
                jsonFetch('/admin/notifications/mark-all-read', { method: 'POST' })
                    .then(() => {
                        document.querySelectorAll('#admin-notif-drawer [data-notification-id]').forEach((el) => {
                            el.classList.remove('is-unread');
                            el.setAttribute('data-unread', '0');
                            const dot = el.querySelector('[data-unread-dot]');
                            if (dot) dot.remove();
                        });
                        setUnreadBadge(0);
                        closeNotificationsDrawer();
                        if (window.brandRouteVeil) window.brandRouteVeil.show();
                        window.location.reload();
                    })
                    .catch((err) => {
                        window.dialog?.alert?.({
                            title: 'Could not update',
                            message: err.message || 'Could not mark notifications as read.',
                            tone: 'warning',
                        }) || window.toast?.show?.(err.message || 'Could not mark notifications as read.', 'error');
                    });
            };

            window.clearAllNotifications = async function () {
                const ok = await (window.dialog?.confirm?.({
                    title: 'Clear all notifications?',
                    message: 'This cannot be undone. Every notification will be removed permanently.',
                    confirmText: 'Clear all',
                    tone: 'danger',
                    eyebrow: 'Destructive action',
                }) ?? Promise.resolve(false));
                if (!ok) return;

                jsonFetch('/admin/notifications/clear', { method: 'DELETE' })
                    .then(() => {
                        setUnreadBadge(0);
                        closeNotificationsDrawer();
                        if (window.brandRouteVeil) window.brandRouteVeil.show();
                        window.location.reload();
                    })
                    .catch((err) => {
                        window.dialog?.alert?.({
                            title: 'Could not clear',
                            message: err.message || 'Could not clear notifications.',
                            tone: 'warning',
                        }) || window.toast?.show?.(err.message || 'Could not clear notifications.', 'error');
                    });
            };
        })();
    </script>

    <script>
        (function () {
            const prefetched = new Set();
            const HOVER_DELAY_MS = 60;
            let hoverTimer = null;

            function eligible(anchor) {
                if (!anchor || !(anchor instanceof HTMLAnchorElement)) return false;
                if (anchor.hasAttribute('data-no-prefetch') || anchor.target) return false;
                const href = anchor.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return false;
                if (anchor.hasAttribute('download')) return false;

                let url;
                try {
                    url = new URL(href, window.location.href);
                } catch {
                    return false;
                }
                if (url.origin !== window.location.origin) return false;
                if (url.pathname === window.location.pathname && url.search === window.location.search) return false;

                return url.href;
            }

            function prefetch(href) {
                if (!href || prefetched.has(href)) return;
                prefetched.add(href);
                const link = document.createElement('link');
                link.rel = 'prefetch';
                link.href = href;
                document.head.appendChild(link);
            }

            document.addEventListener('mouseover', (event) => {
                const anchor = event.target.closest?.('a');
                const href = eligible(anchor);
                if (!href) return;
                clearTimeout(hoverTimer);
                hoverTimer = setTimeout(() => prefetch(href), HOVER_DELAY_MS);
            });

            document.addEventListener('mouseout', () => clearTimeout(hoverTimer));

            document.addEventListener('touchstart', (event) => {
                const anchor = event.target.closest?.('a');
                const href = eligible(anchor);
                if (href) prefetch(href);
            }, { passive: true });

            document.addEventListener('mousedown', (event) => {
                const anchor = event.target.closest?.('a');
                const href = eligible(anchor);
                if (href) prefetch(href);
            });
        })();
    </script>

</body>

</html>
