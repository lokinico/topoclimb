/**
 * Système d'Analytics pour le Système de Vues TopoclimbCH
 * Collecte des métriques d'usage et d'interaction utilisateur
 */

class ViewAnalytics {
    constructor(config = {}) {
        this.config = {
            endpoint: '/api/analytics/views',
            enabled: true,
            batchSize: 10,
            flushInterval: 30000, // 30 secondes
            ...config
        };
        
        this.events = [];
        this.sessionId = this.generateSessionId();
        this.startTime = Date.now();
        
        if (this.config.enabled) {
            this.init();
        }
    }
    
    init() {
        this.setupEventListeners();
        this.startPeriodicFlush();
        this.trackPageView();
    }
    
    generateSessionId() {
        return 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    /**
     * Configuration des événements à tracker
     */
    setupEventListeners() {
        // Tracker les changements de vue
        document.addEventListener('viewChanged', (e) => {
            this.trackViewChange(e.detail);
        });
        
        // Tracker les clics sur boutons de vue
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-view]')) {
                const button = e.target.closest('[data-view]');
                this.trackViewButtonClick(button.dataset.view, e);
            }
        });
        
        // Tracker l'utilisation du clavier
        document.addEventListener('keydown', (e) => {
            const activeElement = document.activeElement;
            if (activeElement && activeElement.hasAttribute('data-view')) {
                if (e.key === 'Enter' || e.key === ' ') {
                    this.trackKeyboardInteraction(activeElement.dataset.view, e.key);
                }
            }
        });
        
        // Tracker la performance de chargement
        window.addEventListener('load', () => {
            this.trackPageLoadTime();
        });
        
        // Tracker les erreurs JavaScript
        window.addEventListener('error', (e) => {
            this.trackError(e);
        });
        
        // Tracker le temps passé sur la page
        window.addEventListener('beforeunload', () => {
            this.trackSessionDuration();
            this.flush();
        });
    }
    
    /**
     * Tracker les changements de vue
     */
    trackViewChange(data) {
        const event = {
            type: 'view_change',
            timestamp: Date.now(),
            sessionId: this.sessionId,
            data: {
                fromView: data.from,
                toView: data.to,
                page: this.getCurrentPage(),
                loadTime: data.loadTime || null,
                userAgent: navigator.userAgent,
                viewport: {
                    width: window.innerWidth,
                    height: window.innerHeight
                }
            }
        };
        
        this.addEvent(event);
        
        // Custom event pour intégrations externes (Google Analytics, etc.)
        if (typeof gtag !== 'undefined') {
            gtag('event', 'view_change', {
                event_category: 'UI Interaction',
                event_label: `${data.from} to ${data.to}`,
                page_title: document.title
            });
        }
    }
    
    /**
     * Tracker les clics sur boutons
     */
    trackViewButtonClick(viewType, event) {
        const button = event.target.closest('[data-view]');
        const rect = button.getBoundingClientRect();
        
        const analyticsEvent = {
            type: 'button_click',
            timestamp: Date.now(),
            sessionId: this.sessionId,
            data: {
                viewType: viewType,
                page: this.getCurrentPage(),
                buttonPosition: {
                    x: rect.left,
                    y: rect.top
                },
                clickPosition: {
                    x: event.clientX,
                    y: event.clientY
                },
                wasActive: button.classList.contains('active')
            }
        };
        
        this.addEvent(analyticsEvent);
    }
    
    /**
     * Tracker les interactions clavier
     */
    trackKeyboardInteraction(viewType, key) {
        const event = {
            type: 'keyboard_interaction',
            timestamp: Date.now(),
            sessionId: this.sessionId,
            data: {
                viewType: viewType,
                key: key,
                page: this.getCurrentPage()
            }
        };
        
        this.addEvent(event);
    }
    
    /**
     * Tracker les performances de chargement
     */
    trackPageLoadTime() {
        if (performance && performance.timing) {
            const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
            
            const event = {
                type: 'page_performance',
                timestamp: Date.now(),
                sessionId: this.sessionId,
                data: {
                    page: this.getCurrentPage(),
                    loadTime: loadTime,
                    domContentLoaded: performance.timing.domContentLoadedEventEnd - performance.timing.navigationStart,
                    resourcesLoaded: performance.timing.loadEventEnd - performance.timing.domContentLoadedEventEnd
                }
            };
            
            this.addEvent(event);
        }
    }
    
    /**
     * Tracker les erreurs JavaScript
     */
    trackError(error) {
        const event = {
            type: 'javascript_error',
            timestamp: Date.now(),
            sessionId: this.sessionId,
            data: {
                message: error.message,
                filename: error.filename,
                line: error.lineno,
                column: error.colno,
                page: this.getCurrentPage(),
                userAgent: navigator.userAgent
            }
        };
        
        this.addEvent(event);
    }
    
    /**
     * Tracker la vue de page
     */
    trackPageView() {
        const event = {
            type: 'page_view',
            timestamp: Date.now(),
            sessionId: this.sessionId,
            data: {
                page: this.getCurrentPage(),
                referrer: document.referrer,
                userAgent: navigator.userAgent,
                viewport: {
                    width: window.innerWidth,
                    height: window.innerHeight
                },
                language: navigator.language
            }
        };
        
        this.addEvent(event);
    }
    
    /**
     * Tracker la durée de session
     */
    trackSessionDuration() {
        const duration = Date.now() - this.startTime;
        
        const event = {
            type: 'session_duration',
            timestamp: Date.now(),
            sessionId: this.sessionId,
            data: {
                duration: duration,
                page: this.getCurrentPage()
            }
        };
        
        this.addEvent(event);
    }
    
    /**
     * Ajouter un événement à la queue
     */
    addEvent(event) {
        this.events.push(event);
        
        // Flush automatique si la queue est pleine
        if (this.events.length >= this.config.batchSize) {
            this.flush();
        }
    }
    
    /**
     * Envoyer les événements au serveur
     */
    async flush() {
        if (this.events.length === 0) return;
        
        const eventsToSend = [...this.events];
        this.events = [];
        
        try {
            const response = await fetch(this.config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    events: eventsToSend,
                    meta: {
                        sessionId: this.sessionId,
                        timestamp: Date.now(),
                        userAgent: navigator.userAgent
                    }
                })
            });
            
            if (!response.ok) {
                console.warn('Analytics: Failed to send events', response.status);
                // Remettre les événements dans la queue en cas d'échec
                this.events.unshift(...eventsToSend);
            }
        } catch (error) {
            console.warn('Analytics: Network error', error);
            // Remettre les événements dans la queue en cas d'échec
            this.events.unshift(...eventsToSend);
        }
    }
    
    /**
     * Démarrer le flush périodique
     */
    startPeriodicFlush() {
        setInterval(() => {
            this.flush();
        }, this.config.flushInterval);
    }
    
    /**
     * Obtenir la page courante
     */
    getCurrentPage() {
        const path = window.location.pathname;
        return path.substring(1) || 'home'; // Supprimer le / initial
    }
    
    /**
     * Méthodes publiques pour tracking personnalisé
     */
    
    /**
     * Tracker un événement personnalisé
     */
    track(eventType, data = {}) {
        const event = {
            type: eventType,
            timestamp: Date.now(),
            sessionId: this.sessionId,
            data: {
                page: this.getCurrentPage(),
                ...data
            }
        };
        
        this.addEvent(event);
    }
    
    /**
     * Obtenir les statistiques de session courante
     */
    getSessionStats() {
        return {
            sessionId: this.sessionId,
            startTime: this.startTime,
            duration: Date.now() - this.startTime,
            eventsQueued: this.events.length,
            currentPage: this.getCurrentPage()
        };
    }
    
    /**
     * Activer/désactiver l'analytics
     */
    setEnabled(enabled) {
        this.config.enabled = enabled;
        
        if (!enabled) {
            // Flush final avant désactivation
            this.flush();
        }
    }
}

// Auto-initialisation si ViewManager est présent
document.addEventListener('DOMContentLoaded', function() {
    // Vérifier si on est sur une page avec le système de vues
    if (document.querySelector('.entities-container') && window.ViewManager) {
        
        // Configuration par défaut
        const analyticsConfig = {
            endpoint: '/api/analytics/views',
            enabled: true,
            batchSize: 5, // Plus petit pour le développement
            flushInterval: 15000 // Plus fréquent pour tests
        };
        
        // Initialiser l'analytics
        window.viewAnalytics = new ViewAnalytics(analyticsConfig);
        
        // Intégrer avec ViewManager existant
        if (window.viewManager) {
            // Hook dans les changements de vue du ViewManager
            const originalSwitchView = window.viewManager.switchView;
            
            window.viewManager.switchView = function(viewType) {
                const previousView = this.currentView;
                const startTime = performance.now();
                
                // Appeler la méthode originale
                const result = originalSwitchView.call(this, viewType);
                
                const endTime = performance.now();
                const loadTime = endTime - startTime;
                
                // Dispatch custom event pour analytics
                document.dispatchEvent(new CustomEvent('viewChanged', {
                    detail: {
                        from: previousView,
                        to: viewType,
                        loadTime: loadTime
                    }
                }));
                
                return result;
            };
        }
        
        console.log('🔍 ViewAnalytics initialized for', window.location.pathname);
    }
});

// Export pour usage externe
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ViewAnalytics;
}

// Global pour access depuis la console
window.ViewAnalytics = ViewAnalytics;