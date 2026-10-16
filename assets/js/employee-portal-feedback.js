/**
 * Employee Portal Visual Feedback System
 * Tasks 22.1–22.3: Touch feedback, loading indicators, toast messages
 * Tasks 23.1–23.4: Accessibility — focus, keyboard nav, ARIA
 *
 * Requirements: 15.1–15.7, 20.1–20.8
 */

(function () {
  'use strict';

  /* ================================================================
     ARIA LIVE ANNOUNCER (Req 20.8)
     Screen readers announce dynamic changes via this hidden region
  ================================================================ */
  var announcer = document.getElementById('ep-announcer');
  if (!announcer) {
    announcer = document.createElement('div');
    announcer.id = 'ep-announcer';
    announcer.setAttribute('aria-live', 'polite');
    announcer.setAttribute('aria-atomic', 'true');
    document.body.appendChild(announcer);
  }

  function announce(message, urgency) {
    announcer.setAttribute('aria-live', urgency === 'assertive' ? 'assertive' : 'polite');
    announcer.textContent = '';
    // Small delay so screen reader picks up the change
    setTimeout(function () {
      announcer.textContent = message;
    }, 50);
  }

  /* ================================================================
     EP TOAST NOTIFICATION SYSTEM (Req 15.3, 15.4)
     showToast(message, type, duration)
       type: 'success' | 'error' | 'warning' | 'info'
       duration: ms (0 = persistent, must be dismissed)
     Uses .ep-toast-* namespace to avoid Bootstrap 5 .toast conflict
  ================================================================ */
  var toastContainer = null;

  function getToastContainer() {
    if (!toastContainer) {
      toastContainer = document.querySelector('.ep-toast-container');
    }
    if (!toastContainer) {
      toastContainer = document.createElement('div');
      toastContainer.className = 'ep-toast-container';
      toastContainer.setAttribute('aria-live', 'polite');
      toastContainer.setAttribute('aria-atomic', 'false');
      toastContainer.setAttribute('role', 'region');
      toastContainer.setAttribute('aria-label', 'Notifications');
      document.body.appendChild(toastContainer);
    }
    return toastContainer;
  }

  var iconMap = {
    success: '✓',
    error: '⚠',
    warning: '⚠',
    info: 'ℹ'
  };

  var labelMap = {
    success: 'Success',
    error: 'Error',
    warning: 'Warning',
    info: 'Information'
  };

  var labelMapShort = {
    success: 'Success',
    error: 'Error',
    warning: 'Warning',
    info: 'Info'
  };

  function showToast(message, type, duration) {
    type = type || 'success';
    duration = (duration === undefined) ? (type === 'error' ? 0 : 3500) : duration;
    // Mobile notifications should always clear themselves, including errors
    // that are persistent on desktop when their duration is zero.
    if (window.matchMedia('(max-width: 767px)').matches && duration <= 0) {
      duration = 6000;
    }

    var toast = document.createElement('div');
    toast.className = 'ep-toast ep-toast-' + type;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    toast.setAttribute('aria-label', labelMap[type] + ': ' + message);
    toast.innerHTML =
      '<span class="ep-toast-icon" aria-hidden="true">' + (iconMap[type] || '•') + '</span>' +
      '<div class="ep-toast-content">' +
      '<span class="ep-toast-label">' + (labelMapShort[type] || type) + '</span>' +
      '<span class="ep-toast-message">' + message + '</span>' +
      '</div>' +
      '<button class="ep-toast-close" aria-label="Dismiss notification">×</button>';

    toast.querySelector('.ep-toast-close').addEventListener('click', function () {
      removeToast(toast);
    });

    getToastContainer().appendChild(toast);

    // Announce to screen readers
    announce(labelMap[type] + ': ' + message, type === 'error' ? 'assertive' : 'polite');

    if (duration > 0) {
      setTimeout(function () { removeToast(toast); }, duration);
    }

    return toast;
  }

  function removeToast(toast) {
    if (!toast || !toast.parentNode) return;

    var isMobile = window.matchMedia('(max-width: 767px)').matches;

    if (isMobile) {
      // Dynamic Island collapse-up
      toast.style.transition = 'transform 0.3s cubic-bezier(.55,0,1,.45), opacity 0.25s ease';
      toast.style.transformOrigin = 'top center';
      toast.style.transform = 'scaleX(0.25) scaleY(0.35) translateY(-14px)';
      toast.style.opacity = '0';
    } else {
      // Desktop slide-off to the right
      toast.style.transition = 'transform 0.28s ease-in, opacity 0.22s ease';
      toast.style.transform = 'translateX(calc(100% + 28px))';
      toast.style.opacity = '0';
    }

    setTimeout(function () {
      if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, 320);
  }

  // Expose globally
  window.showToast = function (message, type, duration) {
    showToast(message, type, duration);
    if (type === 'success') {
      playUiSound('success');
    } else if (type === 'error' || type === 'warning') {
      playUiSound('warning');
    }
  };
  window.epAnnounce = announce;

  /* ================================================================
     WEB AUDIO SYNTHESIZER FOR UI SOUND EFFECTS
     Pure browser-synthesized audio cues (Zero external MP3 download delay)
  ================================================================ */
  var audioCtx = null;

  function getAudioContext() {
    if (!audioCtx && typeof window !== 'undefined' && (window.AudioContext || window.webkitAudioContext)) {
      var AudioContextClass = window.AudioContext || window.webkitAudioContext;
      audioCtx = new AudioContextClass();
    }
    if (audioCtx && audioCtx.state === 'suspended') {
      audioCtx.resume();
    }
    return audioCtx;
  }

  function playUiSound(type) {
    if (typeof localStorage !== 'undefined' && localStorage.getItem('ep_sound_disabled') === 'true') return;
    var ctx = getAudioContext();
    if (!ctx) return;

    try {
      var now = ctx.currentTime;
      if (type === 'tap') {
        // Soft UI tap click (600Hz -> 250Hz in 35ms)
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(600, now);
        osc.frequency.exponentialRampToValueAtTime(250, now + 0.035);
        gain.gain.setValueAtTime(0.06, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.035);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(now);
        osc.stop(now + 0.035);
      } else if (type === 'success') {
        // Warm 2-note success chime (C5 -> G5)
        var notes = [523.25, 783.99];
        notes.forEach(function (freq, i) {
          var osc = ctx.createOscillator();
          var gain = ctx.createGain();
          var noteStart = now + (i * 0.08);
          osc.type = 'sine';
          osc.frequency.setValueAtTime(freq, noteStart);
          gain.gain.setValueAtTime(0.08, noteStart);
          gain.gain.exponentialRampToValueAtTime(0.001, noteStart + 0.18);
          osc.connect(gain);
          gain.connect(ctx.destination);
          osc.start(noteStart);
          osc.stop(noteStart + 0.18);
        });
      } else if (type === 'warning' || type === 'error') {
        // Soft low warning tone (350Hz -> 200Hz)
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(350, now);
        osc.frequency.exponentialRampToValueAtTime(200, now + 0.12);
        gain.gain.setValueAtTime(0.08, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.12);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(now);
        osc.stop(now + 0.12);
      } else if (type === 'notify') {
        // Double ping alert (880Hz -> 1320Hz)
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, now);
        osc.frequency.setValueAtTime(1320, now + 0.06);
        gain.gain.setValueAtTime(0.05, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.14);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(now);
        osc.stop(now + 0.14);
      } else if (type === 'step') {
        // Soft step transition sweep (400Hz -> 650Hz)
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(400, now);
        osc.frequency.exponentialRampToValueAtTime(650, now + 0.06);
        gain.gain.setValueAtTime(0.05, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.06);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(now);
        osc.stop(now + 0.06);
      }
    } catch (e) { }
  }

  window.playUiSound = playUiSound;

  /* ================================================================
     SOUND EFFECTS TOGGLE & STATUS MANAGEMENT
  ================================================================ */
  function updateSoundToggleUI() {
    var isMuted = typeof localStorage !== 'undefined' && localStorage.getItem('ep_sound_disabled') === 'true';
    var toggleBtns = document.querySelectorAll('#soundToggleBtn, .sound-toggle-btn');
    toggleBtns.forEach(function (btn) {
      var icon = btn.querySelector('.sound-icon, i');
      var badge = btn.querySelector('.sound-status-badge');
      if (isMuted) {
        if (icon) icon.className = 'fas fa-volume-mute me-2 sound-icon text-muted';
        if (badge) {
          badge.className = 'badge bg-secondary sound-status-badge';
          badge.textContent = 'OFF';
        }
      } else {
        if (icon) icon.className = 'fas fa-volume-up me-2 sound-icon text-success';
        if (badge) {
          badge.className = 'badge bg-success sound-status-badge';
          badge.textContent = 'ON';
        }
      }
    });
  }

  window.toggleUiSound = function () {
    var currentlyDisabled = typeof localStorage !== 'undefined' && localStorage.getItem('ep_sound_disabled') === 'true';
    if (currentlyDisabled) {
      localStorage.removeItem('ep_sound_disabled');
      playUiSound('success');
    } else {
      localStorage.setItem('ep_sound_disabled', 'true');
    }
    updateSoundToggleUI();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateSoundToggleUI);
  } else {
    updateSoundToggleUI();
  }

  /* ================================================================
     HAPTIC FEEDBACK (Req 15.7) & UI SOUND EFFECTS
     Vibrates device + plays subtle audio feedback on navigation and key actions
  ================================================================ */
  function vibrate(pattern) {
    if (typeof navigator !== 'undefined' && navigator.vibrate) {
      try {
        navigator.vibrate(pattern || 15);
      } catch (err) { }
    }
  }

  // Broad selector for all interactive elements across Admin and HRIS pages
  var hapticSelector = [
    '.sidebar-nav a', '.nav-item', '.hr-nav-item', '.nav-link',
    '.btn', 'button', 'a.btn', 'input[type="submit"]', 'input[type="button"]',
    '.dropdown-item', '.notification-btn', '#notificationBtn', '#mobileGearBtn',
    '.action-btn', '.btn-action', '[data-bs-toggle]', '.page-link', '.filter-tab',
    '.haptic-feedback', '[data-haptic]'
  ].join(', ');

  document.addEventListener('pointerdown', function (e) {
    var el = e.target.closest(hapticSelector);
    if (el) {
      var pattern = el.dataset.haptic ? parseInt(el.dataset.haptic, 10) : 15;
      vibrate(pattern);
      if (el.classList.contains('notification-btn') || el.id === 'notificationBtn' || el.classList.contains('notification-item')) {
        playUiSound('notify');
      } else if (el.classList.contains('btn-danger') || el.classList.contains('text-danger') || el.dataset.action === 'delete') {
        playUiSound('warning');
      } else if (el.type === 'submit' || el.classList.contains('btn-primary') || el.classList.contains('btn-success')) {
        playUiSound('success');
      } else if (el.classList.contains('page-link') || el.classList.contains('filter-tab') || (el.classList.contains('nav-link') && el.closest('.nav-tabs'))) {
        playUiSound('step');
      } else {
        playUiSound('tap');
      }
    }
  }, { passive: true });

  // Play tap sound on checkbox, radio button, and select field changes
  document.addEventListener('change', function (e) {
    if (e.target && (e.target.matches('input[type="checkbox"], input[type="radio"], select.form-select, select.form-control'))) {
      playUiSound('tap');
    }
  }, { passive: true });

  /* ================================================================
     FORM SUBMIT LOADING STATE (Req 15.2)
     Disables submit button + shows spinner on form submit
  ================================================================ */
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function () {
      var submitBtns = form.querySelectorAll('[type="submit"]');
      submitBtns.forEach(function (btn) {
        // Only disable if not already loading (prevents double-fire)
        if (!btn.classList.contains('is-loading')) {
          btn.classList.add('is-loading');
          btn.setAttribute('aria-busy', 'true');
          setTimeout(function () {
            btn.disabled = true;
          }, 1);
        }
      });
    });
  });

  /* ================================================================
     SKIP NAVIGATION (Req 20.4)
     Ensure skip-nav link is functional
  ================================================================ */
  var skipLink = document.querySelector('.skip-navigation');
  if (skipLink) {
    skipLink.addEventListener('click', function (e) {
      var target = document.querySelector(skipLink.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.setAttribute('tabindex', '-1');
        target.focus();
      }
    });
  }

  /* ================================================================
     KEYBOARD NAVIGATION ENHANCEMENTS (Req 20.2, 20.3)
     Enter/Space on non-button interactive elements
  ================================================================ */
  document.addEventListener('keydown', function (e) {
    var el = e.target;

    // Enter or Space activates div/span with role=button or tabindex
    if ((e.key === 'Enter' || e.key === ' ') &&
      (el.getAttribute('role') === 'button' ||
        (el.getAttribute('tabindex') === '0' && !['INPUT', 'BUTTON', 'A', 'SELECT', 'TEXTAREA'].includes(el.tagName)))) {
      e.preventDefault();
      el.click();
    }

    // Escape closes any open dropdown or modal
    if (e.key === 'Escape') {
      var openDropdown = document.querySelector('.dropdown-menu.show');
      if (openDropdown) {
        var toggle = openDropdown.previousElementSibling;
        openDropdown.classList.remove('show');
        if (toggle) toggle.focus();
      }
    }
  });

  /* ================================================================
     LAZY IMAGE LOADING POLYFILL (Req 19.3)
     IntersectionObserver for browsers lacking native lazy loading
  ================================================================ */
  if ('IntersectionObserver' in window) {
    var imgObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var img = entry.target;
          if (img.dataset.src) {
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
          }
          imgObserver.unobserve(img);
        }
      });
    }, { rootMargin: '200px' });

    document.querySelectorAll('img[data-src]').forEach(function (img) {
      imgObserver.observe(img);
    });
  } else {
    // Fallback: load all data-src images immediately
    document.querySelectorAll('img[data-src]').forEach(function (img) {
      img.src = img.dataset.src;
    });
  }

  /* ================================================================
     ARIA CURRENT PAGE — auto-set on navigation links (Req 20.6)
  ================================================================ */
  var currentPath = window.location.pathname.split('/').pop() || 'index.php';
  document.querySelectorAll('.sidebar-link, .bottom-nav-item').forEach(function (link) {
    var href = link.getAttribute('href') || '';
    var page = href.split('/').pop();
    if (page && page === currentPath) {
      link.setAttribute('aria-current', 'page');
      link.classList.add('active');
    }
  });

  /* ================================================================
     NOTIFICATION BADGE LIVE UPDATE (Req 9.1, 9.2)
     Updates badge aria-label when count changes
  ================================================================ */
  var badgeDot = document.querySelector('.badge-dot');
  if (badgeDot) {
    var count = parseInt(badgeDot.textContent) || 0;
    if (count > 0) {
      badgeDot.setAttribute('aria-label', count + ' unread notification' + (count !== 1 ? 's' : ''));
    }
  }

  /* ================================================================
     AUTO-DISMISS SUCCESS ALERTS & AUDIO FEEDBACK (Req 15.3)
     Auto-plays audio chime on page load and auto-dismisses success banners
  ================================================================ */
  var successAlerts = document.querySelectorAll('.alert-success, .alert-ep-success');
  if (successAlerts.length > 0) {
    setTimeout(function () { playUiSound('success'); }, 150);
    successAlerts.forEach(function (alert) {
      setTimeout(function () {
        alert.style.transition = 'opacity 0.4s ease-out';
        alert.style.opacity = '0';
        setTimeout(function () {
          if (alert.parentNode) alert.style.display = 'none';
        }, 420);
      }, 3500);
    });
  }

  var dangerAlerts = document.querySelectorAll('.alert-danger, .alert-warning, .alert-ep-error');
  if (dangerAlerts.length > 0) {
    setTimeout(function () { playUiSound('warning'); }, 150);
  }

  /* ================================================================
     DISMISSIBLE ERROR ALERTS (Req 15.4)
     Error messages stay visible until user dismisses them
  ================================================================ */
  document.querySelectorAll('.alert-ep-error, .alert-ep-dismiss').forEach(function (btn) {
    if (btn.classList.contains('alert-ep-dismiss')) {
      btn.addEventListener('click', function () {
        var alert = btn.closest('.alert-ep-error');
        if (alert) {
          alert.style.transition = 'opacity 0.3s';
          alert.style.opacity = '0';
          setTimeout(function () { if (alert.parentNode) alert.parentNode.removeChild(alert); }, 320);
        }
      });
    }
  });

  /* ================================================================
     SEMANTIC HEADING ORDER CHECK (dev-only, console warn)
  ================================================================ */
  if (window.location.hostname === 'localhost') {
    var headings = Array.from(document.querySelectorAll('h1,h2,h3,h4,h5,h6'));
    var prevLevel = 0;
    headings.forEach(function (h) {
      var level = parseInt(h.tagName[1]);
      if (level > prevLevel + 1) {
        console.warn('[A11y] Heading level skipped:', h.tagName, h.textContent.trim().slice(0, 40));
      }
      prevLevel = level;
    });
  }

  /* ================================================================
     FLOATING BACK TO TOP BUTTON (Employee Portal & Self-Rating)
  ================================================================ */
  function initEpBackToTop() {
    var floatBtn = document.getElementById('epFloatingTopBtn');
    if (!floatBtn) {
      floatBtn = document.createElement('button');
      floatBtn.id = 'epFloatingTopBtn';
      floatBtn.className = 'ep-floating-top-btn';
      floatBtn.type = 'button';
      floatBtn.setAttribute('aria-label', 'Back to top');
      floatBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
      document.body.appendChild(floatBtn);
    }

    window.addEventListener('scroll', function () {
      if (window.scrollY > 250) {
        floatBtn.classList.add('visible');
      } else {
        floatBtn.classList.remove('visible');
      }
    }, { passive: true });

    floatBtn.addEventListener('click', function (e) {
      e.preventDefault();
      vibrate(15);
      playUiSound('tap');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEpBackToTop);
  } else {
    initEpBackToTop();
  }

})();
