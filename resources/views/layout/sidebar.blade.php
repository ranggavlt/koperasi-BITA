@php
  use App\Support\NavigationMenu;

  $brand = NavigationMenu::brand();
  $brandName = $brand['name'] ?? 'Koperasi BITA';
  $brandSubtitle = $brand['subtitle'] ?? 'POS, Simpan Pinjam, dan Laporan';
  $brandLogo = $brand['logo'] ?? 'assets/img/logo-koperasi.png';
  $dashboard = NavigationMenu::dashboardModule();
  $groups = NavigationMenu::sidebarGroups();
  $stateKeys = NavigationMenu::sidebarStateKeys();
@endphp

<aside
  class="kbsm-sidebar max-w-62.5 ease-nav-brand z-990 fixed inset-y-0 my-4 ml-4 block w-full -translate-x-full flex-wrap items-stretch overflow-y-auto overflow-x-hidden rounded-2xl border-0 bg-white p-0 antialiased shadow-soft-xl transition-transform duration-200 xl:left-0 xl:translate-x-0 xl:bg-white"
  data-kbsm-sidebar
  data-group-storage-key="{{ $stateKeys['groups'] }}"
  data-scroll-storage-key="{{ $stateKeys['scroll'] }}"
  data-storage-prefixes='@json($stateKeys['prefixes'])'>

  <button type="button" class="kbsm-sidebar-close xl:hidden" sidenav-close aria-label="Tutup sidebar">
    <span aria-hidden="true">&times;</span>
  </button>

  <a class="kbsm-sidebar-brand" href="{{ $dashboard['url'] ?? route('pages.dashboard') }}">
    <span class="kbsm-sidebar-brand__logo">
      <img src="{{ asset($brandLogo) }}" alt="{{ $brandName }}" />
    </span>
    <span class="kbsm-sidebar-brand__text">
      <span class="kbsm-sidebar-brand__name">{{ $brandName }}</span>
      <span class="kbsm-sidebar-brand__subtitle">{{ $brandSubtitle }}</span>
    </span>
  </a>

  <hr class="kbsm-sidebar-divider" />

  <nav class="kbsm-sidebar-nav" aria-label="Navigasi utama KBSM">
    <a
      class="kbsm-sidebar-link {{ $dashboard['active'] ?? false ? 'kbsm-sidebar-link--active' : '' }}"
      href="{{ $dashboard['url'] ?? route('pages.dashboard') }}"
      data-sidebar-link
      data-sidebar-dashboard>
      <span class="kbsm-sidebar-link__icon">
        @include('layout.partials.nav-icon', ['icon' => $dashboard['icon'] ?? 'dashboard'])
      </span>
      <span class="kbsm-sidebar-link__label">{{ $dashboard['label'] ?? 'Dashboard' }}</span>
    </a>

    <div class="kbsm-sidebar-accordion" data-sidebar-accordion>
      @foreach($groups as $group)
        @php
          $groupKey = $group['key'];
          $groupActive = (bool) ($group['active'] ?? false);
        @endphp

        <section
          class="kbsm-sidebar-group {{ $groupActive ? 'kbsm-sidebar-group--active' : '' }}"
          data-sidebar-group="{{ $groupKey }}"
          data-sidebar-group-active="{{ $groupActive ? 'true' : 'false' }}">
          <button
            type="button"
            class="kbsm-sidebar-group__toggle"
            aria-expanded="{{ $groupActive ? 'true' : 'false' }}"
            data-sidebar-group-toggle>
            <span class="kbsm-sidebar-group__icon">
              @include('layout.partials.nav-icon', ['icon' => $group['icon'] ?? 'folder'])
            </span>
            <span class="kbsm-sidebar-group__label">{{ $group['label'] }}</span>
            <span class="kbsm-sidebar-group__chevron" aria-hidden="true">
              <svg viewBox="0 0 20 20" focusable="false">
                <path d="M6.5 8 10 11.5 13.5 8l1.4 1.4L10 14.3 5.1 9.4 6.5 8Z" />
              </svg>
            </span>
          </button>

          <div class="kbsm-sidebar-group__panel" data-sidebar-group-panel {{ $groupActive ? '' : 'hidden' }}>
            @foreach($group['modules'] as $module)
              <a
                class="kbsm-sidebar-link kbsm-sidebar-link--child {{ $module['active'] ? 'kbsm-sidebar-link--active' : '' }}"
                href="{{ $module['url'] }}"
                data-sidebar-link
                data-sidebar-module="{{ $module['key'] }}">
                <span class="kbsm-sidebar-link__icon">
                  @include('layout.partials.nav-icon', ['icon' => $module['icon'] ?? 'circle'])
                </span>
                <span class="kbsm-sidebar-link__label">{{ $module['label'] }}</span>
              </a>
            @endforeach
          </div>
        </section>
      @endforeach
    </div>
  </nav>
</aside>

@once
  <script>
    (function () {
      const sidebar = document.querySelector('[data-kbsm-sidebar]');
      if (!sidebar) return;

      const groupKey = sidebar.getAttribute('data-group-storage-key');
      const scrollKey = sidebar.getAttribute('data-scroll-storage-key');
      let prefixes = ['kbsm_sidebar_groups:', 'kbsm_sidebar_scroll:'];

      try {
        prefixes = JSON.parse(sidebar.getAttribute('data-storage-prefixes') || '[]') || prefixes;
      } catch (error) {
        prefixes = ['kbsm_sidebar_groups:', 'kbsm_sidebar_scroll:'];
      }

      const groups = Array.from(sidebar.querySelectorAll('[data-sidebar-group]'));
      const activeGroupKeys = groups
        .filter((group) => group.getAttribute('data-sidebar-group-active') === 'true')
        .map((group) => group.getAttribute('data-sidebar-group'));

      const readSavedGroups = () => {
        try {
          const raw = sessionStorage.getItem(groupKey);
          const parsed = raw ? JSON.parse(raw) : null;
          return Array.isArray(parsed) ? parsed : null;
        } catch (error) {
          return null;
        }
      };

      const saveOpenGroups = () => {
        try {
          const open = groups
            .filter((group) => group.classList.contains('kbsm-sidebar-group--open'))
            .map((group) => group.getAttribute('data-sidebar-group'));
          sessionStorage.setItem(groupKey, JSON.stringify(open));
        } catch (error) {
          // sessionStorage can be unavailable in private mode.
        }
      };

      const setOpen = (group, shouldOpen) => {
        const toggle = group.querySelector('[data-sidebar-group-toggle]');
        const panel = group.querySelector('[data-sidebar-group-panel]');
        group.classList.toggle('kbsm-sidebar-group--open', shouldOpen);
        if (toggle) toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        if (panel) panel.hidden = !shouldOpen;
      };

      const saved = readSavedGroups();
      const existingKeys = groups.map((group) => group.getAttribute('data-sidebar-group'));
      const openKeys = new Set((saved || []).filter((key) => existingKeys.includes(key)));

      activeGroupKeys.forEach((key) => openKeys.add(key));
      groups.forEach((group) => setOpen(group, openKeys.has(group.getAttribute('data-sidebar-group'))));

      groups.forEach((group) => {
        const toggle = group.querySelector('[data-sidebar-group-toggle]');
        if (!toggle) return;

        toggle.addEventListener('click', () => {
          setOpen(group, !group.classList.contains('kbsm-sidebar-group--open'));
          saveOpenGroups();
        });
      });

      const activeLink = sidebar.querySelector('.kbsm-sidebar-link--active');
      window.requestAnimationFrame(() => {
        if (activeLink) {
          activeLink.scrollIntoView({ block: 'nearest' });
          return;
        }

        try {
          const savedScroll = sessionStorage.getItem(scrollKey);
          if (savedScroll !== null) {
            sidebar.scrollTop = parseInt(savedScroll, 10) || 0;
          }
        } catch (error) {
          // Ignore storage failure.
        }
      });

      let scrollTimer = null;
      sidebar.addEventListener('scroll', () => {
        window.clearTimeout(scrollTimer);
        scrollTimer = window.setTimeout(() => {
          try {
            sessionStorage.setItem(scrollKey, String(sidebar.scrollTop));
          } catch (error) {
            // Ignore storage failure.
          }
        }, 120);
      }, { passive: true });

      const clearNavigationState = () => {
        try {
          Object.keys(sessionStorage).forEach((key) => {
            if (prefixes.some((prefix) => key.startsWith(prefix))) {
              sessionStorage.removeItem(key);
            }
          });
        } catch (error) {
          // Ignore storage failure.
        }
      };

      window.KbsmNavigationState = window.KbsmNavigationState || {};
      window.KbsmNavigationState.clear = clearNavigationState;

      document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        const action = form.getAttribute('action') || '';
        if (action.includes('/logout') || action.endsWith('logout')) {
          clearNavigationState();
        }
      });
    })();
  </script>
@endonce
