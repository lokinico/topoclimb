/**
 * TopoclimbCH API Client
 * Client HTTP moderne avec gestion d'erreurs avancée
 */

// Enregistrement du module API
TopoclimbCH.modules.register('api', (utils) => {
    
    /**
     * Erreurs API personnalisées
     */
    class ApiError extends Error {
        constructor(message, status, response, request) {
            super(message);
            this.name = 'ApiError';
            this.status = status;
            this.response = response;
            this.request = request;
        }
    }
    
    class NetworkError extends Error {
        constructor(message, request) {
            super(message);
            this.name = 'NetworkError';
            this.request = request;
        }
    }
    
    class TimeoutError extends Error {
        constructor(message, request) {
            super(message);
            this.name = 'TimeoutError';
            this.request = request;
        }
    }
    
    /**
     * Client API principal
     */
    class ApiClient {
        constructor(options = {}) {
            this.baseUrl = options.baseUrl || TopoclimbCH.config.apiBaseUrl;
            this.timeout = options.timeout || TopoclimbCH.config.apiTimeout;
            this.headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...options.headers
            };
            
            // Intercepteurs
            this.requestInterceptors = [];
            this.responseInterceptors = [];
            
            // Cache des requêtes
            this.cache = new Map();
            this.cacheTimeout = options.cacheTimeout || 300000; // 5 minutes
            
            // Statistiques
            this.stats = {
                requests: 0,
                errors: 0,
                cacheHits: 0,
                totalTime: 0
            };
        }
        
        /**
         * Ajoute un intercepteur de requête
         */
        addRequestInterceptor(interceptor) {
            this.requestInterceptors.push(interceptor);
            return this;
        }
        
        /**
         * Ajoute un intercepteur de réponse
         */
        addResponseInterceptor(interceptor) {
            this.responseInterceptors.push(interceptor);
            return this;
        }
        
        /**
         * Requête HTTP générique
         */
        async request(url, options = {}) {
            const startTime = performance.now();
            this.stats.requests++;
            
            try {
                // Construction de l'URL complète
                const fullUrl = this._buildUrl(url, options.params);
                
                // Vérification du cache
                if (options.method === 'GET' || !options.method) {
                    const cached = this._getFromCache(fullUrl);
                    if (cached) {
                        this.stats.cacheHits++;
                        return cached;
                    }
                }
                
                // Configuration de la requête
                const config = {
                    method: 'GET',
                    headers: { ...this.headers },
                    ...options
                };
                
                // Suppression des paramètres custom
                delete config.params;
                delete config.cache;
                delete config.retry;
                
                // Application des intercepteurs de requête
                for (const interceptor of this.requestInterceptors) {
                    await interceptor(config);
                }
                
                // Exécution de la requête avec timeout
                const response = await this._fetchWithTimeout(fullUrl, config);
                
                // Application des intercepteurs de réponse
                for (const interceptor of this.responseInterceptors) {
                    await interceptor(response);
                }
                
                // Parsing de la réponse
                const data = await this._parseResponse(response);
                
                // Mise en cache pour les GET
                if ((options.method === 'GET' || !options.method) && options.cache !== false) {
                    this._setCache(fullUrl, data);
                }
                
                // Mise à jour des statistiques
                this.stats.totalTime += performance.now() - startTime;
                
                TopoclimbCH.events.emit('api:success', {
                    url: fullUrl,
                    method: config.method,
                    data,
                    duration: performance.now() - startTime
                });
                
                return data;
                
            } catch (error) {
                this.stats.errors++;
                this.stats.totalTime += performance.now() - startTime;
                
                TopoclimbCH.events.emit('api:error', {
                    url,
                    error,
                    duration: performance.now() - startTime
                });
                
                // Retry automatique si configuré
                if (options.retry && options.retry > 0) {
                    const retryOptions = { ...options, retry: options.retry - 1 };
                    await utils.sleep(1000); // Attente avant retry
                    return this.request(url, retryOptions);
                }
                
                throw error;
            }
        }
        
        /**
         * Méthodes HTTP simplifiées
         */
        async get(url, params = {}, options = {}) {
            return this.request(url, {
                method: 'GET',
                params,
                ...options
            });
        }
        
        async post(url, data = {}, options = {}) {
            return this.request(url, {
                method: 'POST',
                body: JSON.stringify(data),
                ...options
            });
        }
        
        async put(url, data = {}, options = {}) {
            return this.request(url, {
                method: 'PUT',
                body: JSON.stringify(data),
                ...options
            });
        }
        
        async patch(url, data = {}, options = {}) {
            return this.request(url, {
                method: 'PATCH',
                body: JSON.stringify(data),
                ...options
            });
        }
        
        async delete(url, options = {}) {
            return this.request(url, {
                method: 'DELETE',
                ...options
            });
        }
        
        /**
         * APIs spécifiques TopoclimbCH
         */
        
        // Régions
        async getRegions(params = {}) {
            return this.get('/api/regions', params);
        }
        
        async getRegion(id) {
            return this.get(`/api/regions/${id}`);
        }
        
        // Sites
        async getSites(params = {}) {
            return this.get('/api/sites', params);
        }
        
        async getSite(id) {
            return this.get(`/api/sites/${id}`);
        }
        
        // Secteurs
        async getSectors(params = {}) {
            return this.get('/api/sectors', params);
        }
        
        async getSector(id) {
            return this.get(`/api/sectors/${id}`);
        }
        
        // Voies
        async getRoutes(params = {}) {
            return this.get('/api/routes', params);
        }
        
        async getRoute(id) {
            return this.get(`/api/routes/${id}`);
        }
        
        // Géolocalisation
        async getNearestSites(lat, lng, radius = 50, limit = 10) {
            return this.get('/api/geolocation/nearest-sites', {
                lat, lng, radius, limit
            });
        }
        
        async getNearestSectors(lat, lng, radius = 50, limit = 10) {
            return this.get('/api/geolocation/nearest-sectors', {
                lat, lng, radius, limit
            });
        }
        
        async reverseGeocode(lat, lng) {
            return this.get('/api/geolocation/reverse-geocode', { lat, lng });
        }
        
        // Météo
        async getWeather(lat, lng) {
            return this.get('/api/weather/current', { lat, lng }, {
                cache: true
            });
        }
        
        // Médias
        async deleteMedia(mediaId, csrfToken) {
            return this.delete(`/api/media/${mediaId}`, {
                headers: {
                    'X-CSRF-Token': csrfToken
                }
            });
        }
        
        /**
         * Méthodes utilitaires internes
         */
        
        _buildUrl(endpoint, params = {}) {
            const url = new URL(endpoint, this.baseUrl || window.location.origin);
            
            Object.keys(params).forEach(key => {
                if (params[key] !== null && params[key] !== undefined) {
                    url.searchParams.append(key, params[key]);
                }
            });
            
            return url.toString();
        }
        
        async _fetchWithTimeout(url, options) {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), this.timeout);
            
            try {
                const response = await fetch(url, {
                    ...options,
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                return response;
                
            } catch (error) {
                clearTimeout(timeoutId);
                
                if (error.name === 'AbortError') {
                    throw new TimeoutError(`Request timeout (${this.timeout}ms)`, { url, options });
                }
                
                throw new NetworkError(error.message, { url, options });
            }
        }
        
        async _parseResponse(response) {
            if (!response.ok) {
                let errorData;
                try {
                    errorData = await response.json();
                } catch (e) {
                    errorData = { message: response.statusText };
                }
                
                throw new ApiError(
                    errorData.message || `HTTP ${response.status}: ${response.statusText}`,
                    response.status,
                    errorData,
                    response.url
                );
            }
            
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            }
            
            return await response.text();
        }
        
        _getFromCache(url) {
            const cached = this.cache.get(url);
            if (cached && Date.now() - cached.timestamp < this.cacheTimeout) {
                return cached.data;
            }
            
            this.cache.delete(url);
            return null;
        }
        
        _setCache(url, data) {
            this.cache.set(url, {
                data,
                timestamp: Date.now()
            });
            
            // Nettoyage automatique du cache
            if (this.cache.size > 100) {
                const oldestKey = this.cache.keys().next().value;
                this.cache.delete(oldestKey);
            }
        }
        
        /**
         * Utilitaires publiques
         */
        
        clearCache() {
            this.cache.clear();
        }
        
        getStats() {
            return {
                ...this.stats,
                averageTime: this.stats.requests > 0 ? this.stats.totalTime / this.stats.requests : 0,
                errorRate: this.stats.requests > 0 ? this.stats.errors / this.stats.requests : 0,
                cacheHitRate: this.stats.requests > 0 ? this.stats.cacheHits / this.stats.requests : 0
            };
        }
    }
    
    // Instance globale du client API
    const api = new ApiClient();
    
    // Intercepteur pour ajouter le token CSRF automatiquement
    api.addRequestInterceptor(async (config) => {
        const token = document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content');
        if (token && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(config.method)) {
            config.headers['X-CSRF-Token'] = token;
        }
    });
    
    // Intercepteur pour le debug
    if (TopoclimbCH.debug) {
        api.addRequestInterceptor(async (config) => {
            console.log(`🔄 API Request: ${config.method} ${config.url}`, config);
        });
        
        api.addResponseInterceptor(async (response) => {
            console.log(`✅ API Response: ${response.status} ${response.url}`);
        });
    }
    
    // Exposer dans le namespace global
    TopoclimbCH.api = api;
    
    // Exposer les classes d'erreur
    TopoclimbCH.ApiError = ApiError;
    TopoclimbCH.NetworkError = NetworkError;
    TopoclimbCH.TimeoutError = TimeoutError;
    
    return api;
}, ['utils']);

console.log('🌐 TopoclimbCH API module ready');