/**
 * TopoclimbCH - Application JavaScript Principale
 * Version 2.0 - Architecture moderne unifiée
 * 
 * Ce fichier remplace et unifie :
 * - /public/js/common.js (ancien)
 * - /public/js/components/common.js (ancien)
 * - Parties de /public/js/app.js
 */

(function(window, document) {
    'use strict';
    
    // Éviter les doubles chargements
    if (window.TopoclimbCH && window.TopoclimbCH.initialized) {
        console.warn('⚠️ TopoclimbCH already loaded, skipping...');
        return;
    }
    
    /**
     * 🚀 Chargement séquentiel des modules core
     */
    async function loadCoreModules() {
        const coreModules = [
            '/js/core/index.js',    // Framework de base
            '/js/core/utils.js',    // Utilitaires
            '/js/core/api.js',      // Client API
            '/js/core/ui.js'        // Composants UI
        ];
        
        for (const module of coreModules) {
            try {
                await loadScript(module);
                console.log(`✅ Loaded: ${module}`);
            } catch (error) {
                console.error(`❌ Failed to load: ${module}`, error);
                throw error;
            }
        }
    }
    
    /**
     * 📦 Chargement dynamique de scripts
     */
    function loadScript(src) {
        return new Promise((resolve, reject) => {
            // Vérifier si le script est déjà chargé
            if (document.querySelector(`script[src=\"${src}\"]`)) {
                resolve();
                return;
            }
            
            const script = document.createElement('script');
            script.src = src;
            script.defer = true;
            
            script.onload = () => resolve();
            script.onerror = () => reject(new Error(`Failed to load script: ${src}`));
            
            document.head.appendChild(script);
        });
    }
    
    /**
     * 🎯 Initialisation de l'application
     */
    async function initializeApplication() {
        try {
            console.log('🏗️ Initializing TopoclimbCH v2.0...');
            
            // 1. Charger les modules core
            await loadCoreModules();
            
            // 2. Attendre que TopoclimbCH soit disponible
            if (!window.TopoclimbCH) {
                throw new Error('TopoclimbCH core not loaded');
            }
            
            // 3. Charger les modules requis
            await TopoclimbCH.modules.loadAll(['utils', 'api', 'ui']);
            
            // 4. Configuration de l'application
            TopoclimbCH.init({
                debug: window.location.search.includes('debug=1'),
                apiBaseUrl: window.location.origin,
                locale: document.documentElement.lang || 'fr-CH'
            });
            
            // 5. Initialiser les fonctionnalités globales
            initializeGlobalFeatures();
            
            // 6. Charger les composants spécifiques à la page
            await loadPageSpecificComponents();
            
            console.log('🎉 TopoclimbCH v2.0 ready!');
            
            // Émettre événement de fin d'initialisation
            TopoclimbCH.events.emit('app:ready');
            
        } catch (error) {
            console.error('🚨 Failed to initialize TopoclimbCH:', error);
            
            // Mode dégradé
            initializeFallbackMode();
        }
    }
    
    /**
     * 🌐 Fonctionnalités globales
     */
    function initializeGlobalFeatures() {
        // Auto-init des tooltips Bootstrap
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltips = document.querySelectorAll('[data-bs-toggle=\"tooltip\"]');
            tooltips.forEach(el => new bootstrap.Tooltip(el));
        }
        
        // Liens externes en nouvelle fenêtre
        document.querySelectorAll('a[href^=\"http\"]').forEach(link => {
            if (!link.hostname.includes(window.location.hostname)) {
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            }
        });
        
        // Gestion des formulaires avec validation
        document.querySelectorAll('form[data-validate]').forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!validateForm(form)) {
                    e.preventDefault();
                }
            });
        });
        
        // Images lazy loading
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
        
        // Performance monitoring
        if (TopoclimbCH.debug && 'PerformanceObserver' in window) {
            const perfObserver = new PerformanceObserver((list) => {
                list.getEntries().forEach(entry => {
                    if (entry.duration > 100) {
                        console.log(`🐌 Slow operation: ${entry.name} (${entry.duration.toFixed(2)}ms)`);
                    }
                });
            });
            
            perfObserver.observe({ entryTypes: ['measure', 'navigation'] });
        }
    }
    
    /**
     * 📄 Chargement des composants spécifiques à la page
     */
    async function loadPageSpecificComponents() {
        const bodyClass = document.body.className;
        const components = [];
        
        // Détection basée sur les classes CSS du body ou data attributes
        if (bodyClass.includes('map-page') || document.getElementById('map')) {
            components.push('/js/components/swiss-map-manager.js');
            components.push('/js/components/interactive-map-manager.js');
        }
        
        if (bodyClass.includes('geolocation-page') || document.querySelector('[data-geolocation]')) {
            components.push('/js/utils/coordinates-helper.js');
            components.push('/js/components/geolocation-manager.js');
        }
        
        if (bodyClass.includes('form-page') || document.querySelector('.site-form')) {
            components.push('/js/utils/coordinates-helper.js');
            components.push('/js/components/site-form-manager.js');
        }
        
        if (document.querySelector('[data-lightbox]')) {
            // Lightbox déjà inclus dans UI
        }
        
        // Charger les composants détectés
        for (const component of components) {
            try {
                await loadScript(component);
                console.log(`📦 Component loaded: ${component}`);
            } catch (error) {
                console.warn(`⚠️ Component failed to load: ${component}`, error);
            }
        }
        
        // Auto-initialisation des composants
        autoInitializeComponents();
    }
    
    /**
     * 🤖 Auto-initialisation des composants
     */
    function autoInitializeComponents() {
        // Map interactive
        const mapElement = document.getElementById('map');
        if (mapElement && window.InteractiveMapManager) {
            const mapManager = new InteractiveMapManager('map');
            mapManager.init();
            
            if (mapManager.loadClimbingData) {
                mapManager.loadClimbingData().catch(console.error);
            }
        }
        
        // Geolocation
        if (document.querySelector('[data-geolocation]') && window.GeolocationManager) {
            new GeolocationManager();
        }
        
        // Site form
        if (document.querySelector('.site-form') && window.SiteFormManager) {
            new SiteFormManager();
        }
    }
    
    /**
     * ✅ Validation de formulaire simple
     */
    function validateForm(form) {
        let valid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                valid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        return valid;
    }
    
    /**
     * 🔄 Mode dégradé en cas d'erreur
     */
    function initializeFallbackMode() {
        console.log('🔄 Initializing fallback mode...');
        
        // Fonctionnalités de base uniquement
        window.TopoclimbCH = {
            version: '2.0.0-fallback',
            initialized: true,
            
            // Utilitaires de base
            utils: {
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
                }
            },
            
            // Notifications simples
            showMessage: function(message, type = 'info') {
                alert(`[${type.toUpperCase()}] ${message}`);
            }
        };
        
        console.log('✅ Fallback mode ready');
    }
    
    /**
     * 🚀 Point d'entrée principal
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeApplication);
    } else {
        initializeApplication();
    }
    
})(window, document);