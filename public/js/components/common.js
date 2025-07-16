// public/js/components/common.js

/**
 * Composants JavaScript communs pour TopoclimbCH
 */

// Namespace global pour les composants
window.TopoclimbCH = window.TopoclimbCH || {};
window.TopoclimbCH.components = {};


// Protection contre les doubles chargements
if (window.TopoclimbCommonLoaded) {
    console.warn('⚠️ common.js already loaded, skipping...');
} else {
    window.TopoclimbCommonLoaded = true;

// Protection pour ModalManager
if (typeof window.ModalManager !== 'undefined') {
    console.warn('⚠️ ModalManager already exists, skipping declaration...');
} else {
    // Déclarer ModalManager seulement s'il n'existe pas
    class ModalManager {
        // ... votre code existant
    }
    window.ModalManager = ModalManager;
}

// Protection pour les autres classes similaires
if (typeof window.showToast === 'undefined') {
    window.showToast = function (message, type = 'info') {
        console.log(`Toast [${type}]: ${message}`);
        // Votre implémentation de toast
    };
}

console.log('✅ common.js loaded safely');
/**
 * Gestionnaire de modales modernes
 */
class ModalManager {
    constructor() {
        this.activeModal = null;
        this.init();
    }

    init() {
        // Écouter les clics sur les déclencheurs de modales
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-modal-target]');
            if (trigger) {
                e.preventDefault();
                const modalId = trigger.dataset.modalTarget;
                this.open(modalId);
            }

            // Fermer la modale en cliquant sur le fond
            if (e.target.classList.contains('modal-overlay')) {
                this.close();
            }

            // Fermer la modale avec le bouton close
            if (e.target.closest('[data-modal-close]')) {
                this.close();
            }
        });

        // Fermer avec Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.activeModal) {
                this.close();
            }
        });
    }

    open(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        // Créer l'overlay s'il n'existe pas
        let overlay = document.querySelector('.modal-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'modal-overlay';
            document.body.appendChild(overlay);
        }

        // Afficher la modale
        modal.style.display = 'block';
        overlay.style.display = 'block';
        document.body.classList.add('modal-open');

        // Animation d'entrée
        requestAnimationFrame(() => {
            modal.classList.add('modal-show');
            overlay.classList.add('modal-overlay-show');
        });

        this.activeModal = modal;

        // Focus sur le premier élément focusable
        const focusable = modal.querySelector('input, button, textarea, select');
        if (focusable) {
            focusable.focus();
        }
    }

    close() {
        if (!this.activeModal) return;

        const modal = this.activeModal;
        const overlay = document.querySelector('.modal-overlay');

        // Animation de sortie
        modal.classList.remove('modal-show');
        if (overlay) {
            overlay.classList.remove('modal-overlay-show');
        }

        // Masquer après l'animation
        setTimeout(() => {
            modal.style.display = 'none';
            if (overlay) {
                overlay.style.display = 'none';
            }
            document.body.classList.remove('modal-open');
        }, 300);

        this.activeModal = null;
    }
}

/**
 * Gestionnaire d'images en lightbox
 */
class LightboxManager {
    constructor() {
        this.currentIndex = 0;
        this.images = [];
        this.lightboxElement = null;
        this.init();
    }

    init() {
        // Créer la structure de la lightbox
        this.createLightbox();

        // Écouter les clics sur les images
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-lightbox]');
            if (trigger) {
                e.preventDefault();
                this.open(trigger);
            }
        });
    }

    createLightbox() {
        const lightbox = document.createElement('div');
        lightbox.className = 'lightbox';
        lightbox.innerHTML = `
            <div class="lightbox-overlay"></div>
            <div class="lightbox-container">
                <button class="lightbox-close" aria-label="Fermer">&times;</button>
                <button class="lightbox-prev" aria-label="Précédent">&#8249;</button>
                <button class="lightbox-next" aria-label="Suivant">&#8250;</button>
                <div class="lightbox-content">
                    <img class="lightbox-image" alt="">
                    <div class="lightbox-caption"></div>
                </div>
                <div class="lightbox-counter"></div>
            </div>
        `;
        document.body.appendChild(lightbox);
        this.lightboxElement = lightbox;

        // Événements
        lightbox.querySelector('.lightbox-close').addEventListener('click', () => this.close());
        lightbox.querySelector('.lightbox-prev').addEventListener('click', () => this.prev());
        lightbox.querySelector('.lightbox-next').addEventListener('click', () => this.next());
        lightbox.querySelector('.lightbox-overlay').addEventListener('click', () => this.close());

        // Clavier
        document.addEventListener('keydown', (e) => {
            if (!lightbox.classList.contains('active')) return;

            switch (e.key) {
                case 'Escape':
                    this.close();
                    break;
                case 'ArrowLeft':
                    this.prev();
                    break;
                case 'ArrowRight':
                    this.next();
                    break;
            }
        });
    }

    open(trigger) {
        // Collecter toutes les images du même groupe
        const group = trigger.dataset.lightboxGroup || 'default';
        this.images = Array.from(document.querySelectorAll(`[data-lightbox][data-lightbox-group="${group}"], [data-lightbox]:not([data-lightbox-group])`))
            .map(img => ({
                src: img.dataset.lightbox || img.src || img.href,
                caption: img.dataset.lightboxCaption || img.alt || img.title || ''
            }));

        // Trouver l'index de l'image cliquée
        this.currentIndex = this.images.findIndex(img =>
            img.src === (trigger.dataset.lightbox || trigger.src || trigger.href)
        );

        if (this.currentIndex === -1) this.currentIndex = 0;

        this.showImage();
        this.lightboxElement.classList.add('active');
        document.body.classList.add('lightbox-open');
    }

    close() {
        this.lightboxElement.classList.remove('active');
        document.body.classList.remove('lightbox-open');
    }

    prev() {
        this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length;
        this.showImage();
    }

    next() {
        this.currentIndex = (this.currentIndex + 1) % this.images.length;
        this.showImage();
    }

    showImage() {
        const image = this.images[this.currentIndex];
        const imgElement = this.lightboxElement.querySelector('.lightbox-image');
        const captionElement = this.lightboxElement.querySelector('.lightbox-caption');
        const counterElement = this.lightboxElement.querySelector('.lightbox-counter');

        imgElement.src = image.src;
        imgElement.alt = image.caption;
        captionElement.textContent = image.caption;
        counterElement.textContent = `${this.currentIndex + 1} / ${this.images.length}`;

        // Gérer les boutons prev/next
        const prevBtn = this.lightboxElement.querySelector('.lightbox-prev');
        const nextBtn = this.lightboxElement.querySelector('.lightbox-next');

        prevBtn.style.display = this.images.length > 1 ? 'block' : 'none';
        nextBtn.style.display = this.images.length > 1 ? 'block' : 'none';
    }
}

/**
 * Gestionnaire de notifications toast
 */
class ToastManager {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        // Créer le conteneur de toasts
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        document.body.appendChild(this.container);
    }

    show(message, type = 'info', duration = 5000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        const icon = this.getIcon(type);
        toast.innerHTML = `
            <div class="toast-content">
                <span class="toast-icon">${icon}</span>
                <span class="toast-message">${message}</span>
                <button class="toast-close" aria-label="Fermer">&times;</button>
            </div>
        `;

        this.container.appendChild(toast);

        // Animation d'entrée
        requestAnimationFrame(() => {
            toast.classList.add('toast-show');
        });

        // Fermer automatiquement
        const closeTimer = setTimeout(() => {
            this.close(toast);
        }, duration);

        // Fermer manuellement
        toast.querySelector('.toast-close').addEventListener('click', () => {
            clearTimeout(closeTimer);
            this.close(toast);
        });

        return toast;
    }

    close(toast) {
        toast.classList.remove('toast-show');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }

    getIcon(type) {
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        return icons[type] || icons.info;
    }

    // Méthodes de convenance
    success(message, duration) {
        return this.show(message, 'success', duration);
    }

    error(message, duration) {
        return this.show(message, 'error', duration);
    }

    warning(message, duration) {
        return this.show(message, 'warning', duration);
    }

    info(message, duration) {
        return this.show(message, 'info', duration);
    }
}

/**
 * Gestionnaire de formulaires améliorés
 */
class FormManager {
    constructor() {
        this.init();
    }

    init() {
        // Auto-soumission des formulaires
        document.addEventListener('change', (e) => {
            if (e.target.hasAttribute('data-auto-submit')) {
                const form = e.target.closest('form');
                if (form) {
                    form.submit();
                }
            }
        });

        // Validation en temps réel
        document.addEventListener('input', (e) => {
            if (e.target.hasAttribute('data-validate')) {
                this.validateField(e.target);
            }
        });

        // Confirmation avant soumission
        document.addEventListener('submit', (e) => {
            const form = e.target;
            const confirmMessage = form.dataset.confirmSubmit;

            if (confirmMessage && !confirm(confirmMessage)) {
                e.preventDefault();
            }
        });
    }

    validateField(field) {
        const rules = field.dataset.validate.split('|');
        const errors = [];

        for (const rule of rules) {
            const error = this.applyRule(field, rule);
            if (error) {
                errors.push(error);
            }
        }

        this.displayFieldErrors(field, errors);
        return errors.length === 0;
    }

    applyRule(field, rule) {
        const value = field.value.trim();
        const [ruleName, ruleValue] = rule.split(':');

        switch (ruleName) {
            case 'required':
                return !value ? 'Ce champ est obligatoire' : null;

            case 'email':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return value && !emailRegex.test(value) ? 'Email invalide' : null;

            case 'min':
                return value.length < parseInt(ruleValue) ? `Minimum ${ruleValue} caractères` : null;

            case 'max':
                return value.length > parseInt(ruleValue) ? `Maximum ${ruleValue} caractères` : null;

            case 'numeric':
                return value && isNaN(value) ? 'Doit être un nombre' : null;

            default:
                return null;
        }
    }

    displayFieldErrors(field, errors) {
        // Supprimer les anciens messages
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }

        field.classList.remove('field-valid', 'field-invalid');

        if (errors.length > 0) {
            field.classList.add('field-invalid');

            const errorElement = document.createElement('div');
            errorElement.className = 'field-error';
            errorElement.textContent = errors[0];
            field.parentNode.appendChild(errorElement);
        } else if (field.value.trim()) {
            field.classList.add('field-valid');
        }
    }
}

/**
 * Gestionnaire de filtres dynamiques
 */
class FilterManager {
    constructor(formSelector, targetSelector) {
        this.form = document.querySelector(formSelector);
        this.target = document.querySelector(targetSelector);
        this.debounceDelay = 300;

        if (this.form && this.target) {
            this.init();
        }
    }

    init() {
        // Appliquer le debounce aux champs de texte
        const textInputs = this.form.querySelectorAll('input[type="text"], input[type="search"]');
        textInputs.forEach(input => {
            input.addEventListener('input', this.debounce(() => {
                this.applyFilters();
            }, this.debounceDelay));
        });

        // Appliquer immédiatement pour les autres champs
        const otherInputs = this.form.querySelectorAll('select, input[type="radio"], input[type="checkbox"]');
        otherInputs.forEach(input => {
            input.addEventListener('change', () => {
                this.applyFilters();
            });
        });
    }

    applyFilters() {
        const formData = new FormData(this.form);
        const params = new URLSearchParams();

        for (const [key, value] of formData.entries()) {
            if (value.trim()) {
                params.append(key, value);
            }
        }

        // Mise à jour de l'URL sans rechargement
        const newUrl = `${window.location.pathname}?${params.toString()}`;
        window.history.replaceState({}, '', newUrl);

        // Charger le contenu filtré
        this.loadFilteredContent(params);
    }

    async loadFilteredContent(params) {
        try {
            const response = await fetch(`${window.location.pathname}?${params.toString()}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContent = doc.querySelector(this.target.tagName + this.target.className.split(' ').map(c => '.' + c).join(''));

                if (newContent) {
                    this.target.innerHTML = newContent.innerHTML;
                }
            }
        } catch (error) {
            console.error('Erreur lors du filtrage:', error);
        }
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Initialisation automatique des composants
document.addEventListener('DOMContentLoaded', () => {
    // Initialiser les composants globaux
    TopoclimbCH.components.modal = new ModalManager();
    TopoclimbCH.components.lightbox = new LightboxManager();
    TopoclimbCH.components.toast = new ToastManager();
    TopoclimbCH.components.form = new FormManager();

    // Exposer les méthodes utiles globalement
    window.showToast = (message, type, duration) => {
        return TopoclimbCH.components.toast.show(message, type, duration);
    };

    window.openModal = (modalId) => {
        TopoclimbCH.components.modal.open(modalId);
    };

    window.closeModal = () => {
        TopoclimbCH.components.modal.close();
    };
});

    // Exporter les classes pour utilisation externe
    window.TopoclimbCH.components.ModalManager = ModalManager;
    window.TopoclimbCH.components.LightboxManager = LightboxManager;
    window.TopoclimbCH.components.ToastManager = ToastManager;
    window.TopoclimbCH.components.FormManager = FormManager;
    window.TopoclimbCH.components.FilterManager = FilterManager;
}