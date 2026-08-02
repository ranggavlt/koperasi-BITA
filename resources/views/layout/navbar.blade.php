@php
    use App\Support\NavigationMenu;

    $role = auth()->user()->role ?? null;
    $currentModule = NavigationMenu::currentModule($role);
    $currentSection = $currentModule['section'] ?? 'Dashboard';
    $currentLabel = $currentModule['label'] ?? 'Dashboard';
    $quickLinks = NavigationMenu::quickLinks($role);
    $searchModules = NavigationMenu::searchModules($role);
    $brandName = config('navigation.brand.name', 'Koperasi BITA');
@endphp

<nav class="kbsm-navbar flex flex-wrap items-center justify-between px-0 py-2 mx-6 mt-3 mb-2 transition-all duration-250 ease-soft-in rounded-2xl lg:flex-nowrap lg:justify-start bg-white shadow-soft-xl" navbar-main>
    <div class="flex items-center justify-between w-full px-4 py-1 mx-auto flex-wrap lg:flex-nowrap" data-navbar-shell>
        
        <!-- BREADCRUMBS (LEFT) -->
        <nav class="lg:flex-1 flex justify-start">
            <div class="flex min-w-0 items-center" data-navbar-current style="gap: 0.7rem;">
                <span class="inline-flex items-center justify-center rounded-xl text-slate-700 bg-white shadow-sm" data-module-badge style="width: 2.2rem; height: 2.2rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M4 4H11V11H4V4ZM13 4H20V8.5H13V4ZM13 10.5H20V20H13V10.5ZM4 13H11V20H4V13Z" fill="currentColor" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <div class="text-slate-400 uppercase tracking-widest font-bold" style="font-size: 0.65rem;">
                        {{ $brandName }} / {{ $currentSection }}
                    </div>
                    <div class="text-slate-700 font-bold truncate text-sm">
                        {{ $currentLabel }}
                    </div>
                </div>
            </div>
        </nav>

        <!-- SEARCH BAR (CENTER) -->
        <div class="lg:flex-1 flex justify-center w-full lg:w-auto mt-3 lg:mt-0 order-3 lg:order-none">
            <form class="relative flex items-center w-full max-w-md" data-navbar-search-wrap data-module-search-form>
                <div class="relative w-full flex items-center">
                    <span class="absolute left-0 z-10 flex h-full items-center justify-center text-slate-400 w-10">
                        <i class="fas fa-search text-xs"></i>
                    </span>
                    <input type="text" class="kbsm-focus w-full rounded-xl border border-solid border-gray-300 bg-white text-sm text-slate-700 transition-all placeholder:text-slate-400 focus:outline-none focus:border-fuchsia-300 focus:shadow-soft-primary-outline pl-10 pr-4 py-2" style="height: 2.2rem;" placeholder="Cari modul..." autocomplete="off" data-module-search-input />
                    <button type="submit" class="ml-2 rounded-lg px-3 font-bold uppercase transition-all bg-gradient-to-tl from-slate-800 to-slate-700 text-white shadow-soft-md hover:scale-102" data-module-search-button style="height: 2.2rem; font-size: 0.7rem;">
                        Buka
                    </button>
                    <!-- Search Results -->
                    <div class="absolute left-0 right-0 z-50 hidden max-h-96 overflow-y-auto overflow-x-hidden rounded-2xl bg-white shadow-soft-3xl" style="top: calc(100% + 0.6rem);" data-module-search-results></div>
                </div>
                <div class="hidden" data-module-search-status aria-live="polite">
                    Ketik nama modul lalu tekan Enter.
                </div>
            </form>
        </div>

        <!-- TOOLS (RIGHT) -->
        <div class="lg:flex-1 flex items-center justify-end sm:mr-6 md:mr-0 order-2 lg:order-none" style="gap: 1rem;">
            <!-- Profile & Logout -->
            @auth
            <div class="hidden xl:flex items-center" style="gap: 0.5rem;">
                <a href="{{ route('pages.profile') }}" class="flex items-center rounded-xl border border-gray-200 bg-white pr-3 pl-1 py-1 font-bold uppercase text-slate-600 shadow-soft-xs transition-all hover:scale-105" style="font-size: 0.75rem;">
                  @if(auth()->user()->avatar_path)
                    <img src="{{ Storage::url(auth()->user()->avatar_path) }}" class="w-6 h-6 rounded-full object-cover mr-2" alt="avatar">
                  @else
                    <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mr-2"><i class="fas fa-user" style="font-size:0.6rem"></i></div>
                  @endif
                  {{ auth()->user()->name }}
                </a>
                <form method="POST" action="{{ route('logout') }}" class="m-0" data-kbsm-navigation-logout>
                  @csrf
                  <button type="submit" class="rounded-xl bg-gradient-to-tl from-red-600 to-rose-400 px-3 py-1.5 font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105" style="font-size: 0.75rem;">
                    <i class="fas fa-sign-out-alt mr-1"></i>Logout
                  </button>
                </form>
            </div>
            @endauth

            <!-- Hamburger Menu (Mobile) -->
            <a href="javascript:;" class="block p-0 text-sm transition-all ease-nav-brand text-slate-500 xl:hidden" sidenav-trigger aria-controls="kbsm-sidebar" aria-expanded="false" aria-label="Buka sidebar">
                <div class="w-4.5 overflow-hidden">
                    <i class="ease-soft mb-0.75 relative block h-0.5 rounded-sm bg-slate-500 transition-all"></i>
                    <i class="ease-soft mb-0.75 relative block h-0.5 rounded-sm bg-slate-500 transition-all"></i>
                    <i class="ease-soft relative block h-0.5 rounded-sm bg-slate-500 transition-all"></i>
                </div>
            </a>
        </div>
    </div>
</nav>

@once
    <style>
        [data-navbar-current] {
            flex: 0 0 auto;
        }

        [data-navbar-search-wrap] {
            flex: 1 1 16rem;
            min-width: 0;
        }

        [data-navbar-tools] {
            margin-left: auto;
        }

        [data-navbar-meta-pill],
        [data-navbar-pill],
        [data-module-badge] {
            background-color: var(--kbsm-green-soft);
            border: 1px solid rgba(47, 143, 58, 0.12);
        }

        [data-navbar-meta-pill] {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.75rem;
            height: 2.1rem;
            min-width: 2.1rem;
            padding: 0 0.7rem;
        }

        [data-navbar-pill] {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        [data-navbar-pill]:hover {
            background-color: var(--kbsm-green-soft);
            color: var(--kbsm-navy-dark);
        }

        [data-navbar-pill][data-active='true'] {
            background-color: var(--kbsm-navy);
            border-color: var(--kbsm-navy);
            color: var(--kbsm-white);
        }

        [data-module-search-button] {
            background-color: var(--kbsm-navy);
            color: var(--kbsm-white);
            border: 1px solid var(--kbsm-navy);
        }

        [data-module-search-button]:hover {
            background-color: var(--kbsm-navy-dark);
            border-color: var(--kbsm-navy-dark);
        }

        [data-module-search-results] {
            border: 1px solid rgba(226, 232, 240, 0.95);
        }

        [data-module-search-results] [data-module-result-item] {
            background-color: #ffffff;
        }

        [data-module-search-results] [data-module-result-item]:hover {
            background-color: var(--kbsm-green-soft);
        }

        [data-module-search-results] [data-result-badge] {
            background-color: var(--kbsm-green-soft);
            border: 1px solid rgba(47, 143, 58, 0.12);
            color: var(--kbsm-navy-dark);
        }

        [data-navbar-quick-links] {
            display: none;
        }

        @media (min-width: 992px) {
            [data-navbar-search-wrap] {
                flex: 0 1 28rem;
            }
        }

        @media (min-width: 1280px) {
            [data-navbar-quick-links] {
                display: flex;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modules = @json($searchModules);

            const normalize = (value = '') => value
                .toString()
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .trim();

            const escapeHtml = (value = '') => value
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const enrichedModules = modules.map((module) => ({
                ...module,
                badge: module.badge || 'MD',
                labelKey: normalize(module.label),
                haystack: normalize([
                    module.label,
                    module.section,
                    module.description,
                    ...(module.keywords || []),
                ].join(' ')),
                keywordsKey: (module.keywords || []).map(normalize),
            }));

            const defaultMatches = [...enrichedModules]
                .sort((left, right) => Number(right.isQuickLink) - Number(left.isQuickLink))
                .slice(0, 6);

            const findMatches = (query) => {
                const normalizedQuery = normalize(query);

                if (!normalizedQuery) {
                    return defaultMatches;
                }

                const queryTokens = normalizedQuery.split(/\s+/).filter(Boolean);

                return enrichedModules
                    .map((module) => {
                        let score = 0;

                        if (module.labelKey === normalizedQuery) score += 150;
                        if (module.keywordsKey.includes(normalizedQuery)) score += 130;
                        if (module.labelKey.startsWith(normalizedQuery)) score += 90;
                        if (module.haystack.includes(normalizedQuery)) score += 40;
                        if (module.isQuickLink) score += 8;

                        queryTokens.forEach((token) => {
                            if (module.labelKey.includes(token)) score += 25;
                            if (module.keywordsKey.some((keyword) => keyword.includes(token))) score += 18;
                            if (module.haystack.includes(token)) score += 6;
                        });

                        return { ...module, score };
                    })
                    .filter((module) => module.score > 0)
                    .sort((left, right) => right.score - left.score)
                    .slice(0, 6);
            };

            document.querySelectorAll('[data-module-search-form]').forEach((form) => {
                const input = form.querySelector('[data-module-search-input]');
                const results = form.querySelector('[data-module-search-results]');
                const status = form.querySelector('[data-module-search-status]');

                if (!input || !results || !status) {
                    return;
                }

                const closeResults = () => {
                    results.classList.add('hidden');
                };

                const openResults = () => {
                    results.classList.remove('hidden');
                };

                const renderResults = (matches, query) => {
                    if (!matches.length) {
                        results.innerHTML = `
                            <div class="px-4 py-4 text-sm text-slate-500">
                                Modul "<strong>${escapeHtml(query)}</strong>" tidak ditemukan. Coba kata lain seperti produk, kasir, simpanan, atau payroll.
                            </div>
                        `;
                        openResults();
                        status.textContent = 'Tidak ada modul yang cocok dengan pencarian itu.';
                        return;
                    }

                    const items = matches.map((module) => `
                        <a
                            href="${escapeHtml(module.url)}"
                            class="flex items-start px-4 py-3 transition-colors"
                            data-module-result-item
                            style="gap: 0.75rem; text-decoration: none;">
                            <span
                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl shadow-soft-xs"
                                data-result-badge
                                style="flex-shrink: 0; font-size: 0.76rem; font-weight: 700; letter-spacing: 0.06em;">
                                ${escapeHtml(module.badge)}
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xs font-bold uppercase text-slate-400" style="letter-spacing: 0.12em;">
                                    ${escapeHtml(module.section)}
                                </span>
                                <span class="block font-semibold text-slate-700">
                                    ${escapeHtml(module.label)}
                                </span>
                                <span class="block text-sm leading-normal text-slate-500">
                                    ${escapeHtml(module.description)}
                                </span>
                            </span>
                        </a>
                    `).join('');

                    results.innerHTML = items;
                    openResults();

                    if (query.trim()) {
                        status.textContent = `Tekan Enter untuk membuka ${matches[0].label}.`;
                    } else {
                        status.textContent = 'Modul populer ditampilkan agar kamu bisa buka lebih cepat.';
                    }
                };

                input.addEventListener('focus', () => {
                    renderResults(findMatches(input.value), input.value);
                });

                input.addEventListener('input', () => {
                    renderResults(findMatches(input.value), input.value);
                });

                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeResults();
                        input.blur();
                    }
                });

                form.addEventListener('submit', (event) => {
                    event.preventDefault();

                    const matches = findMatches(input.value);

                    if (!input.value.trim()) {
                        renderResults(defaultMatches, '');
                        return;
                    }

                    if (!matches.length) {
                        renderResults([], input.value);
                        return;
                    }

                    window.location.assign(matches[0].url);
                });

                document.addEventListener('click', (event) => {
                    if (!form.contains(event.target)) {
                        closeResults();
                    }
                });

                document.addEventListener('keydown', (event) => {
                    const activeElement = document.activeElement;
                    const isTypingContext = activeElement && (
                        activeElement.tagName === 'INPUT'
                        || activeElement.tagName === 'TEXTAREA'
                        || activeElement.tagName === 'SELECT'
                        || activeElement.isContentEditable
                    );

                    if ((event.key === '/' || (event.key.toLowerCase() === 'k' && (event.ctrlKey || event.metaKey))) && !isTypingContext) {
                        event.preventDefault();
                        input.focus();
                        input.select();
                        renderResults(findMatches(input.value), input.value);
                    }
                });
            });
        });
    </script>
@endonce
