<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <title>Sign in — {{ $brandDisplayName ?? config('app.name', 'Default Company') }}</title>

    @include('admin.partials.favicon')

    @include('admin.partials.critical-loader-css')
    <link rel="preload" href="{{ $brandLogoUrl ?? asset('images/brand-logo.png') }}" as="image">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Merriweather+Sans:wght@400;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Merriweather+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    </noscript>

    @vite(['resources/css/app.css', 'resources/js/admin.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" media="print" onload="this.media='all'">
</head>

<body class="min-h-screen bg-zibra-paper font-body text-zibra-ink antialiased">

    @include('admin.partials.route-veil', ['label' => 'Loading'])

    {{-- Subtle zebra-stripe atmosphere matching the storefront --}}
    <div class="pointer-events-none fixed inset-0 opacity-[0.035]"
        style="background: repeating-linear-gradient(115deg, #000 0 9px, transparent 9px 20px);"></div>

    <div class="relative min-h-screen flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-[420px] animate-fade-in-up">
            <div class="border border-zibra-line bg-white">
                <div class="px-8 pt-10 pb-8 text-center border-b border-zibra-line">
                    <img src="{{ $brandLogoUrl ?? asset('images/brand-logo.png') }}" alt="{{ $brandDisplayName ?? 'Store' }}"
                        class="h-14 w-14 mx-auto object-cover rounded-full border border-zibra-line bg-white">
                    <p class="mt-5 text-[10px] uppercase tracking-[0.28em] text-zibra-ash">Admin access</p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-zibra-ink">Sign in</h1>
                    <p class="mt-2 text-sm text-zibra-ash">Authorized staff only. Sessions are encrypted and rate-limited.</p>
                </div>

                <div class="px-8 py-8">
                    @if (session('error'))
                        <div class="mb-6 p-4 border-l-2 border-red-600 bg-red-50 text-red-800 text-sm flex items-start gap-3" role="alert">
                            <i class="fas fa-exclamation-circle mt-0.5"></i>
                            <p>{{ session('error') }}</p>
                        </div>
                    @endif

                    @if (session('success'))
                        <div class="mb-6 p-4 border-l-2 border-green-600 bg-green-50 text-green-800 text-sm flex items-start gap-3" role="status">
                            <i class="fas fa-check-circle mt-0.5"></i>
                            <p>{{ session('success') }}</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.login.post') }}" class="space-y-6" autocomplete="on" data-turbo="false">
                        @csrf

                        <div>
                            <label for="email" class="block text-[10px] uppercase tracking-[0.2em] text-zibra-ash mb-2">
                                Email
                            </label>
                            <input type="email" id="email" name="email" value="{{ $rememberedEmail }}" required {{ $rememberedEmail !== '' ? '' : 'autofocus' }}
                                autocomplete="username"
                                spellcheck="false"
                                inputmode="email"
                                maxlength="255"
                                class="w-full px-0 py-3 border-0 border-b border-zibra-line bg-transparent text-zibra-ink placeholder-zibra-ash focus:outline-none focus:border-zibra-ink transition-colors"
                                placeholder="Email">
                            @error('email')
                                <p class="mt-2 text-sm text-red-600 flex items-center gap-1" role="alert">
                                    <i class="fas fa-exclamation-circle text-xs"></i>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div x-data="{ show: false }">
                            <label for="password" class="block text-[10px] uppercase tracking-[0.2em] text-zibra-ash mb-2">
                                Password
                            </label>
                            <div class="relative">
                                <input :type="show ? 'text' : 'password'" id="password" name="password" required
                                    autocomplete="current-password"
                                    maxlength="255"
                                    {{ $rememberedEmail !== '' ? 'autofocus' : '' }}
                                    class="w-full px-0 py-3 pr-10 border-0 border-b border-zibra-line bg-transparent text-zibra-ink placeholder-zibra-ash focus:outline-none focus:border-zibra-ink transition-colors"
                                    placeholder="••••••••">
                                <button type="button" @click="show = !show" data-no-loader
                                    aria-label="Toggle password visibility"
                                    class="absolute right-0 top-1/2 -translate-y-1/2 text-zibra-ash hover:text-zibra-ink transition-colors">
                                    <i :class="show ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-2 text-sm text-red-600 flex items-center gap-1" role="alert">
                                    <i class="fas fa-exclamation-circle text-xs"></i>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div class="flex items-start justify-between gap-4 pt-1">
                            <label for="remember" class="group flex items-start gap-3 cursor-pointer select-none">
                                <span class="relative mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center border border-zibra-line bg-white transition-colors group-hover:border-zibra-ink has-[:checked]:border-zibra-ink has-[:checked]:bg-zibra-ink">
                                    <input type="checkbox" id="remember" name="remember" value="1"
                                        class="peer sr-only"
                                        @checked($keepSignedIn)>
                                    <i class="fas fa-check text-[9px] text-white opacity-0 peer-checked:opacity-100" aria-hidden="true"></i>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-[11px] font-semibold tracking-[0.08em] uppercase text-zibra-ink">
                                        Keep me signed in
                                    </span>
                                    <span class="mt-0.5 block text-xs leading-relaxed text-zibra-ash">
                                        Trusted device only — stays signed in for 30 days.
                                    </span>
                                </span>
                            </label>
                        </div>

                        <button type="submit"
                            class="w-full h-12 bg-zibra-ink text-white text-[11px] uppercase tracking-[0.22em] hover:bg-black transition-colors">
                            Sign in
                        </button>
                    </form>

                    <div class="mt-8 pt-6 border-t border-zibra-line text-center">
                        <a href="{{ rtrim(config('app.frontend_url'), '/') }}/" data-no-loader
                            class="inline-flex items-center gap-2 text-[11px] uppercase tracking-[0.18em] text-zibra-ash hover:text-zibra-ink transition-colors">
                            <i class="fas fa-arrow-left text-[10px]"></i>
                            Back to website
                        </a>
                    </div>
                </div>
            </div>

            <p class="text-center mt-8 text-xs text-zibra-ash tracking-wide">
                © {{ date('Y') }} {{ $brandDisplayName ?? 'Default Company' }}. All rights reserved.
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        (function () {
            var emailKey = 'dokannward.admin.email';
            var keepKey = 'dokannward.admin.keep';
            var email = document.getElementById('email');
            var remember = document.getElementById('remember');
            var form = email && email.form;
            if (!email || !form) return;

            try {
                if (!email.value) {
                    var savedEmail = window.localStorage.getItem(emailKey);
                    if (savedEmail) {
                        email.value = savedEmail;
                        if (!email.hasAttribute('autofocus')) {
                            var password = document.getElementById('password');
                            if (password) password.focus();
                        }
                    }
                }
                if (remember && !remember.checked) {
                    remember.checked = window.localStorage.getItem(keepKey) === '1';
                }
            } catch (e) {}

            form.addEventListener('submit', function () {
                try {
                    var value = (email.value || '').trim().toLowerCase();
                    if (value) window.localStorage.setItem(emailKey, value);
                    if (remember) {
                        window.localStorage.setItem(keepKey, remember.checked ? '1' : '0');
                    }
                } catch (e) {}
            });
        })();
    </script>
</body>

</html>
