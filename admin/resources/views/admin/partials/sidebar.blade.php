{{-- Overlay navigation drawer — Dokan Ward editorial chrome --}}
<div id="admin-drawer" class="admin-drawer" x-cloak
    :class="{ 'is-open': sidebarOpen }"
    @keydown.escape.window="closeSidebar()"
    role="dialog"
    aria-modal="true"
    :aria-hidden="sidebarOpen ? 'false' : 'true'">

    <div class="admin-drawer__veil"
        x-show="sidebarOpen"
        x-transition:enter="admin-drawer-veil-enter"
        x-transition:enter-start="admin-drawer-veil-enter-start"
        x-transition:enter-end="admin-drawer-veil-enter-end"
        x-transition:leave="admin-drawer-veil-leave"
        x-transition:leave-start="admin-drawer-veil-leave-start"
        x-transition:leave-end="admin-drawer-veil-leave-end"
        @click="closeSidebar()"
        aria-hidden="true"></div>

    <aside class="admin-sidebar admin-drawer__panel admin-sidebar--luxury"
        x-show="sidebarOpen"
        x-transition:enter="admin-drawer-panel-enter"
        x-transition:enter-start="admin-drawer-panel-enter-start"
        x-transition:enter-end="admin-drawer-panel-enter-end"
        x-transition:leave="admin-drawer-panel-leave"
        x-transition:leave-start="admin-drawer-panel-leave-start"
        x-transition:leave-end="admin-drawer-panel-leave-end"
        @click.stop>

        <span class="admin-sidebar__edge" aria-hidden="true"></span>

        <div class="admin-sidebar__brand">
            <div class="admin-drawer__brand-row">
                <a href="{{ route('admin.dashboard') }}" class="admin-sidebar__brand-link group min-w-0" @click="closeSidebar()">
                    <img src="{{ ($brandLogoUrl ?? asset('images/brand-logo.png')) }}" alt="{{ $brandDisplayName ?? 'Store' }}"
                        class="admin-sidebar__logo">
                    <p class="admin-sidebar-brand">Admin · Commerce</p>
                </a>
                <button type="button" class="admin-drawer__close" @click="closeSidebar()" aria-label="Close menu">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <span class="admin-sidebar__brand-stripe" aria-hidden="true"></span>
        </div>

        <nav class="admin-sidebar__nav" aria-label="Admin" data-admin-nav>
            <div class="admin-sidebar__group">
                <p class="admin-sidebar__label">
                    <span class="admin-sidebar__label-mark" aria-hidden="true"></span>
                    Commerce
                </p>

                <a href="{{ route('admin.dashboard') }}"
                    class="admin-nav-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">
                    <span class="admin-nav-link__icon" aria-hidden="true"><i class="fas fa-home"></i></span>
                    <span class="admin-nav-link__text">Dashboard</span>
                </a>

                <a href="{{ route('admin.products.index') }}"
                    class="admin-nav-link {{ request()->routeIs('admin.products.*') ? 'is-active' : '' }}">
                    <span class="admin-nav-link__icon" aria-hidden="true"><i class="fas fa-box"></i></span>
                    <span class="admin-nav-link__text">Products</span>
                </a>

                <a href="{{ route('admin.collections.index') }}"
                    class="admin-nav-link {{ request()->routeIs('admin.collections.*') ? 'is-active' : '' }}">
                    <span class="admin-nav-link__icon" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                    <span class="admin-nav-link__text">Collections</span>
                </a>

                <a href="{{ route('admin.categories.index') }}"
                    class="admin-nav-link {{ request()->routeIs('admin.categories.*') ? 'is-active' : '' }}">
                    <span class="admin-nav-link__icon" aria-hidden="true"><i class="fas fa-tags"></i></span>
                    <span class="admin-nav-link__text">Categories</span>
                </a>

                @php
                    $ordersBadge = (int) (($navBadgeClear['orders'] ?? null) ?: ($pendingOrders ?? 0));
                    $ordersClearing = isset($navBadgeClear['orders']) && $navBadgeClear['orders'] > 0;
                @endphp
                <a href="{{ route('admin.orders.index') }}"
                    class="admin-nav-link {{ request()->routeIs('admin.orders.*') ? 'is-active' : '' }}">
                    <span class="admin-nav-link__icon" aria-hidden="true"><i class="fas fa-shopping-cart"></i></span>
                    <span class="admin-nav-link__text">Orders</span>
                    @if ($ordersBadge > 0)
                        <span class="admin-nav-badge" data-nav-badge="orders" data-count="{{ $ordersBadge }}"
                            @if ($ordersClearing) data-clearing="1" @endif>{{ $ordersBadge }}</span>
                    @endif
                </a>

                <a href="{{ route('admin.customers.index') }}"
                    class="admin-nav-link {{ request()->routeIs('admin.customers.*') ? 'is-active' : '' }}">
                    <span class="admin-nav-link__icon" aria-hidden="true"><i class="fas fa-users"></i></span>
                    <span class="admin-nav-link__text">Customers</span>
                </a>

                <a href="{{ route('admin.brands.index') }}"
                    class="admin-nav-link {{ request()->routeIs('admin.brands.*') ? 'is-active' : '' }}">
                    <span class="admin-nav-link__icon" aria-hidden="true"><i class="fas fa-certificate"></i></span>
                    <span class="admin-nav-link__text">Brands</span>
                </a>
            </div>

            <div class="admin-sidebar__group">
                <p class="admin-sidebar__label">
                    <span class="admin-sidebar__label-mark" aria-hidden="true"></span>
                    Content
                </p>

                <a href="{{ route('admin.website.index') }}"
                    class="admin-nav-link {{ request()->routeIs('admin.website.*') ? 'is-active' : '' }}">
                    <span class="admin-nav-link__icon" aria-hidden="true"><i class="fas fa-globe"></i></span>
                    <span class="admin-nav-link__text">Website</span>
                </a>

                <a href="{{ route('admin.policies.index') }}"
                    class="admin-nav-link {{ request()->routeIs('admin.policies.*') ? 'is-active' : '' }}">
                    <span class="admin-nav-link__icon" aria-hidden="true"><i class="fas fa-scale-balanced"></i></span>
                    <span class="admin-nav-link__text">Policies</span>
                </a>

                <a href="{{ route('admin.pages.index') }}"
                    class="admin-nav-link {{ request()->routeIs('admin.pages.*') ? 'is-active' : '' }}">
                    <span class="admin-nav-link__icon" aria-hidden="true"><i class="fas fa-file-alt"></i></span>
                    <span class="admin-nav-link__text">Text pages</span>
                </a>

                <a href="{{ route('admin.banners.index') }}"
                    class="admin-nav-link {{ request()->routeIs('admin.banners.*') ? 'is-active' : '' }}">
                    <span class="admin-nav-link__icon" aria-hidden="true"><i class="fas fa-image"></i></span>
                    <span class="admin-nav-link__text">Banners</span>
                </a>

                <a href="{{ route('admin.testimonials.index') }}"
                    class="admin-nav-link {{ request()->routeIs('admin.testimonials.*') ? 'is-active' : '' }}">
                    <span class="admin-nav-link__icon" aria-hidden="true"><i class="fas fa-comment-dots"></i></span>
                    <span class="admin-nav-link__text">Testimonials</span>
                </a>

                @php
                    $reviewsBadge = (int) (($navBadgeClear['reviews'] ?? null) ?: ($pendingReviews ?? 0));
                    $reviewsClearing = isset($navBadgeClear['reviews']) && $navBadgeClear['reviews'] > 0;
                @endphp
                <a href="{{ route('admin.reviews.index') }}"
                    class="admin-nav-link {{ request()->routeIs('admin.reviews.*') ? 'is-active' : '' }}">
                    <span class="admin-nav-link__icon" aria-hidden="true"><i class="fas fa-star"></i></span>
                    <span class="admin-nav-link__text">{{ __('admin.reviews') }}</span>
                    @if ($reviewsBadge > 0)
                        <span class="admin-nav-badge" data-nav-badge="reviews" data-count="{{ $reviewsBadge }}"
                            @if ($reviewsClearing) data-clearing="1" @endif>{{ $reviewsBadge }}</span>
                    @endif
                </a>

                @php
                    $contactBadge = (int) (($navBadgeClear['contact'] ?? null) ?: ($unreadContactMessages ?? 0));
                    $contactClearing = isset($navBadgeClear['contact']) && $navBadgeClear['contact'] > 0;
                @endphp
                <a href="{{ route('admin.contact-messages.index') }}"
                    class="admin-nav-link {{ request()->routeIs('admin.contact-messages.*') ? 'is-active' : '' }}">
                    <span class="admin-nav-link__icon" aria-hidden="true"><i class="fas fa-envelope"></i></span>
                    <span class="admin-nav-link__text">Contact Messages</span>
                    @if ($contactBadge > 0)
                        <span class="admin-nav-badge" data-nav-badge="contact" data-count="{{ $contactBadge }}"
                            @if ($contactClearing) data-clearing="1" @endif>{{ $contactBadge }}</span>
                    @endif
                </a>

                <a href="{{ route('admin.inventory.index') }}"
                    class="admin-nav-link {{ request()->routeIs('admin.inventory.*') ? 'is-active' : '' }}">
                    <span class="admin-nav-link__icon" aria-hidden="true"><i class="fas fa-warehouse"></i></span>
                    <span class="admin-nav-link__text">Inventory</span>
                </a>
            </div>

            <div class="admin-sidebar__divider" aria-hidden="true"></div>

            <div class="admin-sidebar__group admin-sidebar__group--utility">
                <a href="{{ route('admin.profile') }}"
                    class="admin-nav-link {{ request()->routeIs('admin.profile') ? 'is-active' : '' }}"
                    @click="closeSidebar()">
                    <span class="admin-nav-link__icon" aria-hidden="true"><i class="fas fa-user"></i></span>
                    <span class="admin-nav-link__text">Profile</span>
                </a>

                <a href="{{ route('admin.settings.index') }}"
                    class="admin-nav-link {{ request()->routeIs('admin.settings.*') ? 'is-active' : '' }}">
                    <span class="admin-nav-link__icon" aria-hidden="true"><i class="fas fa-cog"></i></span>
                    <span class="admin-nav-link__text">{{ __('admin.settings') }}</span>
                </a>
            </div>
        </nav>

        <div class="admin-drawer__foot">
            <span class="admin-sidebar__foot-stripe" aria-hidden="true"></span>
            <p class="admin-drawer__foot-label">{{ $brandDisplayName ?? 'Store' }} Commerce</p>
            <p class="admin-drawer__foot-meta">Navigate the house</p>
        </div>
    </aside>
</div>
