// KBSM sidebar drawer control.
(function () {
  var sidenav = document.querySelector("[data-kbsm-sidebar]") || document.querySelector("aside");
  var sidenavTrigger = document.querySelector("[sidenav-trigger]");
  var sidenavCloseButton = document.querySelector("[sidenav-close]");
  var backdrop = document.querySelector("[data-kbsm-sidebar-backdrop]");

  if (!sidenav || !sidenavTrigger) {
    return;
  }

  var burger = sidenavTrigger.firstElementChild;
  var topBread = burger ? burger.firstElementChild : null;
  var bottomBread = burger ? burger.lastElementChild : null;
  var desktopQuery = window.matchMedia("(min-width: 1200px)");
  var backdropHideTimer = null;
  var isOpen = false;

  function isRtlPage() {
    return typeof page !== "undefined" && page === "rtl";
  }

  function setBodyLocked(locked) {
    document.body.classList.toggle("kbsm-sidebar-drawer-open", locked);
  }

  function setBackdropVisible(visible) {
    if (!backdrop) {
      return;
    }

    window.clearTimeout(backdropHideTimer);

    if (visible) {
      backdrop.hidden = false;
      window.requestAnimationFrame(function () {
        backdrop.classList.add("kbsm-sidebar-backdrop--visible");
      });
      return;
    }

    backdrop.classList.remove("kbsm-sidebar-backdrop--visible");
    backdropHideTimer = window.setTimeout(function () {
      if (!isOpen) {
        backdrop.hidden = true;
      }
    }, 180);
  }

  function setBurgerActive(active) {
    if (!topBread || !bottomBread) {
      return;
    }

    if (isRtlPage()) {
      topBread.classList.toggle("-translate-x-[5px]", active);
      bottomBread.classList.toggle("-translate-x-[5px]", active);
      return;
    }

    topBread.classList.toggle("translate-x-[5px]", active);
    bottomBread.classList.toggle("translate-x-[5px]", active);
  }

  function openSidebar() {
    if (desktopQuery.matches || isOpen) {
      return;
    }

    isOpen = true;
    sidenav.classList.add("translate-x-0", "shadow-soft-xl");
    if (sidenavCloseButton) {
      sidenavCloseButton.classList.remove("hidden");
    }
    sidenavTrigger.setAttribute("aria-expanded", "true");
    sidenav.setAttribute("data-drawer-open", "true");
    setBackdropVisible(true);
    setBodyLocked(true);
    setBurgerActive(true);
  }

  function closeSidebar() {
    isOpen = false;
    sidenav.classList.remove("translate-x-0");
    if (sidenavCloseButton) {
      sidenavCloseButton.classList.add("hidden");
    }
    sidenavTrigger.setAttribute("aria-expanded", "false");
    sidenav.removeAttribute("data-drawer-open");
    setBackdropVisible(false);
    setBodyLocked(false);
    setBurgerActive(false);
  }

  function toggleSidebar(event) {
    if (event) {
      event.preventDefault();
    }

    if (isOpen) {
      closeSidebar();
      return;
    }

    openSidebar();
  }

  sidenavTrigger.addEventListener("click", toggleSidebar);

  if (sidenavCloseButton) {
    sidenavCloseButton.addEventListener("click", function (event) {
      event.preventDefault();
      closeSidebar();
    });
  }

  if (backdrop) {
    backdrop.addEventListener("click", closeSidebar);
  }

  window.addEventListener("click", function (event) {
    if (
      !desktopQuery.matches &&
      isOpen &&
      !sidenav.contains(event.target) &&
      !sidenavTrigger.contains(event.target) &&
      (!backdrop || event.target !== backdrop)
    ) {
      closeSidebar();
    }
  });

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && isOpen) {
      closeSidebar();
    }
  });

  function handleViewportChange() {
    closeSidebar();
  }

  if (desktopQuery.addEventListener) {
    desktopQuery.addEventListener("change", handleViewportChange);
  } else if (desktopQuery.addListener) {
    desktopQuery.addListener(handleViewportChange);
  }

  handleViewportChange();
})();
