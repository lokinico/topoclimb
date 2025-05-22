/**
 * TopoclimbCH - Application JavaScript principale
 */

// Configuration globale
window.TopoclimbCH = {
  config: {
    baseUrl: window.location.origin,
    apiUrl: window.location.origin + '/api',
    debug: document.documentElement.hasAttribute('data-debug')
  },

  // Utilitaires globaux
  utils: {
    /**
     * Effectuer une requête AJAX avec gestion d'erreur
     */
    ajax: function (url, options = {}) {
      const defaults = {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      };

      const config = { ...defaults, ...options };

      return fetch(url, config)
        .then(response => {
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
        })
        .catch(error => {
          console.error('AJAX Error:', error);
          throw error;
        });
    },

    /**
     * Débounce une fonction
     */
    debounce: function (func, wait, immediate) {
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
     * Formater une taille de fichier
     */
    formatFileSize: function (bytes) {
      if (bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    /**
     * Valider une adresse email
     */
    validateEmail: function (email) {
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return re.test(email);
    }
  }
};

// Initialisation globale
document.addEventListener('DOMContentLoaded', function () {
  // Navigation mobile
  initializeMobileNavigation();

  // Gestion des onglets
  initializeTabs();

  // Gestion des alertes
  initializeAlerts();

  // Tooltips personnalisés
  initializeCustomTooltips();

  // Gestion globale des formulaires
  initializeGlobalFormHandlers();

  // Initialiser les tooltips Bootstrap si disponible
  initializeBootstrapComponents();

  // Debug info
  if (TopoclimbCH.config.debug) {
    console.log('TopoclimbCH Application initialized');
  }
});

/**
 * Initialise la navigation mobile
 */
function initializeMobileNavigation() {
  const mobileToggle = document.querySelector('.mobile-menu-toggle');
  const navLinks = document.querySelector('.nav-links');

  if (mobileToggle && navLinks) {
    mobileToggle.addEventListener('click', function () {
      navLinks.classList.toggle('active');
    });

    // Fermer le menu mobile quand on clique en dehors
    document.addEventListener('click', function (e) {
      if (!mobileToggle.contains(e.target) && !navLinks.contains(e.target)) {
        navLinks.classList.remove('active');
      }
    });
  }
}

/**
 * Initialise la gestion des onglets
 */
function initializeTabs() {
  const tabLinks = document.querySelectorAll('.nav-tabs a');

  if (tabLinks.length > 0) {
    tabLinks.forEach(link => {
      link.addEventListener('click', function (e) {
        e.preventDefault();

        // Retirer la classe active de tous les onglets
        document.querySelectorAll('.nav-tabs li').forEach(tab => {
          tab.classList.remove('active');
        });

        // Cacher tous les contenus d'onglets
        document.querySelectorAll('.tab-pane').forEach(pane => {
          pane.classList.remove('active');
        });

        // Ajouter la classe active à l'onglet cliqué
        this.parentElement.classList.add('active');

        // Afficher le contenu de l'onglet
        const target = this.getAttribute('href');
        const targetPane = document.querySelector(target);
        if (targetPane) {
          targetPane.classList.add('active');
        }
      });
    });

    // Activer le premier onglet par défaut si aucun n'est actif
    if (document.querySelector('.nav-tabs li.active') === null) {
      const firstTab = document.querySelector('.nav-tabs li:first-child');
      const firstPane = document.querySelector('.tab-pane:first-child');

      if (firstTab) firstTab.classList.add('active');
      if (firstPane) firstPane.classList.add('active');
    }
  }
}

/**
 * Initialise la gestion des alertes
 */
function initializeAlerts() {
  // Boutons de fermeture des alertes
  const alertCloseButtons = document.querySelectorAll('.alert .close');

  alertCloseButtons.forEach(button => {
    button.addEventListener('click', function () {
      const alert = this.parentElement;
      alert.style.opacity = '0';
      setTimeout(() => {
        alert.style.display = 'none';
      }, 150);
    });
  });

  // Auto-fermeture des alertes avec attribut data-auto-dismiss
  document.querySelectorAll('.alert[data-auto-dismiss]').forEach(alert => {
    const delay = parseInt(alert.dataset.autoDismiss) || 5000;
    setTimeout(() => {
      alert.style.opacity = '0';
      setTimeout(() => {
        alert.style.display = 'none';
      }, 150);
    }, delay);
  });

  // Auto-fermeture générale après 5 secondes (pour compatibilité)
  setTimeout(() => {
    document.querySelectorAll('.alert:not([data-auto-dismiss]):not(.alert-permanent)').forEach(alert => {
      if (!alert.querySelector('.close:hover')) { // Ne pas fermer si l'utilisateur survole le bouton
        alert.style.opacity = '0';
        setTimeout(() => {
          alert.style.display = 'none';
        }, 150);
      }
    });
  }, 5000);
}

/**
 * Initialise les tooltips personnalisés
 */
function initializeCustomTooltips() {
  const tooltipElements = document.querySelectorAll('[data-tooltip]');

  tooltipElements.forEach(element => {
    let tooltipDiv = null;

    element.addEventListener('mouseenter', function () {
      // Éviter les doublons
      if (tooltipDiv) return;

      tooltipDiv = document.createElement('div');
      tooltipDiv.classList.add('tooltip', 'custom-tooltip');
      tooltipDiv.textContent = this.getAttribute('data-tooltip');

      document.body.appendChild(tooltipDiv);

      const rect = this.getBoundingClientRect();
      const tooltipRect = tooltipDiv.getBoundingClientRect();

      // Positionner le tooltip
      let top = rect.bottom + 10;
      let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);

      // Ajuster si le tooltip dépasse de l'écran
      if (left < 10) left = 10;
      if (left + tooltipRect.width > window.innerWidth - 10) {
        left = window.innerWidth - tooltipRect.width - 10;
      }

      tooltipDiv.style.top = top + 'px';
      tooltipDiv.style.left = left + 'px';
      tooltipDiv.style.opacity = '1';
    });

    element.addEventListener('mouseleave', function () {
      if (tooltipDiv) {
        tooltipDiv.style.opacity = '0';
        setTimeout(() => {
          if (tooltipDiv && tooltipDiv.parentNode) {
            tooltipDiv.remove();
          }
          tooltipDiv = null;
        }, 150);
      }
    });
  });
}

/**
 * Initialise les gestionnaires de formulaires globaux
 */
function initializeGlobalFormHandlers() {
  // Confirmation avant suppression (compatible avec l'ancien code)
  document.querySelectorAll('[data-confirm]').forEach(element => {
    element.addEventListener('click', function (e) {
      const message = this.getAttribute('data-confirm') || this.dataset.confirm || 'Êtes-vous sûr de vouloir effectuer cette action?';
      if (!confirm(message)) {
        e.preventDefault();
      }
    });
  });

  // Auto-submit sur changement
  document.querySelectorAll('[data-auto-submit]').forEach(element => {
    element.addEventListener('change', function () {
      if (this.form) {
        this.form.submit();
      }
    });
  });

  // Validation en temps réel
  document.querySelectorAll('input[required], textarea[required], select[required]').forEach(input => {
    input.addEventListener('blur', function () {
      validateField(this);
    });
  });
}

/**
 * Initialise les composants Bootstrap si disponibles
 */
function initializeBootstrapComponents() {
  // Tooltips Bootstrap
  if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  }

  // Modales Bootstrap avec jQuery
  if (typeof $ !== 'undefined' && $.fn.modal) {
    $('.modal').on('shown.bs.modal', function () {
      $(this).find('input[autofocus]').focus();
    });
  }
}

/**
 * Valide un champ de formulaire
 */
function validateField(field) {
  const value = field.value.trim();
  let isValid = true;
  let errorMessage = '';

  // Validation required
  if (field.hasAttribute('required') && !value) {
    isValid = false;
    errorMessage = 'Ce champ est obligatoire';
  }

  // Validation email
  if (field.type === 'email' && value && !TopoclimbCH.utils.validateEmail(value)) {
    isValid = false;
    errorMessage = 'Adresse email invalide';
  }

  // Validation numérique
  if (field.type === 'number' && value) {
    const num = parseFloat(value);
    if (isNaN(num)) {
      isValid = false;
      errorMessage = 'Valeur numérique invalide';
    } else if (field.hasAttribute('min') && num < parseFloat(field.getAttribute('min'))) {
      isValid = false;
      errorMessage = `Valeur minimale: ${field.getAttribute('min')}`;
    } else if (field.hasAttribute('max') && num > parseFloat(field.getAttribute('max'))) {
      isValid = false;
      errorMessage = `Valeur maximale: ${field.getAttribute('max')}`;
    }
  }

  // Validation de longueur
  if (field.hasAttribute('minlength') && value.length < parseInt(field.getAttribute('minlength'))) {
    isValid = false;
    errorMessage = `Longueur minimale: ${field.getAttribute('minlength')} caractères`;
  }

  if (field.hasAttribute('maxlength') && value.length > parseInt(field.getAttribute('maxlength'))) {
    isValid = false;
    errorMessage = `Longueur maximale: ${field.getAttribute('maxlength')} caractères`;
  }

  // Appliquer les styles de validation
  if (isValid) {
    field.classList.remove('is-invalid');
    field.classList.add('is-valid');

    // Supprimer le message d'erreur existant
    const existingFeedback = field.parentNode.querySelector('.invalid-feedback');
    if (existingFeedback) {
      existingFeedback.remove();
    }
  } else {
    field.classList.remove('is-valid');
    field.classList.add('is-invalid');

    // Afficher le message d'erreur
    let feedback = field.parentNode.querySelector('.invalid-feedback');
    if (!feedback) {
      feedback = document.createElement('div');
      feedback.className = 'invalid-feedback';
      field.parentNode.appendChild(feedback);
    }
    feedback.textContent = errorMessage;
  }

  return isValid;
}

// Exposer les fonctions utiles globalement pour compatibilité
window.validateField = validateField;
window.TopoclimbCH = window.TopoclimbCH;