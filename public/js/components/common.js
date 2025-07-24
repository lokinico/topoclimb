// public/js/components/common.js - FICHIER CRITIQUE RESTAURÉ

/**
 * Common JavaScript utilities pour TopoclimbCH
 * Ce fichier contient les fonctions utilitaires partagées
 * Version: 2025-07-24 - Récréé suite à suppression accidentelle
 */

console.log('🔧 Loading TopoclimbCH Common Components...');

/**
 * Configuration globale de l'application
 */
window.TopoclimbCH = window.TopoclimbCH || {
    config: {
        apiBaseUrl: '/api',
        mapTileUrl: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        mapAttribution: '© OpenStreetMap contributors',
        debug: true
    },
    utils: {},
    components: {},
    pages: {}
};

/**
 * Utilitaires généraux
 */
window.TopoclimbCH.utils = {
    
    /**
     * Fonction debounce pour optimiser les événements
     */
    debounce: function(func, wait, immediate) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                if (!immediate) func(...args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func(...args);
        };
    },

    /**
     * Fonction throttle pour limiter les appels
     */
    throttle: function(func, limit) {
        let lastFunc;
        let lastRan;
        return function() {
            const context = this;
            const args = arguments;
            if (!lastRan) {
                func.apply(context, args);
                lastRan = Date.now();
            } else {
                clearTimeout(lastFunc);
                lastFunc = setTimeout(function() {
                    if ((Date.now() - lastRan) >= limit) {
                        func.apply(context, args);
                        lastRan = Date.now();
                    }
                }, limit - (Date.now() - lastRan));
            }
        };
    },

    /**
     * Escaper HTML pour éviter XSS
     */
    escapeHtml: function(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    },

    /**
     * Formatter une distance
     */
    formatDistance: function(distance) {
        if (distance < 1000) {
            return Math.round(distance) + 'm';
        } else {
            return (distance / 1000).toFixed(1) + 'km';
        }
    },

    /**
     * Formatter une durée en minutes
     */
    formatDuration: function(minutes) {
        if (minutes < 60) {
            return minutes + ' min';
        } else {
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            return hours + 'h' + (mins > 0 ? ' ' + mins + 'm' : '');
        }
    },

    /**
     * Afficher un toast message
     */
    showToast: function(message, type = 'info', duration = 3000) {
        // Créer le toast
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <span class="toast-message">${this.escapeHtml(message)}</span>
                <button class="toast-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        
        // Styles inline pour être indépendant du CSS
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 300px;
            max-width: 500px;
            padding: 12px 20px;
            background: ${type === 'success' ? '#28a745' : 
                        type === 'error' ? '#dc3545' : 
                        type === 'warning' ? '#ffc107' : '#007bff'};
            color: ${type === 'warning' ? '#000' : '#fff'};
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            font-size: 14px;
            animation: slideInRight 0.3s ease;
        `;

        // Ajouter au DOM
        document.body.appendChild(toast);

        // Auto-suppression
        if (duration > 0) {
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => toast.remove(), 300);
                }
            }, duration);
        }

        return toast;
    },

    /**
     * Effectuer une requête AJAX simplifiée
     */
    ajax: function(url, options = {}) {
        const defaults = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        const config = Object.assign(defaults, options);

        return fetch(url, config)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .catch(error => {
                console.error('Ajax error:', error);
                this.showToast('Erreur de connexion: ' + error.message, 'error');
                throw error;
            });
    },

    /**
     * Valider un email
     */
    isValidEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },

    /**
     * Gérer les polyfills pour les navigateurs anciens
     */
    addPolyfills: function() {
        // Polyfill pour Element.closest()
        if (!Element.prototype.closest) {
            Element.prototype.closest = function(selector) {
                let element = this;
                while (element && element.nodeType === 1) {
                    if (element.matches && element.matches(selector)) {
                        return element;
                    }
                    element = element.parentNode;
                }
                return null;
            };
        }

        // Polyfill pour Element.matches()
        if (!Element.prototype.matches) {
            Element.prototype.matches = Element.prototype.msMatchesSelector ||
                                      Element.prototype.webkitMatchesSelector;
        }
    }
};

/**
 * Composants UI réutilisables
 */
window.TopoclimbCH.components = {
    
    /**
     * Modal simple
     */
    Modal: function(options) {
        const modal = document.createElement('div');
        modal.className = 'topoclimb-modal';
        modal.innerHTML = `
            <div class="modal-backdrop" onclick="this.parentElement.remove()"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">${options.title || 'Modal'}</h5>
                    <button class="modal-close" onclick="this.closest('.topoclimb-modal').remove()">×</button>
                </div>
                <div class="modal-body">
                    ${options.content || ''}
                </div>
                <div class="modal-footer">
                    ${options.footer || '<button onclick="this.closest(\'.topoclimb-modal\').remove()">Fermer</button>'}
                </div>
            </div>
        `;

        // Styles inline
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10050;
            display: flex;
            align-items: center;
            justify-content: center;
        `;

        document.body.appendChild(modal);
        return modal;
    },

    /**
     * Loader/Spinner
     */
    showLoader: function(element) {
        const loader = document.createElement('div');
        loader.className = 'topoclimb-loader';
        loader.innerHTML = '<div class="spinner"></div>';
        loader.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        `;

        if (element) {
            element.style.position = 'relative';
            element.appendChild(loader);
        } else {
            document.body.appendChild(loader);
        }

        return loader;
    },

    /**
     * Masquer le loader
     */
    hideLoader: function(loader) {
        if (loader && loader.parentNode) {
            loader.remove();
        }
    }
};

/**
 * Initialisation des composants communs
 */
window.TopoclimbCH.init = function() {
    console.log('🚀 Initializing TopoclimbCH Common Components...');
    
    // Ajouter les polyfills
    this.utils.addPolyfills();
    
    // Ajouter les styles CSS pour les composants
    this.addCommonStyles();
    
    // Initialiser les gestionnaires d'événements globaux
    this.initGlobalEventHandlers();
    
    console.log('✅ TopoclimbCH Common Components initialized');
};

/**
 * Ajouter les styles CSS communs
 */
window.TopoclimbCH.addCommonStyles = function() {
    if (document.getElementById('topoclimb-common-styles')) return;
    
    const styles = document.createElement('style');
    styles.id = 'topoclimb-common-styles';
    styles.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        .topoclimb-modal .modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .topoclimb-modal .modal-content {
            position: relative;
            background: white;
            border-radius: 8px;
            max-width: 90vw;
            max-height: 90vh;
            overflow: auto;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .topoclimb-modal .modal-header {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .topoclimb-modal .modal-body {
            padding: 1rem;
        }
        
        .topoclimb-modal .modal-footer {
            padding: 1rem;
            border-top: 1px solid #dee2e6;
            text-align: right;
        }
        
        .topoclimb-loader .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `;
    
    document.head.appendChild(styles);
};

/**
 * Initialiser les gestionnaires d'événements globaux
 */
window.TopoclimbCH.initGlobalEventHandlers = function() {
    // Gestionnaire global pour les clics sur les liens avec confirmation
    document.addEventListener('click', function(e) {
        const confirmLink = e.target.closest('[data-confirm]');
        if (confirmLink) {
            const message = confirmLink.dataset.confirm;
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Gestionnaire pour les tooltips simples
    document.addEventListener('mouseover', function(e) {
        const tooltip = e.target.closest('[data-tooltip]');
        if (tooltip && !tooltip.querySelector('.topoclimb-tooltip')) {
            const tip = document.createElement('div');
            tip.className = 'topoclimb-tooltip';
            tip.textContent = tooltip.dataset.tooltip;
            tip.style.cssText = `
                position: absolute;
                background: #333;
                color: white;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                z-index: 10000;
                pointer-events: none;
                white-space: nowrap;
            `;
            
            tooltip.appendChild(tip);
            
            // Positionner le tooltip
            const rect = tooltip.getBoundingClientRect();
            tip.style.left = '50%';
            tip.style.top = '-30px';
            tip.style.transform = 'translateX(-50%)';
        }
    });
    
    document.addEventListener('mouseout', function(e) {
        const tooltip = e.target.closest('[data-tooltip]');
        if (tooltip) {
            const tip = tooltip.querySelector('.topoclimb-tooltip');
            if (tip) tip.remove();
        }
    });
};

// Auto-initialisation quand le DOM est prêt
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        window.TopoclimbCH.init();
    });
} else {
    window.TopoclimbCH.init();
}

console.log('✅ TopoclimbCH Common Components loaded successfully');