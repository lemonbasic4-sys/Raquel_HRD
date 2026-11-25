/**
 * Form Validation Utility
 * 
 * Client-side validation with real-time feedback
 * Character counter for text inputs with limits
 */

(function() {
  'use strict';

  // Validation rules
  const validators = {
    required: (value) => value.trim() !== '',
    email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
    tel: (value) => /^[\d\s\-\+\(\)]+$/.test(value),
    number: (value) => !isNaN(value) && value.trim() !== '',
    min: (value, min) => parseFloat(value) >= parseFloat(min),
    max: (value, max) => parseFloat(value) <= parseFloat(max),
    minlength: (value, length) => value.length >= parseInt(length),
    maxlength: (value, length) => value.length <= parseInt(length),
    pattern: (value, pattern) => new RegExp(pattern).test(value)
  };

  // Error messages
  const errorMessages = {
    required: 'This field is required',
    email: 'Please enter a valid email address',
    tel: 'Please enter a valid phone number',
    number: 'Please enter a valid number',
    min: 'Value must be at least {min}',
    max: 'Value must be no more than {max}',
    minlength: 'Must be at least {minlength} characters',
    maxlength: 'Must be no more than {maxlength} characters',
    pattern: 'Please match the required format'
  };

  /**
   * Initialize validation
   */
  function init() {
    // Find all forms with validation
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
      setupFormValidation(form);
    });

    // Setup character counters
    setupCharacterCounters();
  }

  /**
   * Setup validation for a form
   */
  function setupFormValidation(form) {
    const inputs = form.querySelectorAll('input, textarea, select');
    
    inputs.forEach(input => {
      // Validate on blur
      input.addEventListener('blur', () => {
        validateInput(input);
      });

      // Real-time validation on input (with debounce)
      let validationTimer;
      input.addEventListener('input', () => {
        clearTimeout(validationTimer);
        validationTimer = setTimeout(() => {
          validateInput(input);
        }, 500);
      });
    });

    // Validate on submit
    form.addEventListener('submit', (e) => {
      let isValid = true;
      
      inputs.forEach(input => {
        if (!validateInput(input)) {
          isValid = false;
        }
      });

      if (!isValid) {
        e.preventDefault();
        
        // Focus first invalid input
        const firstInvalid = form.querySelector('.is-invalid');
        if (firstInvalid) {
          firstInvalid.focus();
          firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }
    });
  }

  /**
   * Validate a single input
   * @returns {boolean} True if valid
   */
  function validateInput(input) {
    // Skip validation for disabled or hidden inputs
    if (input.disabled || input.type === 'hidden') {
      return true;
    }

    const value = input.value;
    const errors = [];

    // Check required
    if (input.hasAttribute('required') || input.hasAttribute('aria-required')) {
      if (!validators.required(value)) {
        errors.push(errorMessages.required);
      }
    }

    // If empty and not required, skip other validations
    if (value.trim() === '' && !input.hasAttribute('required')) {
      clearError(input);
      return true;
    }

    // Check type-specific validation
    const type = input.getAttribute('type') || input.tagName.toLowerCase();
    
    if (type === 'email' && value && !validators.email(value)) {
      errors.push(errorMessages.email);
    }
    
    if (type === 'tel' && value && !validators.tel(value)) {
      errors.push(errorMessages.tel);
    }
    
    if (type === 'number' && value && !validators.number(value)) {
      errors.push(errorMessages.number);
    }

    // Check min/max for numbers
    if (input.hasAttribute('min')) {
      const min = input.getAttribute('min');
      if (!validators.min(value, min)) {
        errors.push(errorMessages.min.replace('{min}', min));
      }
    }

    if (input.hasAttribute('max')) {
      const max = input.getAttribute('max');
      if (!validators.max(value, max)) {
        errors.push(errorMessages.max.replace('{max}', max));
      }
    }

    // Check minlength/maxlength
    if (input.hasAttribute('minlength')) {
      const minlength = input.getAttribute('minlength');
      if (!validators.minlength(value, minlength)) {
        errors.push(errorMessages.minlength.replace('{minlength}', minlength));
      }
    }

    if (input.hasAttribute('maxlength')) {
      const maxlength = input.getAttribute('maxlength');
      if (!validators.maxlength(value, maxlength)) {
        errors.push(errorMessages.maxlength.replace('{maxlength}', maxlength));
      }
    }

    // Check pattern
    if (input.hasAttribute('pattern')) {
      const pattern = input.getAttribute('pattern');
      if (!validators.pattern(value, pattern)) {
        const customMessage = input.getAttribute('data-pattern-message');
        errors.push(customMessage || errorMessages.pattern);
      }
    }

    // Display results
    if (errors.length > 0) {
      showError(input, errors[0]);
      return false;
    } else {
      clearError(input);
      return true;
    }
  }

  /**
   * Show error message for input
   */
  function showError(input, message) {
    input.classList.add('is-invalid');
    input.classList.remove('is-valid');
    input.setAttribute('aria-invalid', 'true');

    // Find or create error container
    let errorContainer = input.parentElement.querySelector('.form-error');
    
    if (!errorContainer) {
      errorContainer = document.createElement('div');
      errorContainer.className = 'form-error';
      errorContainer.setAttribute('role', 'alert');
      errorContainer.setAttribute('aria-live', 'polite');
      
      const errorId = input.id ? `${input.id}-error` : `error-${Date.now()}`;
      errorContainer.id = errorId;
      input.setAttribute('aria-describedby', errorId);
      
      input.parentElement.appendChild(errorContainer);
    }

    errorContainer.textContent = message;
  }

  /**
   * Clear error message for input
   */
  function clearError(input) {
    input.classList.remove('is-invalid');
    if (!input.closest('form[data-hide-valid-state]')) {
      input.classList.add('is-valid');
    }
    input.setAttribute('aria-invalid', 'false');

    const errorContainer = input.parentElement.querySelector('.form-error');
    if (errorContainer) {
      errorContainer.textContent = '';
    }
  }

  /**
   * Setup character counters for inputs with maxlength
   */
  function setupCharacterCounters() {
    const inputs = document.querySelectorAll('input[maxlength], textarea[maxlength]');
    
    inputs.forEach(input => {
      const maxLength = parseInt(input.getAttribute('maxlength'));
      
      // Create counter element
      const counter = document.createElement('div');
      counter.className = 'char-counter';
      counter.setAttribute('aria-live', 'polite');
      counter.setAttribute('aria-atomic', 'true');
      
      // Insert after input
      input.parentElement.appendChild(counter);

      // Update counter
      const updateCounter = () => {
        const currentLength = input.value.length;
        counter.textContent = `${currentLength} / ${maxLength} characters`;

        // Apply warning/danger classes
        counter.classList.remove('warning', 'danger');
        
        if (currentLength >= maxLength) {
          counter.classList.add('danger');
        } else if (currentLength >= maxLength * 0.9) {
          counter.classList.add('warning');
        }
      };

      // Initial update
      updateCounter();

      // Update on input
      input.addEventListener('input', updateCounter);
    });
  }

  /**
   * Public API: Manually validate a form
   * @param {HTMLFormElement} form - Form to validate
   * @returns {boolean} True if valid
   */
  window.validateForm = function(form) {
    if (typeof form === 'string') {
      form = document.querySelector(form);
    }

    if (!form) return false;

    const inputs = form.querySelectorAll('input, textarea, select');
    let isValid = true;
    
    inputs.forEach(input => {
      if (!validateInput(input)) {
        isValid = false;
      }
    });

    return isValid;
  };

  /**
   * Public API: Clear all validation errors in a form
   * @param {HTMLFormElement} form - Form to clear
   */
  window.clearFormValidation = function(form) {
    if (typeof form === 'string') {
      form = document.querySelector(form);
    }

    if (!form) return;

    const inputs = form.querySelectorAll('input, textarea, select');
    inputs.forEach(clearError);
  };

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
