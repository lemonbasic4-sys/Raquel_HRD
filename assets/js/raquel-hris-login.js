'use strict';

/**
 * raquel-hris-login.js
 * Raquel Pawnshop HRIS — Login Page Scripts
 * Extracted & modernised from raquel-hris-login.html
 */

document.addEventListener('DOMContentLoaded', function () {

  /* ─────────────────────────────────────────
     Toggle password visibility
     Called via onclick="tpw('inputId','iconId')"
  ───────────────────────────────────────── */
  function togglePasswordVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (!input || !icon) return;

    if (input.type === 'password') {
      input.type     = 'text';
      icon.className = 'ti ti-eye-off';
    } else {
      input.type     = 'password';
      icon.className = 'ti ti-eye';
    }
  }

  // Expose to inline onclick handlers in HTML
  window.tpw = togglePasswordVisibility;

  /* ─────────────────────────────────────────
     Panel switching — Show ESS portal
  ───────────────────────────────────────── */
  window.showESS = function () {
    document.getElementById('track').classList.add('show-ess');
    document.body.classList.add('show-ess');

    // Remember choice across page refreshes
    localStorage.setItem('loginPortal', 'ess');

    // Update mobile tab active states
    ['mobTabHRIS', 'mobTabHRIS2'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) { el.classList.remove('active-gold'); }
    });
    ['mobTabESS', 'mobTabESS2'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) { el.classList.add('active-green'); }
    });

  };

  /* ─────────────────────────────────────────
     Panel switching — Show HRIS portal
  ───────────────────────────────────────── */
  window.showHRIS = function () {
    document.getElementById('track').classList.remove('show-ess');
    document.body.classList.remove('show-ess');

    // Remember choice across page refreshes
    localStorage.setItem('loginPortal', 'hris');

    // Update mobile tab active states
    ['mobTabESS', 'mobTabESS2'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) { el.classList.remove('active-green'); }
    });
    ['mobTabHRIS', 'mobTabHRIS2'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) { el.classList.add('active-gold'); }
    });

  };

  /* ─────────────────────────────────────────
     Restore last active portal on page load
  ───────────────────────────────────────── */
  if (localStorage.getItem('loginPortal') === 'ess') {
    window.showESS();
  }

  /* ─────────────────────────────────────────
     HRIS form — client-side validation
  ───────────────────────────────────────── */
  const hrisForm = document.getElementById('hrisForm');
  if (window.innerWidth > 640) {
    document.querySelectorAll('.login-error').forEach(function (toast) {
      document.body.appendChild(toast);
    });
  }

  window.closeLoginToast = function (toast) {
    if (!toast || toast.style.display === 'none' || toast.classList.contains('is-closing')) return;
    if (window.innerWidth <= 640 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      toast.style.display = 'none';
      return;
    }
    toast.classList.add('is-closing');
    toast.addEventListener('animationend', function () {
      toast.style.display = 'none';
      toast.classList.remove('is-closing');
    }, { once: true });
  };

  const toastTimers = new WeakMap();
  function scheduleLoginToast(toast) {
    if (window.innerWidth <= 640) return;
    clearTimeout(toastTimers.get(toast));
    toastTimers.set(toast, setTimeout(function () {
      window.closeLoginToast(toast);
    }, 15000));
  }

  document.querySelectorAll('.login-error').forEach(function (toast) {
    if (toast.style.display !== 'none') scheduleLoginToast(toast);
  });

  document.querySelectorAll('.login-error-close').forEach(function (button) {
    button.addEventListener('click', function () {
      window.closeLoginToast(button.closest('.login-error'));
    });
  });

  if (hrisForm) {
    const hrisUser = document.getElementById('hris-username');
    const hrisPass = document.getElementById('hp');
    const hrisErr  = document.getElementById('hrisError');

    hrisForm.addEventListener('submit', function (e) {
      const username = hrisUser.value.trim();
      const password = hrisPass.value;

      if (!username || !password) {
        e.preventDefault();
        document.getElementById('hrisErrorMsg').textContent =
          'Please fill in both username and password.';
        hrisErr.style.display = 'flex';
        hrisErr.classList.remove('is-closing');
        scheduleLoginToast(hrisErr);
        if (window.innerWidth <= 640) hrisErr.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });

    // Clear error messages when user starts typing
    if (hrisUser) {
      hrisUser.addEventListener('input', function () {
        hrisErr.style.display = 'none';
      });
    }
    if (hrisPass) {
      hrisPass.addEventListener('input', function () {
        hrisErr.style.display = 'none';
      });
    }
  }

  /* ─────────────────────────────────────────
     ESS form — client-side validation (added)
  ───────────────────────────────────────── */
  const essForm = document.getElementById('essForm');
  if (essForm) {
    const essUser = document.getElementById('ess-username');
    const essPass = document.getElementById('ep');
    const essErr  = document.getElementById('essError');

    essForm.addEventListener('submit', function (e) {
      const username = essUser.value.trim();
      const password = essPass.value;

      if (!username || !password) {
        e.preventDefault();
        document.getElementById('essErrorMsg').textContent =
          'Please fill in both username and password.';
        essErr.style.display = 'flex';
        essErr.classList.remove('is-closing');
        scheduleLoginToast(essErr);
        if (window.innerWidth <= 640) essErr.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });

    // Clear error messages when user starts typing
    if (essUser) {
      essUser.addEventListener('input', function () {
        essErr.style.display = 'none';
      });
    }
    if (essPass) {
      essPass.addEventListener('input', function () {
        essErr.style.display = 'none';
      });
    }
  }

});
