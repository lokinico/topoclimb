/**
 * TopoclimbCH Core Utilities
 * Utilitaires de base unifiés et modernes
 */

// Enregistrement du module Utils
TopoclimbCH.modules.register('utils', () => {
    
    const Utils = {
        
        /**
         * 🔄 Fonctions de contrôle de flux
         */
        
        /**
         * Debounce function - limite la fréquence d'exécution
         */
        debounce(func, wait, immediate = false) {
            let timeout;
            return function executedFunction(...args) {
                const context = this;
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
         * Throttle function - limite le taux d'exécution
         */
        throttle(func, limit) {
            let inThrottle;
            return function(...args) {
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },
        
        /**
         * 📊 Fonctions de formatage
         */
        
        /**
         * Formate un nombre avec séparateurs de milliers
         */
        formatNumber(num, options = {}) {
            const defaults = {
                locale: TopoclimbCH.config.locale,
                ...options
            };
            
            return new Intl.NumberFormat(defaults.locale, defaults).format(num);
        },
        
        /**
         * Formate une distance en km/m
         */
        formatDistance(km, options = {}) {
            const { precision = 1, showUnit = true } = options;
            
            if (km < 1) {
                const meters = Math.round(km * 1000);
                return showUnit ? `${meters} m` : meters.toString();
            }
            
            const formatted = km.toFixed(precision);
            return showUnit ? `${formatted} km` : formatted;
        },
        
        /**
         * Formate une durée en heures/minutes
         */
        formatDuration(minutes, options = {}) {
            const { showSeconds = false, compact = false } = options;
            
            const hours = Math.floor(minutes / 60);
            const mins = Math.floor(minutes % 60);
            const secs = Math.floor((minutes % 1) * 60);
            
            if (compact) {
                if (hours > 0) return `${hours}h${mins.toString().padStart(2, '0')}`;
                if (showSeconds && secs > 0) return `${mins}m${secs.toString().padStart(2, '0')}s`;
                return `${mins}min`;
            }
            
            const parts = [];
            if (hours > 0) parts.push(`${hours}h`);
            if (mins > 0) parts.push(`${mins}min`);
            if (showSeconds && secs > 0) parts.push(`${secs}s`);
            
            return parts.join(' ');
        },
        
        /**
         * Formate une date relative (il y a X temps)
         */
        formatRelativeTime(date, options = {}) {
            const { locale = TopoclimbCH.config.locale } = options;
            const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
            
            const now = new Date();
            const diffMs = date - now;
            const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24));
            
            if (Math.abs(diffDays) < 1) {
                const diffHours = Math.round(diffMs / (1000 * 60 * 60));
                if (Math.abs(diffHours) < 1) {
                    const diffMinutes = Math.round(diffMs / (1000 * 60));
                    return rtf.format(diffMinutes, 'minute');
                }
                return rtf.format(diffHours, 'hour');
            }
            
            if (Math.abs(diffDays) < 30) {
                return rtf.format(diffDays, 'day');
            }
            
            const diffMonths = Math.round(diffDays / 30);
            return rtf.format(diffMonths, 'month');
        },
        
        /**
         * 🔒 Fonctions de sécurité
         */
        
        /**
         * Échappe le HTML pour éviter les XSS
         */
        escapeHtml(text) {
            if (typeof text !== 'string') return text;
            
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        /**
         * Nettoie une chaîne pour utilisation en slug/URL
         */
        slugify(text, options = {}) {
            const { separator = '-', lowercase = true } = options;
            
            let slug = text
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '') // Supprime les accents
                .replace(/[^a-zA-Z0-9\s]/g, '') // Garde seulement alphanumériques et espaces
                .trim()
                .replace(/\s+/g, separator); // Remplace espaces par séparateur
            
            return lowercase ? slug.toLowerCase() : slug;
        },
        
        /**
         * 🆔 Génération d'identifiants
         */
        
        /**
         * Génère un ID unique
         */
        generateId(prefix = 'tc', length = 8) {
            const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
            let result = '';
            
            for (let i = 0; i < length; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            return `${prefix}_${result}`;
        },
        
        /**
         * Génère un UUID v4 simple
         */
        generateUUID() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0;
                const v = c == 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        },
        
        /**
         * 📐 Fonctions DOM et géométrie
         */
        
        /**
         * Vérifie si un élément est visible dans la viewport
         */
        isElementVisible(element, options = {}) {
            const { threshold = 0, rootMargin = '0px' } = options;
            
            if (!element || !element.getBoundingClientRect) return false;
            
            const rect = element.getBoundingClientRect();
            const windowHeight = window.innerHeight || document.documentElement.clientHeight;
            const windowWidth = window.innerWidth || document.documentElement.clientWidth;
            
            const verticalVisible = rect.top <= windowHeight - threshold && rect.bottom >= threshold;
            const horizontalVisible = rect.left <= windowWidth - threshold && rect.right >= threshold;
            
            return verticalVisible && horizontalVisible;
        },
        
        /**
         * Scroll fluide vers un élément
         */
        scrollToElement(element, options = {}) {
            if (!element) return Promise.resolve();
            
            const { offset = 0, behavior = 'smooth', block = 'start' } = options;
            
            return new Promise((resolve) => {
                const elementTop = element.getBoundingClientRect().top + window.pageYOffset - offset;
                
                window.scrollTo({
                    top: elementTop,
                    behavior
                });
                
                // Attendre la fin du scroll
                let lastScrollTop = window.pageYOffset;
                const checkScroll = () => {
                    const currentScrollTop = window.pageYOffset;
                    if (Math.abs(currentScrollTop - lastScrollTop) < 1) {
                        resolve();
                    } else {
                        lastScrollTop = currentScrollTop;
                        requestAnimationFrame(checkScroll);
                    }
                };
                
                requestAnimationFrame(checkScroll);
            });
        },
        
        /**
         * Calcule les dimensions d'un élément
         */
        getElementDimensions(element) {
            if (!element) return null;
            
            const rect = element.getBoundingClientRect();
            const styles = window.getComputedStyle(element);
            
            return {
                width: rect.width,
                height: rect.height,
                top: rect.top,
                left: rect.left,
                right: rect.right,
                bottom: rect.bottom,
                marginTop: parseFloat(styles.marginTop),
                marginBottom: parseFloat(styles.marginBottom),
                marginLeft: parseFloat(styles.marginLeft),
                marginRight: parseFloat(styles.marginRight),
                paddingTop: parseFloat(styles.paddingTop),
                paddingBottom: parseFloat(styles.paddingBottom),
                paddingLeft: parseFloat(styles.paddingLeft),
                paddingRight: parseFloat(styles.paddingRight)
            };
        },
        
        /**
         * 📱 Détection d'environnement
         */
        
        /**
         * Détecte si on est sur mobile
         */
        isMobile() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        },
        
        /**
         * Détecte si on est sur tablette
         */
        isTablet() {
            return /iPad|Android/i.test(navigator.userAgent) && window.innerWidth >= 768;
        },
        
        /**
         * Détecte les capacités du navigateur
         */
        getCapabilities() {
            return {
                touch: 'ontouchstart' in window,
                geolocation: 'geolocation' in navigator,
                serviceWorker: 'serviceWorker' in navigator,
                webgl: (() => {
                    try {
                        const canvas = document.createElement('canvas');
                        return !!(canvas.getContext('webgl') || canvas.getContext('experimental-webgl'));
                    } catch (e) {
                        return false;
                    }
                })(),
                webp: (() => {
                    const canvas = document.createElement('canvas');
                    return canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
                })(),
                localStorage: (() => {
                    try {
                        localStorage.setItem('test', 'test');
                        localStorage.removeItem('test');
                        return true;
                    } catch (e) {
                        return false;
                    }
                })()
            };
        },
        
        /**
         * 🔧 Fonctions utilitaires diverses
         */
        
        /**
         * Deep clone d'un objet
         */
        deepClone(obj) {
            if (obj === null || typeof obj !== 'object') return obj;
            if (obj instanceof Date) return new Date(obj.getTime());
            if (obj instanceof Array) return obj.map(item => this.deepClone(item));
            if (typeof obj === 'object') {
                const copy = {};
                Object.keys(obj).forEach(key => {
                    copy[key] = this.deepClone(obj[key]);
                });
                return copy;
            }
        },
        
        /**
         * Merge profond d'objets
         */
        deepMerge(target, ...sources) {
            if (!sources.length) return target;
            const source = sources.shift();
            
            if (this.isObject(target) && this.isObject(source)) {
                for (const key in source) {
                    if (this.isObject(source[key])) {
                        if (!target[key]) Object.assign(target, { [key]: {} });
                        this.deepMerge(target[key], source[key]);
                    } else {
                        Object.assign(target, { [key]: source[key] });
                    }
                }
            }
            
            return this.deepMerge(target, ...sources);
        },
        
        /**
         * Vérifie si une valeur est un objet
         */
        isObject(item) {
            return item && typeof item === 'object' && !Array.isArray(item);
        },
        
        /**
         * Attend un délai
         */
        sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },
        
        /**
         * Retry avec backoff exponentiel
         */
        async retry(fn, options = {}) {
            const { 
                maxAttempts = 3, 
                delay = 1000, 
                backoff = 2,
                onRetry = () => {}
            } = options;
            
            let lastError;
            
            for (let attempt = 1; attempt <= maxAttempts; attempt++) {
                try {
                    return await fn();
                } catch (error) {
                    lastError = error;
                    
                    if (attempt === maxAttempts) {
                        throw error;
                    }
                    
                    const waitTime = delay * Math.pow(backoff, attempt - 1);
                    onRetry(error, attempt, waitTime);
                    
                    await this.sleep(waitTime);
                }
            }
            
            throw lastError;
        }
    };
    
    // Exposer les utils dans le namespace global
    TopoclimbCH.utils = Utils;
    
    return Utils;
});

console.log('🛠️ TopoclimbCH Utils module ready');