@php
    $adminName = auth()->user()->name ?? 'Admin';
    $adminInitials = collect(preg_split('/\s+/', trim($adminName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('') ?: 'A';
@endphp

<header class="admin-topbar">
    <div class="admin-topbar__inner">
        <div class="admin-topbar__lead">
            <button type="button"
                class="admin-menu-toggle"
                @click="toggleSidebar()"
                :class="{ 'is-open': sidebarOpen }"
                :aria-expanded="sidebarOpen ? 'true' : 'false'"
                aria-controls="admin-drawer"
                aria-label="Toggle navigation">
                <span class="admin-menu-toggle__bars" aria-hidden="true">
                    <span></span><span></span><span></span>
                </span>
            </button>

            <a href="{{ route('admin.dashboard') }}" class="admin-topbar__brand" aria-label="{{ $brandDisplayName ?? 'Store' }} admin">
                <img src="{{ $brandLogoUrl ?? asset('images/brand-logo.png') }}"
                    alt="{{ $brandDisplayName ?? 'Store' }}"
                    class="admin-topbar__brand-logo"
                    width="36"
                    height="36">
                <span class="admin-topbar__brand-name">{{ $brandDisplayName ?? 'Store' }}</span>
            </a>

            <div class="admin-topbar__search"
                x-data="deskSearch()"
                @keydown.escape.window="close()"
                @click.outside="close()">
                <label class="admin-topbar__search-field">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search"
                        x-ref="input"
                        x-model="q"
                        @input="onInput()"
                        @focus="onFocus()"
                        @keydown.down.prevent="move(1)"
                        @keydown.up.prevent="move(-1)"
                        @keydown.enter.prevent="goActive()"
                        placeholder="Search products, orders, customers…"
                        autocomplete="off"
                        enterkeyhint="search"
                        aria-label="Search admin desk"
                        :aria-expanded="open ? 'true' : 'false'"
                        aria-controls="admin-desk-search-panel"
                        aria-autocomplete="list"
                        role="combobox">
                </label>

                <div id="admin-desk-search-panel"
                    class="admin-desk-search"
                    x-show="open"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 translate-y-1"
                    role="listbox"
                    x-cloak>
                    <template x-if="loading">
                        <p class="admin-desk-search__hint">Searching…</p>
                    </template>
                    <template x-if="!loading && q.trim().length < 2">
                        <p class="admin-desk-search__hint">Type at least 2 characters · <kbd>Ctrl</kbd>+<kbd>K</kbd> to focus</p>
                    </template>
                    <template x-if="!loading && q.trim().length >= 2 && isEmpty()">
                        <p class="admin-desk-search__hint">No matches for “<span x-text="q.trim()"></span>”</p>
                    </template>

                    <template x-for="group in groups" :key="group.key">
                        <div class="admin-desk-search__group" x-show="group.items.length > 0">
                            <p class="admin-desk-search__group-label" x-text="group.label"></p>
                            <template x-for="(item, index) in group.items" :key="group.key + '-' + item.id">
                                <a :href="item.url"
                                    class="admin-desk-search__item"
                                    :class="{ 'is-active': activeKey === group.key + '-' + index }"
                                    @mouseenter="activeKey = group.key + '-' + index"
                                    @click="close()"
                                    role="option">
                                    <span class="admin-desk-search__item-label" x-text="item.label"></span>
                                    <span class="admin-desk-search__item-meta" x-text="item.meta"></span>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="admin-topbar__actions">
            <button type="button"
                class="admin-topbar__theme"
                @click="darkMode = !darkMode"
                :aria-pressed="darkMode ? 'true' : 'false'"
                :aria-label="darkMode ? 'Switch to light mode' : 'Switch to dark mode'">
                <span class="admin-topbar__theme-track" aria-hidden="true">
                    <span class="admin-topbar__theme-thumb" :class="{ 'is-dark': darkMode }">
                        <i class="fas fa-moon" x-show="!darkMode"></i>
                        <i class="fas fa-sun" x-show="darkMode" x-cloak></i>
                    </span>
                </span>
                <span class="admin-topbar__theme-label" x-text="darkMode ? 'Dark' : 'Light'"></span>
            </button>

            <div
                id="admin-notifications"
                class="admin-topbar__notif"
                x-data="{
                    open: false,
                    close() { this.open = false; },
                    toggle() { this.open = !this.open; },
                    init() {
                        const close = () => { this.open = false; };
                        document.addEventListener('turbo:before-visit', close);
                        document.addEventListener('turbo:load', close);
                        document.addEventListener('admin:notifications-close', close);
                        this.$watch('open', (v) => {
                            document.documentElement.classList.toggle('admin-notif-open', v);
                        });
                    }
                }"
                @keydown.escape.window="if (open) close()">

                <button @click="toggle()" type="button"
                    class="admin-notif__bell"
                    :aria-expanded="open"
                    aria-controls="admin-notif-drawer"
                    aria-label="Notifications">
                    <i class="fas fa-bell" aria-hidden="true"></i>
                    @if ($unreadCount > 0)
                        <span data-unread-badge
                            data-count="{{ $unreadCount }}"
                            class="admin-notif__badge">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                    @endif
                </button>

                <template x-teleport="body">
                    <div x-show="open"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="admin-notif__backdrop"
                        @click="close()"
                        x-cloak></div>
                </template>

                <template x-teleport="body">
                    <aside x-show="open"
                        id="admin-notif-drawer"
                        role="dialog"
                        aria-modal="true"
                        aria-label="Notifications"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="translate-x-full opacity-0"
                        x-transition:enter-end="translate-x-0 opacity-100"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="translate-x-0 opacity-100"
                        x-transition:leave-end="translate-x-full opacity-0"
                        class="admin-notif__drawer"
                        @click.stop
                        x-cloak>

                        <div class="admin-notif__drawer-glow" aria-hidden="true"></div>

                        <header class="admin-notif__header">
                            <div class="admin-notif__header-copy">
                                <p class="admin-notif__eyebrow">Activity desk</p>
                                <h3 class="admin-notif__title">Notifications</h3>
                                <p class="admin-notif__subtitle">
                                    @if ($unreadCount > 0)
                                        {{ $unreadCount }} unread · latest {{ $notifications->count() }}
                                    @elseif ($notifications->count() > 0)
                                        All caught up · latest {{ $notifications->count() }}
                                    @else
                                        Quiet for now
                                    @endif
                                </p>
                            </div>
                            <div class="admin-notif__header-actions">
                                @if ($unreadCount > 0)
                                    <button onclick="markAllAsRead()" type="button" class="admin-notif__chip-btn">
                                        Mark all read
                                    </button>
                                @endif
                                @if ($notifications->count() > 0)
                                    <button onclick="clearAllNotifications()" type="button" class="admin-notif__chip-btn is-danger">
                                        Clear all
                                    </button>
                                @endif
                                <button type="button" class="admin-notif__close" @click="close()" aria-label="Close notifications">
                                    <i class="fas fa-times" aria-hidden="true"></i>
                                </button>
                            </div>
                        </header>

                        <div class="admin-notif__list" data-notifications-list>
                            @forelse($notifications as $notification)
                                @php
                                    $tone = match ($notification->color) {
                                        'yellow' => 'warn',
                                        'green' => 'ok',
                                        'red' => 'bad',
                                        default => 'info',
                                    };
                                    $link = $notification->link ?: '#';
                                    if (is_string($link) && str_starts_with($link, 'http')) {
                                        $path = parse_url($link, PHP_URL_PATH) ?: '#';
                                        $query = parse_url($link, PHP_URL_QUERY);
                                        $link = $query ? "{$path}?{$query}" : $path;
                                    }
                                @endphp
                                <a href="{{ $link }}"
                                    onclick="event.preventDefault(); markAsReadAndNavigate(this)"
                                    class="admin-notif__item{{ !$notification->is_read ? ' is-unread' : '' }}"
                                    data-notification-id="{{ $notification->id }}"
                                    data-unread="{{ $notification->is_read ? '0' : '1' }}"
                                    data-link="{{ $link }}">
                                    <span class="admin-notif__icon admin-notif__icon--{{ $tone }}" aria-hidden="true">
                                        <i class="fas {{ $notification->icon ?? 'fa-bell' }}"></i>
                                    </span>
                                    <span class="admin-notif__body">
                                        <span class="admin-notif__item-top">
                                            <span class="admin-notif__item-title">
                                                {{ $notification->title }}
                                                @if (!$notification->is_read)
                                                    <span data-unread-dot class="admin-notif__dot"></span>
                                                @endif
                                            </span>
                                            <span class="admin-notif__item-time">{{ $notification->time_ago }}</span>
                                        </span>
                                        <span class="admin-notif__item-msg">{{ $notification->message }}</span>
                                    </span>
                                    <span class="admin-notif__chevron" aria-hidden="true">
                                        <i class="fas fa-arrow-right"></i>
                                    </span>
                                </a>
                            @empty
                                <div class="admin-notif__empty">
                                    <span class="admin-notif__empty-mark" aria-hidden="true">
                                        <i class="fas fa-bell-slash"></i>
                                    </span>
                                    <p>No notifications</p>
                                    <span>You’re all caught up — new activity will land here.</span>
                                </div>
                            @endforelse
                        </div>

                        @if ($notifications->count() > 0)
                            <footer class="admin-notif__footer">
                                <span>Live feed</span>
                                <span>
                                    Showing latest {{ $notifications->count() }}
                                    @if ($unreadCount > 0)
                                        · <strong>{{ $unreadCount }} unread</strong>
                                    @endif
                                </span>
                            </footer>
                        @endif
                    </aside>
                </template>
            </div>

            <div class="admin-topbar__profile" x-data="{ open: false }" @keydown.escape.window="open = false">
                <button type="button"
                    class="admin-topbar__profile-btn"
                    @click="open = !open"
                    :aria-expanded="open ? 'true' : 'false'"
                    aria-haspopup="menu"
                    aria-label="Account menu">
                    <span class="admin-topbar__avatar" aria-hidden="true">{{ $adminInitials }}</span>
                    <span class="admin-topbar__profile-copy">
                        <span class="admin-topbar__profile-name">{{ $adminName }}</span>
                        <span class="admin-topbar__profile-role">Administrator</span>
                    </span>
                    <i class="fas fa-chevron-down admin-topbar__profile-caret" aria-hidden="true"></i>
                </button>

                <div class="admin-topbar__menu"
                    role="menu"
                    x-show="open"
                    @click.away="open = false"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 translate-y-1"
                    x-cloak>
                    <a href="{{ route('admin.profile') }}" class="admin-topbar__menu-item" role="menuitem">
                        <i class="fas fa-user" aria-hidden="true"></i>
                        Profile
                    </a>
                    <a href="{{ route('admin.settings.index') }}" class="admin-topbar__menu-item" role="menuitem">
                        <i class="fas fa-cog" aria-hidden="true"></i>
                        Settings
                    </a>
                    <div class="admin-topbar__menu-rule" aria-hidden="true"></div>
                    <form method="POST" action="{{ route('admin.logout') }}" data-turbo="false" data-no-loader>
                        @csrf
                        <button type="submit" class="admin-topbar__menu-item is-danger" role="menuitem">
                            <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
