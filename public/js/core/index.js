/**
 * TopoclimbCH Core JavaScript Framework
 * Architecture moderne ES6+ avec modules
 * Version 2.0 - Refactorisation complète
 */

// Initialisation du namespace global unique
window.TopoclimbCH = window.TopoclimbCH || {
    version: '2.0.0',
    debug: window.location.search.includes('debug=1'),
    initialized: false,
    modules: {},
    components: {},
    utils: {},
    pages: {},
    
    // Configuration globale
    config: {
        apiBaseUrl: '',
        apiTimeout: 10000,
        locale: 'fr-CH',
        mapCenter: [46.8182, 8.2275],
        mapZoom: 8
    }
};

/**
 * Système de modules moderne
 */
class ModuleManager {
    constructor() {
        this.modules = new Map();
        this.dependencies = new Map();
        this.loading = new Set();
    }
    
    /**
     * Enregistre un module
     */
    register(name, factory, dependencies = []) {
        if (TopoclimbCH.debug) {
            console.log(`📦 Registering module: ${name}`);
        }
        
        this.modules.set(name, { factory, dependencies, instance: null });
        this.dependencies.set(name, dependencies);
        
        return this;
    }
    
    /**
     * Charge un module avec ses dépendances
     */
    async load(name) {
        if (this.loading.has(name)) {
            throw new Error(`Circular dependency detected for module: ${name}`);
        }
        
        const module = this.modules.get(name);
        if (!module) {
            throw new Error(`Module not found: ${name}`);
        }
        
        if (module.instance) {
            return module.instance;
        }
        
        this.loading.add(name);
        
        try {
            // Charger les dépendances d'abord
            const deps = Array.isArray(module.dependencies) ? module.dependencies : [];
            const dependencies = await Promise.all(
                deps.map(dep => this.load(dep))
            );
            
            // Créer l'instance du module
            module.instance = await module.factory(...dependencies);
            
            if (TopoclimbCH.debug) {
                console.log(`✅ Module loaded: ${name}`);
            }
            
            return module.instance;
        } finally {
            this.loading.delete(name);
        }
    }
    
    /**
     * Charge plusieurs modules en parallèle
     */
    async loadAll(names) {
        return Promise.all(names.map(name => this.load(name)));
    }
    
    /**
     * Obtient une instance de module (doit être déjà chargé)
     */
    get(name) {
        const module = this.modules.get(name);
        return module ? module.instance : null;
    }
}

// Instance globale du gestionnaire de modules
TopoclimbCH.modules = new ModuleManager();

/**
 * Système d'événements global
 */
class EventSystem {
    constructor() {
        this.listeners = new Map();
        this.onceListeners = new Set();
    }
    
    on(event, callback, options = {}) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, new Set());
        }
        
        const listener = { callback, options };
        this.listeners.get(event).add(listener);
        
        if (options.once) {
            this.onceListeners.add(listener);
        }
        
        return () => this.off(event, callback);
    }
    
    once(event, callback, options = {}) {
        return this.on(event, callback, { ...options, once: true });
    }
    
    off(event, callback) {
        const listeners = this.listeners.get(event);
        if (!listeners) return;
        
        for (const listener of listeners) {
            if (listener.callback === callback) {
                listeners.delete(listener);
                this.onceListeners.delete(listener);
                break;
            }
        }
        
        if (listeners.size === 0) {
            this.listeners.delete(event);
        }
    }
    
    emit(event, data) {
        const listeners = this.listeners.get(event);
        if (!listeners) return;
        
        const toRemove = [];
        
        for (const listener of listeners) {
            try {
                listener.callback(data);
                
                if (this.onceListeners.has(listener)) {
                    toRemove.push(listener);
                }
            } catch (error) {
                console.error(`Event listener error for "${event}":`, error);
            }
        }
        
        // Supprimer les listeners "once"
        toRemove.forEach(listener => {
            listeners.delete(listener);
            this.onceListeners.delete(listener);
        });
        
        if (TopoclimbCH.debug) {
            console.log(`📡 Event emitted: ${event}`, data);
        }
    }
    
    clear() {
        this.listeners.clear();
        this.onceListeners.clear();
    }
}

// Instance globale du système d'événements
TopoclimbCH.events = new EventSystem();

/**
 * Gestionnaire de promesses avec cache
 */
class PromiseCache {
    constructor(maxSize = 100) {
        this.cache = new Map();
        this.maxSize = maxSize;
    }
    
    get(key) {
        return this.cache.get(key);
    }
    
    set(key, promise) {
        if (this.cache.size >= this.maxSize) {
            const firstKey = this.cache.keys().next().value;
            this.cache.delete(firstKey);
        }
        
        this.cache.set(key, promise);
        return promise;
    }
    
    has(key) {
        return this.cache.has(key);
    }
    
    delete(key) {
        return this.cache.delete(key);
    }
    
    clear() {
        this.cache.clear();
    }
}

TopoclimbCH.promiseCache = new PromiseCache();

/**
 * Initialisation du framework
 */
TopoclimbCH.init = function(config = {}) {
    if (this.initialized) {
        console.warn('⚠️ TopoclimbCH already initialized');
        return;
    }
    
    // Merge configuration
    Object.assign(this.config, config);
    
    // Événement d'initialisation
    this.events.emit('core:init', { config: this.config });
    
    this.initialized = true;
    
    if (this.debug) {
        console.log(`🚀 TopoclimbCH Core v${this.version} initialized`);
        console.log('📋 Config:', this.config);
    }
    
    return this;
};

/**
 * Gestion d'erreurs globale
 */
window.addEventListener('error', function(event) {
    console.error('🚨 Global JavaScript Error:', {
        message: event.message,
        filename: event.filename,
        line: event.lineno,
        column: event.colno,
        error: event.error
    });
    
    TopoclimbCH.events.emit('error:global', {
        type: 'javascript',
        message: event.message,
        source: event.filename,
        line: event.lineno,
        error: event.error
    });
});

window.addEventListener('unhandledrejection', function(event) {
    console.error('🚨 Unhandled Promise Rejection:', event.reason);
    
    TopoclimbCH.events.emit('error:promise', {
        type: 'promise',
        reason: event.reason
    });
});

// Auto-initialisation quand le DOM est prêt
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        TopoclimbCH.init();
    });
} else {
    // DOM déjà prêt
    TopoclimbCH.init();
}

// Export pour utilisation comme module ES6
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TopoclimbCH;
}

console.log('🎯 TopoclimbCH Core loaded and ready');