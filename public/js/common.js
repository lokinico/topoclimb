/**
 * TopoclimbCH Common.js - Pont de compatibilité
 * Ce fichier maintient la compatibilité avec l'ancien système
 * et redirige vers la nouvelle architecture modulaire
 */

// Redirection vers le nouveau système
if (!window.TopoclimbCH || !window.TopoclimbCH.initialized) {
    console.log('🔄 Loading new TopoclimbCH architecture...');
    
    // Charger le nouveau système
    const script = document.createElement('script');
    script.src = '/js/topoclimb.js';
    script.defer = true;
    document.head.appendChild(script);
    
    // Interface de compatibilité temporaire
    window.TopoclimbCH = window.TopoclimbCH || {
        // Utilitaires de base pour compatibilité immédiate
        Utils: {
            debounce: function(func, wait) {
                let timeout;
                return function(...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), wait);
                };
            },
            
            escapeHtml: function(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            },
            
            generateId: function(prefix = 'id') {
                return prefix + '_' + Math.random().toString(36).substr(2, 9);
            },
            
            formatNumber: function(num) {
                return new Intl.NumberFormat('fr-CH').format(num);
            }
        },
        
        // Événements simplifiés
        Events: {
            listeners: {},
            on: function(event, callback) {
                if (!this.listeners[event]) this.listeners[event] = [];
                this.listeners[event].push(callback);
            },
            emit: function(event, data) {
                if (this.listeners[event]) {
                    this.listeners[event].forEach(cb => cb(data));
                }
            }
        },
        
        // Notifications temporaires
        Notifications: {
            show: function(message, type) {
                console.log(`[${type}] ${message}`);
                // TODO: Remplacer par vraie notification quand UI chargé
            },
            success: function(message) { this.show(message, 'success'); },
            error: function(message) { this.show(message, 'error'); },
            warning: function(message) { this.show(message, 'warning'); },
            info: function(message) { this.show(message, 'info'); }
        },
        
        // API fetch simple
        fetch: async function(url, options = {}) {
            try {
                const response = await fetch(url, {
                    headers: { 'Content-Type': 'application/json', ...options.headers },
                    ...options
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                return await response.json();
            } catch (error) {
                console.error('Fetch error:', error);
                throw error;
            }
        }
    };
    
    console.log('⚡ Compatibility layer loaded, full system loading...');
} else {
    console.log('✅ TopoclimbCH modern architecture already loaded');
}

// Classes de compatibilité globales
window.APIClient = window.APIClient || class {
    constructor() {
        console.warn('⚠️ Using compatibility APIClient, consider upgrading to TopoclimbCH.api');
    }
    
    async get(url, params) {
        return TopoclimbCH.fetch(url + '?' + new URLSearchParams(params));
    }
    
    async post(url, data) {
        return TopoclimbCH.fetch(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
};

window.CoordinatesHelper = window.CoordinatesHelper || class {
    static isValidLatLng(lat, lng) {
        const latitude = parseFloat(lat);
        const longitude = parseFloat(lng);
        return !isNaN(latitude) && !isNaN(longitude) &&
               latitude >= -90 && latitude <= 90 &&
               longitude >= -180 && longitude <= 180;
    }
    
    static getSwissCenter() {
        return { latitude: 46.8182, longitude: 8.2275 };
    }
};

console.log('🔗 TopoclimbCH compatibility bridge loaded');