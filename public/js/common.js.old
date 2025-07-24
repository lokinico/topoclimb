/**
 * Common JavaScript utilities for TopoclimbCH
 * Fonctions communes utilisées à travers l'application
 */

// Classes utilitaires globales
window.TopoclimbCH = window.TopoclimbCH || {};

// Utilitaires de base
window.TopoclimbCH.Utils = {
    
    /**
     * Debounce function pour éviter les appels trop fréquents
     */
    debounce: function(func, wait, immediate) {
        let timeout;
        return function executedFunction() {
            const context = this;
            const args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    },
    
    /**
     * Throttle function pour limiter la fréquence d'appels
     */
    throttle: function(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },
    
    /**
     * Formate un nombre avec des milliers
     */
    formatNumber: function(num) {
        return new Intl.NumberFormat('fr-CH').format(num);
    },
    
    /**
     * Formate une distance en km
     */
    formatDistance: function(km) {
        if (km < 1) {
            return Math.round(km * 1000) + ' m';
        }
        return km.toFixed(1) + ' km';
    },
    
    /**
     * Échappe le HTML pour éviter les XSS
     */
    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    /**
     * Génère un ID unique
     */
    generateId: function(prefix = 'id') {
        return prefix + '_' + Math.random().toString(36).substr(2, 9);
    },
    
    /**
     * Vérifie si un élément est visible
     */
    isElementVisible: function(element) {
        const rect = element.getBoundingClientRect();
        return rect.top >= 0 && rect.left >= 0 && 
               rect.bottom <= window.innerHeight && 
               rect.right <= window.innerWidth;
    },
    
    /**
     * Scroll smooth vers un élément
     */
    scrollToElement: function(element, offset = 0) {
        const elementPosition = element.offsetTop - offset;
        window.scrollTo({
            top: elementPosition,
            behavior: 'smooth'
        });
    }
};

// Gestionnaire d'événements global
window.TopoclimbCH.Events = {
    listeners: {},
    
    /**
     * Ajoute un écouteur d'événement
     */
    on: function(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    },
    
    /**
     * Supprime un écouteur d'événement
     */
    off: function(event, callback) {
        if (!this.listeners[event]) return;
        
        const index = this.listeners[event].indexOf(callback);
        if (index > -1) {
            this.listeners[event].splice(index, 1);
        }
    },
    
    /**
     * Déclenche un événement
     */
    emit: function(event, data) {
        if (!this.listeners[event]) return;
        
        this.listeners[event].forEach(callback => {
            try {
                callback(data);
            } catch (error) {
                console.error('Event listener error:', error);
            }
        });
    }
};

// Gestionnaire de notifications
window.TopoclimbCH.Notifications = {
    container: null,
    
    /**
     * Initialise le conteneur de notifications
     */
    init: function() {
        this.container = document.getElementById('notifications-container');
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'notifications-container';
            this.container.className = 'notifications-container';
            this.container.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                z-index: 9999;
                max-width: 400px;
            `;
            document.body.appendChild(this.container);
        }
    },
    
    /**
     * Affiche une notification
     */
    show: function(message, type = 'info', duration = 5000) {
        this.init();
        
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show mb-2`;
        notification.innerHTML = `
            ${window.TopoclimbCH.Utils.escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        this.container.appendChild(notification);
        
        // Auto-suppression
        if (duration > 0) {
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, duration);
        }
        
        return notification;
    },
    
    /**
     * Raccourcis pour différents types
     */
    success: function(message, duration) {
        return this.show(message, 'success', duration);
    },
    
    error: function(message, duration) {
        return this.show(message, 'danger', duration);
    },
    
    warning: function(message, duration) {
        return this.show(message, 'warning', duration);
    },
    
    info: function(message, duration) {
        return this.show(message, 'info', duration);
    }
};

// Gestionnaire de modales
window.TopoclimbCH.Modals = {
    
    /**
     * Confirme une action avec une modale
     */
    confirm: function(message, title = 'Confirmation', okText = 'Confirmer', cancelText = 'Annuler') {
        return new Promise((resolve) => {
            const modalId = window.TopoclimbCH.Utils.generateId('confirm-modal');
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = modalId;
            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${window.TopoclimbCH.Utils.escapeHtml(title)}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>${window.TopoclimbCH.Utils.escapeHtml(message)}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                ${window.TopoclimbCH.Utils.escapeHtml(cancelText)}
                            </button>
                            <button type="button" class="btn btn-primary confirm-btn">
                                ${window.TopoclimbCH.Utils.escapeHtml(okText)}
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Événements
            modal.querySelector('.confirm-btn').addEventListener('click', () => {
                resolve(true);
                bootstrap.Modal.getInstance(modal).hide();
            });
            
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
                resolve(false);
            });
            
            // Afficher la modale
            new bootstrap.Modal(modal).show();
        });
    }
};

// Gestionnaire de formulaires
window.TopoclimbCH.Forms = {
    
    /**
     * Sérialise un formulaire en objet
     */
    serialize: function(form) {
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            if (data[key]) {
                // Si la clé existe déjà, créer un array
                if (Array.isArray(data[key])) {
                    data[key].push(value);
                } else {
                    data[key] = [data[key], value];
                }
            } else {
                data[key] = value;
            }
        }
        
        return data;
    },
    
    /**
     * Valide un formulaire
     */
    validate: function(form) {
        const errors = [];
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                const label = form.querySelector(`label[for="${field.id}"]`);
                const fieldName = label ? label.textContent : field.name;
                errors.push(`Le champ "${fieldName}" est requis`);
                field.classList.add('is-invalid');
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        // Validation email
        const emailFields = form.querySelectorAll('input[type="email"]');
        emailFields.forEach(field => {
            if (field.value && !this.isValidEmail(field.value)) {
                errors.push('Format d\'email invalide');
                field.classList.add('is-invalid');
            }
        });
        
        return {
            isValid: errors.length === 0,
            errors: errors
        };
    },
    
    /**
     * Valide un email
     */
    isValidEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
};

// Gestionnaire de stockage local
window.TopoclimbCH.Storage = {
    
    /**
     * Stocke une valeur
     */
    set: function(key, value) {
        try {
            localStorage.setItem(`topoclimb_${key}`, JSON.stringify(value));
        } catch (error) {
            console.warn('localStorage not available:', error);
        }
    },
    
    /**
     * Récupère une valeur
     */
    get: function(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(`topoclimb_${key}`);
            return item ? JSON.parse(item) : defaultValue;
        } catch (error) {
            console.warn('localStorage not available:', error);
            return defaultValue;
        }
    },
    
    /**
     * Supprime une valeur
     */
    remove: function(key) {
        try {
            localStorage.removeItem(`topoclimb_${key}`);
        } catch (error) {
            console.warn('localStorage not available:', error);
        }
    },
    
    /**
     * Vide tout le stockage TopoclimbCH
     */
    clear: function() {
        try {
            Object.keys(localStorage).forEach(key => {
                if (key.startsWith('topoclimb_')) {
                    localStorage.removeItem(key);
                }
            });
        } catch (error) {
            console.warn('localStorage not available:', error);
        }
    }
};

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les composants
    window.TopoclimbCH.Notifications.init();
    
    // Amélioration UX - liens externes
    document.querySelectorAll('a[href^="http"]').forEach(link => {
        if (!link.hostname.includes(window.location.hostname)) {
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
        }
    });
    
    // Amélioration UX - tooltips Bootstrap
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Debug mode
    if (window.location.search.includes('debug=1')) {
        console.log('🐛 TopoclimbCH Debug Mode enabled');
        console.log('📦 Available modules:', Object.keys(window.TopoclimbCH));
    }
    
    console.log('✅ TopoclimbCH Common JavaScript loaded');
});

// Gestionnaire d'erreurs global
window.addEventListener('error', function(e) {
    console.error('Global JavaScript error:', e.error);
    
    // En mode développement, afficher une notification
    if (window.location.hostname === 'localhost' || window.location.search.includes('debug=1')) {
        window.TopoclimbCH.Notifications.error(
            `Erreur JavaScript: ${e.error.message}`,
            10000
        );
    }
});

// API fetch avec gestion d'erreur globale
window.TopoclimbCH.fetch = async function(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return await response.json();
        }
        
        return await response.text();
    } catch (error) {
        console.error('Fetch error:', error);
        window.TopoclimbCH.Notifications.error(`Erreur réseau: ${error.message}`);
        throw error;
    }
};

// Expose les classes pour utilisation globale
window.APIClient = APIClient;
window.CoordinatesHelper = CoordinatesHelper;