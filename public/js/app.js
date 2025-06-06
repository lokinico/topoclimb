/**
 * TopoclimbCH - Application JavaScript modernisée
 * Compatible avec le nouveau design system
 */

// Configuration globale modernisée
window.TopoclimbCH = {
  config: {
    baseUrl: window.location.origin,
    apiUrl: window.location.origin + '/api',
    debug: document.documentElement.hasAttribute('data-debug'),
    version: '2.0.0'
  },

  // Composants chargés
  components: {},

  // État de l'application
  state: {
    isLoading: false,
    currentUser: null,
    theme: 'modern'
  },

  // Utilitaires modernisés
  utils: {
    /**
     * Effectuer une requête AJAX moderne avec gestion d'erreur avancée
     */
    async request(url, options = {}) {
      const defaults = {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        credentials: 'same-origin'
      };

      const config = { ...defaults, ...options };

      // Ajouter le token CSRF si disponible
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
        document.querySelector('input[name="csrf_token"]')?.value;
      if (csrfToken) {
        config.headers['X-CSRF-Token'] = csrfToken;
      }

      try {
        TopoclimbCH.ui.showLoading();

        const response = await fetch(url, config);

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const contentType = response.headers.get('Content-Type');
        let data;

        if (contentType && contentType.includes('application/json')) {
          data = await response.json();
        } else {
          data = await response.text();
        }

        return {
          success: true,
          data,
          status: response.status,
          headers: response.headers
        };

      } catch (error) {
        console.error('Request Error:', error);

        // Afficher l'erreur à l'utilisateur
        if (window.showFlashMessage) {
          window.showFlashMessage('error', `Erreur: ${error.message}`);
        }

        return {
          success: false,
          error: error.message,
          status: 0
        };
      } finally {
        TopoclimbCH.ui.hideLoading();
      }
    },

    /**
     * Débounce optimisé pour les recherches
     */
    debounce(func, wait = 300, immediate = false) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          timeout = null;
          if (!immediate) func(...args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func(...args);
      };
    },

    /**
     * Throttle pour optimiser les événements de scroll
     */
    throttle(func, wait = 100) {
      let timeout;
      return function executedFunction(...args) {
        if (!timeout) {
          timeout = setTimeout(() => {
            timeout = null;
            func(...args);
          }, wait);
        }
      };
    },

    /**
     * Formatage des dates en français
     */
    formatDate(date, options = {}) {
      const defaults = {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      };
      const config = { ...defaults, ...options };

      return new Intl.DateTimeFormat('fr-CH', config).format(new Date(date));
    },

    /**
     * Formatage des nombres
     */
    formatNumber(number, decimals = 0) {
      return new Intl.NumberFormat('fr-CH', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
      }).format(number);
    },

    /**
     * Validation email moderne
     */
    validateEmail(email) {
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return re.test(email);
    },

    /**
     * Générer un ID unique
     */
    generateId(prefix = 'tc') {
      return `${prefix}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    },

    /**
     * Détecter les fonctionnalités du navigateur
     */
    getCapabilities() {
      return {
        touch: 'ontouchstart' in window,
        geolocation: 'geolocation' in navigator,
        webShare: 'share' in navigator,
        clipboard: 'clipboard' in navigator,
        serviceWorker: 'serviceWorker' in navigator,
        localStorage: (() => {
          try {
            localStorage.setItem('test', 'test');
            localStorage.removeItem('test');
            return true;
          } catch {
            return false;
          }
        })()
      };
    }
  },

  // Interface utilisateur moderne
  ui: {
    /**
     * Afficher l'overlay de chargement
     */
    showLoading(message = 'Chargement...') {
      if (TopoclimbCH.state.isLoading) return;

      TopoclimbCH.state.isLoading = true;

      let overlay = document.getElementById('loadingOverlay');
      if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'loading-overlay';
        overlay.innerHTML = `
          <div class="loading-spinner">
            <div class="spinner-modern"></div>
            <p class="loading-text">${message}</p>
          </div>
        `;
        document.body.appendChild(overlay);
      }

      overlay.querySelector('.loading-text').textContent = message;
      overlay.style.display = 'flex';

      requestAnimationFrame(() => {
        overlay.classList.add('show');
      });
    },

    /**
     * Masquer l'overlay de chargement
     */
    hideLoading() {
      TopoclimbCH.state.isLoading = false;

      const overlay = document.getElementById('loadingOverlay');
      if (overlay) {
        overlay.classList.remove('show');
        setTimeout(() => {
          overlay.style.display = 'none';
        }, 300);
      }
    },

    /**
     * Animer l'entrée des éléments
     */
    animateIn(elements, delay = 100) {
      const elementsArray = Array.from(
        typeof elements === 'string' ? document.querySelectorAll(elements) : elements
      );

      elementsArray.forEach((el, index) => {
        setTimeout(() => {
          el.classList.add('fade-in');
        }, index * delay);
      });
    },

    /**
     * Smooth scroll vers un élément
     */
    scrollTo(target, offset = 0) {
      const element = typeof target === 'string' ? document.querySelector(target) : target;
      if (!element) return;

      const targetPosition = element.offsetTop - offset;

      window.scrollTo({
        top: targetPosition,
        behavior: 'smooth'
      });
    },

    /**
     * Gérer les états de boutons
     */
    setButtonState(button, state, text = null) {
      const btn = typeof button === 'string' ? document.querySelector(button) : button;
      if (!btn) return;

      btn.classList.remove('btn-loading', 'btn-success', 'btn-error');

      switch (state) {
        case 'loading':
          btn.classList.add('btn-loading');
          btn.disabled = true;
          if (text) btn.textContent = text;
          break;
        case 'success':
          btn.classList.add('btn-success');
          btn.disabled = false;
          if (text) btn.textContent = text;
          break;
        case 'error':
          btn.classList.add('btn-error');
          btn.disabled = false;
          if (text) btn.textContent = text;
          break;
        default:
          btn.disabled = false;
          if (text) btn.textContent = text;
      }
    }
  },

  // Gestionnaire d'événements moderne
  events: {
    listeners: new Map(),

    /**
     * Ajouter un écouteur d'événement avec namespace
     */
    on(element, event, handler, namespace = null) {
      const el = typeof element === 'string' ? document.querySelector(element) : element;
      if (!el) return;

      el.addEventListener(event, handler);

      if (namespace) {
        if (!this.listeners.has(namespace)) {
          this.listeners.set(namespace, []);
        }
        this.listeners.get(namespace).push({ element: el, event, handler });
      }
    },

    /**
     * Supprimer les écouteurs par namespace
     */
    off(namespace) {
      const listeners = this.listeners.get(namespace);
      if (!listeners) return;

      listeners.forEach(({ element, event, handler }) => {
        element.removeEventListener(event, handler);
      });

      this.listeners.delete(namespace);
    },

    /**
     * Délégation d'événements moderne
     */
    delegate(parent, selector, event, handler) {
      const parentEl = typeof parent === 'string' ? document.querySelector(parent) : parent;
      if (!parentEl) return;

      parentEl.addEventListener(event, (e) => {
        const target = e.target.closest(selector);
        if (target) {
          handler.call(target, e);
        }
      });
    }
  }
};

// Initialisation globale modernisée
document.addEventListener('DOMContentLoaded', function () {
  console.log('🏔️ TopoclimbCH v' + TopoclimbCH.config.version + ' initialized');

  // Initialiser les composants de base
  initializeModernNavigation();
  initializeModernForms();
  initializeModernInteractions();
  initializeModernAnimations();
  initializeAccessibility();

  // Initialiser les composants Bootstrap si disponibles
  initializeBootstrapComponents();

  // Détecter les capacités du navigateur
  const capabilities = TopoclimbCH.utils.getCapabilities();
  document.body.classList.toggle('touch-device', capabilities.touch);
  document.body.classList.toggle('has-geolocation', capabilities.geolocation);

  // Ajouter les styles CSS dynamiques
  addDynamicStyles();

  if (TopoclimbCH.config.debug) {
    console.log('📱 Browser capabilities:', capabilities);
  }
});

/**
 * Navigation moderne avec animations
 */
function initializeModernNavigation() {
  // Toggle navbar mobile avec animation
  const navbarToggler = document.querySelector('.navbar-toggler');
  const navbarCollapse = document.querySelector('.navbar-collapse');

  if (navbarToggler && navbarCollapse) {
    navbarToggler.addEventListener('click', function () {
      this.classList.toggle('collapsed');

      // Animation des barres du burger
      const spans = this.querySelectorAll('span');
      spans.forEach((span, index) => {
        span.style.transform = this.classList.contains('collapsed')
          ? `rotate(${index === 0 ? 45 : index === 2 ? -45 : 0}deg)`
          : '';
        if (index === 1) {
          span.style.opacity = this.classList.contains('collapsed') ? '0' : '1';
        }
      });
    });

    // Fermer le menu en cliquant en dehors
    document.addEventListener('click', (e) => {
      if (!navbarToggler.contains(e.target) && !navbarCollapse.contains(e.target)) {
        if (navbarCollapse.classList.contains('show')) {
          navbarToggler.click();
        }
      }
    });
  }

  // Highlight du lien actif
  const currentPath = window.location.pathname;
  document.querySelectorAll('.nav-link').forEach(link => {
    const href = link.getAttribute('href');
    if (href && (currentPath === href || (href !== '/' && currentPath.startsWith(href)))) {
      link.classList.add('active');
    }
  });

  // Scroll header avec effet
  let lastScrollY = window.scrollY;
  const navbar = document.querySelector('.navbar');

  window.addEventListener('scroll', TopoclimbCH.utils.throttle(() => {
    const currentScrollY = window.scrollY;

    if (navbar) {
      navbar.classList.toggle('scrolled', currentScrollY > 50);
      navbar.classList.toggle('hidden', currentScrollY > lastScrollY && currentScrollY > 200);
    }

    lastScrollY = currentScrollY;
  }, 100));
}

/**
 * Formulaires modernes avec validation
 */
function initializeModernForms() {
  // Validation en temps réel
  document.querySelectorAll('input, textarea, select').forEach(field => {
    // Animation des labels flottants
    if (field.value) {
      field.classList.add('has-value');
    }

    field.addEventListener('input', function () {
      this.classList.toggle('has-value', this.value.length > 0);

      // Validation en temps réel si data-validate
      if (this.hasAttribute('data-validate')) {
        validateFieldModern(this);
      }
    });

    field.addEventListener('blur', function () {
      if (this.hasAttribute('required') || this.hasAttribute('data-validate')) {
        validateFieldModern(this);
      }
    });
  });

  // Auto-soumission améliorée
  TopoclimbCH.events.delegate(document, '[data-auto-submit]', 'change', function () {
    const form = this.closest('form');
    if (form && !TopoclimbCH.state.isLoading) {
      form.submit();
    }
  });

  // Confirmation avant suppression avec style moderne
  TopoclimbCH.events.delegate(document, '[data-confirm]', 'click', function (e) {
    const message = this.dataset.confirm || 'Êtes-vous sûr de vouloir effectuer cette action ?';

    e.preventDefault();

    // Créer une modale de confirmation moderne
    showModernConfirm(message, () => {
      // Action confirmée
      if (this.tagName === 'A') {
        window.location.href = this.href;
      } else if (this.tagName === 'BUTTON') {
        this.form?.submit();
      }
    });
  });
}

/**
 * Validation de champ moderne
 */
function validateFieldModern(field) {
  const value = field.value.trim();
  const rules = (field.dataset.validate || '').split('|');
  const errors = [];

  // Appliquer les règles de validation
  rules.forEach(rule => {
    const [ruleName, ruleValue] = rule.split(':');
    const error = applyValidationRule(field, ruleName, ruleValue, value);
    if (error) errors.push(error);
  });

  // Validation required
  if (field.hasAttribute('required') && !value) {
    errors.push('Ce champ est obligatoire');
  }

  // Afficher le résultat
  displayFieldValidation(field, errors);
  return errors.length === 0;
}

/**
 * Appliquer une règle de validation
 */
function applyValidationRule(field, ruleName, ruleValue, value) {
  if (!value) return null; // Pas de validation si vide (sauf required)

  switch (ruleName) {
    case 'email':
      return !TopoclimbCH.utils.validateEmail(value) ? 'Email invalide' : null;
    case 'min':
      return value.length < parseInt(ruleValue) ? `Minimum ${ruleValue} caractères` : null;
    case 'max':
      return value.length > parseInt(ruleValue) ? `Maximum ${ruleValue} caractères` : null;
    case 'numeric':
      return isNaN(value) ? 'Doit être un nombre' : null;
    case 'url':
      try {
        new URL(value);
        return null;
      } catch {
        return 'URL invalide';
      }
    default:
      return null;
  }
}

/**
 * Afficher la validation d'un champ
 */
function displayFieldValidation(field, errors) {
  // Supprimer les anciens messages
  const existingFeedback = field.parentNode.querySelector('.field-feedback');
  if (existingFeedback) {
    existingFeedback.remove();
  }

  field.classList.remove('field-valid', 'field-invalid');

  if (errors.length > 0) {
    field.classList.add('field-invalid');

    const feedback = document.createElement('div');
    feedback.className = 'field-feedback field-error';
    feedback.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${errors[0]}`;
    field.parentNode.appendChild(feedback);
  } else if (field.value.trim()) {
    field.classList.add('field-valid');

    const feedback = document.createElement('div');
    feedback.className = 'field-feedback field-success';
    feedback.innerHTML = `<i class="fas fa-check-circle"></i> Valide`;
    field.parentNode.appendChild(feedback);
  }
}

/**
 * Interactions modernes
 */
function initializeModernInteractions() {
  // Tooltips personnalisés améliorés
  let activeTooltip = null;

  TopoclimbCH.events.delegate(document, '[data-tooltip]', 'mouseenter', function (e) {
    if (activeTooltip) return;

    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip-modern';
    tooltip.textContent = this.dataset.tooltip;
    document.body.appendChild(tooltip);

    const rect = this.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();

    let top = rect.bottom + 10;
    let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);

    // Ajustements pour rester dans l'écran
    if (left < 10) left = 10;
    if (left + tooltipRect.width > window.innerWidth - 10) {
      left = window.innerWidth - tooltipRect.width - 10;
    }
    if (top + tooltipRect.height > window.innerHeight - 10) {
      top = rect.top - tooltipRect.height - 10;
    }

    tooltip.style.top = top + 'px';
    tooltip.style.left = left + 'px';

    requestAnimationFrame(() => {
      tooltip.classList.add('show');
    });

    activeTooltip = tooltip;
  });

  TopoclimbCH.events.delegate(document, '[data-tooltip]', 'mouseleave', function () {
    if (activeTooltip) {
      activeTooltip.classList.remove('show');
      setTimeout(() => {
        if (activeTooltip?.parentNode) {
          activeTooltip.remove();
        }
        activeTooltip = null;
      }, 200);
    }
  });

  // Boutons avec état de chargement
  TopoclimbCH.events.delegate(document, '.btn[data-loading]', 'click', function () {
    if (this.classList.contains('btn-loading')) return false;

    const originalText = this.textContent;
    const loadingText = this.dataset.loading || 'Chargement...';

    TopoclimbCH.ui.setButtonState(this, 'loading', loadingText);

    // Simuler ou attendre l'action
    setTimeout(() => {
      TopoclimbCH.ui.setButtonState(this, 'default', originalText);
    }, 2000);
  });

  // Copier dans le presse-papiers
  TopoclimbCH.events.delegate(document, '[data-copy]', 'click', async function () {
    const textToCopy = this.dataset.copy || this.textContent;

    try {
      await navigator.clipboard.writeText(textToCopy);
      if (window.showFlashMessage) {
        window.showFlashMessage('success', 'Copié dans le presse-papiers');
      }
    } catch (err) {
      console.error('Erreur de copie:', err);
      if (window.showFlashMessage) {
        window.showFlashMessage('error', 'Impossible de copier');
      }
    }
  });
}

/**
 * Animations au scroll
 */
function initializeModernAnimations() {
  // Observer pour les animations d'entrée
  const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  };

  const animationObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('animate-in');
        animationObserver.unobserve(entry.target);
      }
    });
  }, observerOptions);

  // Observer tous les éléments animables
  document.querySelectorAll('.card, .info-section, .stat-item, [data-animate]').forEach(el => {
    animationObserver.observe(el);
  });

  // Parallax léger pour les héros
  window.addEventListener('scroll', TopoclimbCH.utils.throttle(() => {
    const scrolled = window.pageYOffset;
    const heroes = document.querySelectorAll('.hero-modern, .sector-hero');

    heroes.forEach(hero => {
      if (hero.getBoundingClientRect().bottom > 0) {
        hero.style.transform = `translateY(${scrolled * 0.3}px)`;
      }
    });
  }, 16));
}

/**
 * Accessibilité moderne
 */
function initializeAccessibility() {
  // Navigation au clavier améliorée
  document.addEventListener('keydown', (e) => {
    // Échapper pour fermer les modales/menus
    if (e.key === 'Escape') {
      // Fermer les dropdowns
      document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
        menu.classList.remove('show');
      });

      // Fermer les modales personnalisées
      if (window.closeModal && typeof window.closeModal === 'function') {
        window.closeModal();
      }
    }

    // Tab piège dans les modales
    if (e.key === 'Tab') {
      const activeModal = document.querySelector('.modal.show, [role="dialog"]');
      if (activeModal) {
        trapFocus(activeModal, e);
      }
    }
  });

  // Focus visible amélioré
  document.addEventListener('mousedown', () => {
    document.body.classList.add('using-mouse');
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Tab') {
      document.body.classList.remove('using-mouse');
    }
  });

  // Announcements pour les lecteurs d'écran
  window.announceToScreenReader = function (message, priority = 'polite') {
    const announcement = document.createElement('div');
    announcement.setAttribute('aria-live', priority);
    announcement.setAttribute('aria-atomic', 'true');
    announcement.className = 'sr-only';
    announcement.textContent = message;

    document.body.appendChild(announcement);

    setTimeout(() => {
      document.body.removeChild(announcement);
    }, 1000);
  };
}

/**
 * Piéger le focus dans un élément
 */
function trapFocus(element, event) {
  const focusableElements = element.querySelectorAll(
    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
  );

  const firstElement = focusableElements[0];
  const lastElement = focusableElements[focusableElements.length - 1];

  if (event.shiftKey && document.activeElement === firstElement) {
    lastElement.focus();
    event.preventDefault();
  } else if (!event.shiftKey && document.activeElement === lastElement) {
    firstElement.focus();
    event.preventDefault();
  }
}

/**
 * Modale de confirmation moderne
 */
function showModernConfirm(message, onConfirm, onCancel = null) {
  const modal = document.createElement('div');
  modal.className = 'modal-confirm-modern';
  modal.innerHTML = `
    <div class="modal-overlay"></div>
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-body">
          <div class="confirm-icon">
            <i class="fas fa-question-circle"></i>
          </div>
          <h5>Confirmation</h5>
          <p>${message}</p>
          <div class="confirm-actions">
            <button type="button" class="btn btn-secondary cancel-btn">Annuler</button>
            <button type="button" class="btn btn-primary confirm-btn">Confirmer</button>
          </div>
        </div>
      </div>
    </div>
  `;

  document.body.appendChild(modal);

  // Animation d'entrée
  requestAnimationFrame(() => {
    modal.classList.add('show');
  });

  // Gestion des événements
  modal.querySelector('.cancel-btn').addEventListener('click', () => {
    closeModal();
    if (onCancel) onCancel();
  });

  modal.querySelector('.confirm-btn').addEventListener('click', () => {
    closeModal();
    if (onConfirm) onConfirm();
  });

  modal.querySelector('.modal-overlay').addEventListener('click', () => {
    closeModal();
    if (onCancel) onCancel();
  });

  function closeModal() {
    modal.classList.remove('show');
    setTimeout(() => {
      if (modal.parentNode) {
        modal.remove();
      }
    }, 300);
  }

  // Focus sur le bouton principal
  modal.querySelector('.confirm-btn').focus();
}

/**
 * Initialiser les composants Bootstrap
 */
function initializeBootstrapComponents() {
  if (typeof bootstrap === 'undefined') return;

  // Tooltips Bootstrap
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

  // Popovers Bootstrap
  const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
  popoverTriggerList.map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));
}

/**
 * Ajouter les styles CSS dynamiques
 */
function addDynamicStyles() {
  const style = document.createElement('style');
  style.textContent = `
    /* Styles dynamiques pour l'interactivité moderne */
    .btn-loading {
      position: relative;
      color: transparent !important;
    }
    
    .btn-loading::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 16px;
      height: 16px;
      margin-top: -8px;
      margin-left: -8px;
      border: 2px solid transparent;
      border-top: 2px solid currentColor;
      border-radius: 50%;
      animation: btn-spin 1s linear infinite;
    }
    
    @keyframes btn-spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    .field-feedback {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-top: 0.25rem;
      font-size: 0.875rem;
      font-weight: 500;
    }
    
    .field-error { color: #e84393; }
    .field-success { color: #00a085; }
    
    .field-invalid {
      border-color: #e84393 !important;
      box-shadow: 0 0 0 3px rgba(232, 67, 147, 0.1) !important;
    }
    
    .field-valid {
      border-color: #00a085 !important;
      box-shadow: 0 0 0 3px rgba(0, 160, 133, 0.1) !important;
    }
    
    .tooltip-modern {
      position: absolute;
      z-index: 1070;
      background: rgba(0, 0, 0, 0.9);
      color: white;
      padding: 0.5rem 0.75rem;
      border-radius: 6px;
      font-size: 0.875rem;
      font-weight: 500;
      white-space: nowrap;
      opacity: 0;
      transform: translateY(-5px);
      transition: all 0.2s ease;
      pointer-events: none;
      backdrop-filter: blur(10px);
    }
    
    .tooltip-modern.show {
      opacity: 1;
      transform: translateY(0);
    }
    
    .modal-confirm-modern {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      z-index: 1060;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    
    .modal-confirm-modern.show {
      opacity: 1;
    }
    
    .modal-confirm-modern .modal-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(5px);
    }
    
    .modal-confirm-modern .modal-dialog {
      position: relative;
      z-index: 1;
      max-width: 400px;
      width: 90%;
      transform: scale(0.9);
      transition: transform 0.3s ease;
    }
    
    .modal-confirm-modern.show .modal-dialog {
      transform: scale(1);
    }
    
    .modal-confirm-modern .modal-content {
      background: white;
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }
    
    .modal-confirm-modern .modal-body {
      padding: 2rem;
      text-align: center;
    }
    
    .modal-confirm-modern .confirm-icon {
      width: 60px;
      height: 60px;
      background: linear-gradient(135deg, #667eea, #764ba2);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      color: white;
      font-size: 1.5rem;
    }
    
    .modal-confirm-modern h5 {
      margin-bottom: 1rem;
      color: #2d3748;
    }
    
    .modal-confirm-modern p {
      margin-bottom: 2rem;
      color: #718096;
      line-height: 1.5;
    }
    
    .confirm-actions {
      display: flex;
      gap: 1rem;
      justify-content: center;
    }
    
    .animate-in {
      animation: slideInUp 0.6s ease-out;
    }
    
    .navbar.scrolled {
      backdrop-filter: blur(20px);
      background: rgba(102, 126, 234, 0.95);
    }
    
    .navbar.hidden {
      transform: translateY(-100%);
    }
    
    .using-mouse *:focus {
      outline: none !important;
      box-shadow: none !important;
    }
    
    .sr-only {
      position: absolute !important;
      width: 1px !important;
      height: 1px !important;
      padding: 0 !important;
      margin: -1px !important;
      overflow: hidden !important;
      clip: rect(0, 0, 0, 0) !important;
      white-space: nowrap !important;
      border: 0 !important;
    }
  `;

  document.head.appendChild(style);
}

// Exposer les fonctions utiles globalement
window.TopoclimbCH = TopoclimbCH;
window.validateFieldModern = validateFieldModern;
window.showModernConfirm = showModernConfirm;