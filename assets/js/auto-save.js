/**
 * Auto-Save Utility
 * 
 * Automatically saves form data after 2 seconds of inactivity
 * Stores unsaved data in localStorage as backup
 */

(function() {
  'use strict';

  // Configuration
  const SAVE_DELAY = 2000; // 2 seconds
  const STORAGE_KEY_PREFIX = 'autosave_';
  const SUCCESS_MESSAGE_DURATION = 2000; // 2 seconds

  // State
  let saveTimers = {};
  let toastContainer = null;
  let isUnloading = false;

  /**
   * Initialize auto-save on forms with data-autosave attribute
   */
  function init() {
    // Create toast container if it doesn't exist
    if (!toastContainer) {
      toastContainer = document.createElement('div');
      toastContainer.className = 'toast-container';
      toastContainer.setAttribute('aria-live', 'polite');
      toastContainer.setAttribute('aria-atomic', 'true');
      document.body.appendChild(toastContainer);
    }

    // Find all forms with auto-save enabled
    const autoSaveForms = document.querySelectorAll('[data-autosave]');
    
    autoSaveForms.forEach(form => {
      const formId = form.id || form.getAttribute('data-autosave');
      
      if (!formId) {
        console.warn('Auto-save form must have an id or data-autosave identifier');
        return;
      }

      // Restore any previously saved data
      restoreFormData(form, formId);

      // Listen for input changes
      form.addEventListener('input', (e) => {
        handleInput(form, formId, e.target);
      });

      // Listen for change events (for select, radio, checkbox)
      form.addEventListener('change', (e) => {
        handleInput(form, formId, e.target);
      });
    });
  }

  /**
   * Handle input changes and trigger debounced save
   */
  function handleInput(form, formId, target) {
    // Clear existing timer for this form
    if (saveTimers[formId]) {
      clearTimeout(saveTimers[formId]);
    }

    // Save to localStorage immediately as backup
    saveToLocalStorage(form, formId);

    // Set new timer for server save
    saveTimers[formId] = setTimeout(() => {
      saveToServer(form, formId);
    }, SAVE_DELAY);
  }

  /**
   * Save form data to localStorage
   */
  function saveToLocalStorage(form, formId) {
    try {
      const formData = new FormData(form);
      const data = {};
      
      for (let [key, value] of formData.entries()) {
        // Session security tokens must always come from the current server page.
        if (key === 'csrf_token') continue;
        data[key] = value;
      }

      localStorage.setItem(STORAGE_KEY_PREFIX + formId, JSON.stringify({
        data: data,
        timestamp: Date.now()
      }));
    } catch (error) {
      console.error('Failed to save to localStorage:', error);
    }
  }

  /**
   * Restore form data from localStorage
   */
  function restoreFormData(form, formId) {
    try {
      const saved = localStorage.getItem(STORAGE_KEY_PREFIX + formId);
      
      if (!saved) return;

      const { data, timestamp } = JSON.parse(saved);
      // Older drafts included the previous login's token. Remove it without
      // discarding the employee's saved answers or touching the fresh form token.
      if (Object.prototype.hasOwnProperty.call(data, 'csrf_token')) {
        delete data.csrf_token;
        localStorage.setItem(STORAGE_KEY_PREFIX + formId, JSON.stringify({data, timestamp}));
      }
      
      // Only restore if saved within last 7 days
      const sevenDaysAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
      if (timestamp < sevenDaysAgo) {
        localStorage.removeItem(STORAGE_KEY_PREFIX + formId);
        return;
      }

      // Restore form values
      Object.keys(data).forEach(key => {
        if (key === 'csrf_token') return;
        const input = form.elements[key];
        if (input) {
          if (input.type === 'checkbox') {
            input.checked = data[key] === 'on' || data[key] === input.value;
          } else if (input.type === 'radio') {
            const radio = form.querySelector(`input[name="${key}"][value="${data[key]}"]`);
            if (radio) radio.checked = true;
          } else {
            input.value = data[key];
          }
        }
      });

      // Show restore notification
      showToast('Your previous work has been restored', 'info');
    } catch (error) {
      console.error('Failed to restore from localStorage:', error);
    }
  }

  /**
   * Save form data to server via AJAX
   */
  function saveToServer(form, formId) {
    const formData = new FormData(form);
    const saveEndpoint = form.getAttribute('data-autosave-endpoint') || form.action;

    // Add auto-save flag
    formData.append('auto_save', '1');

    fetch(saveEndpoint, {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        // Clear localStorage backup on successful save
        localStorage.removeItem(STORAGE_KEY_PREFIX + formId);
        
        // Show success message
        showToast('Changes saved automatically', 'success');
      } else {
        throw new Error(data.message || 'Save failed');
      }
    })
    .catch(error => {
      if (isUnloading) return;
      console.error('Auto-save failed:', error);
      showToast('Failed to save changes. Your work is saved locally.', 'error', 0);
    });
  }

  /**
   * Show toast notification
   * @param {string} message - Message to display
   * @param {string} type - Toast type (success, error, warning, info)
   * @param {number} duration - Duration in ms (0 = persistent)
   */
  function showToast(message, type = 'success', duration = SUCCESS_MESSAGE_DURATION) {
    // Keep mobile screens clear of persistent auto-save errors.
    if (window.matchMedia('(max-width: 767px)').matches && duration <= 0) {
      duration = 6000;
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'status');
    
    const iconMap = {
      success: '✓',
      error: '⚠',
      warning: '⚠',
      info: 'ℹ'
    };

    toast.innerHTML = `
      <span class="toast-icon" aria-hidden="true">${iconMap[type]}</span>
      <div class="toast-content">
        <p class="toast-message">${message}</p>
      </div>
      <button class="toast-close" aria-label="Close notification">×</button>
    `;

    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', () => {
      removeToast(toast);
    });

    toastContainer.appendChild(toast);

    // Auto-remove success messages
    if (duration > 0) {
      setTimeout(() => {
        removeToast(toast);
      }, duration);
    }
  }

  /**
   * Remove toast notification with animation
   */
  function removeToast(toast) {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(100%)';
    setTimeout(() => {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, 300);
  }

  /**
   * Clear auto-save data for a form
   * Call this after successful final submission
   */
  window.clearAutoSaveData = function(formId) {
    localStorage.removeItem(STORAGE_KEY_PREFIX + formId);
  };

  /**
   * Manually trigger save
   * Useful for explicit save buttons
   */
  window.triggerAutoSave = function(formId) {
    const form = document.getElementById(formId) || 
                  document.querySelector(`[data-autosave="${formId}"]`);
    
    if (form) {
      saveToServer(form, formId);
    }
  };

  /**
   * Cancel all pending auto-save timers and mark as unloading.
   * Call this immediately before a final form submission so no
   * in-flight AJAX fires and shows a spurious "Failed" toast.
   */
  window.cancelAutoSave = function() {
    isUnloading = true;
    Object.keys(saveTimers).forEach(id => {
      clearTimeout(saveTimers[id]);
      delete saveTimers[id];
    });
  };

  window.addEventListener('beforeunload', () => {
    isUnloading = true;
  });

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
