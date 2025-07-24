/**
 * APIClient - Client HTTP simplifié pour les APIs TopoclimbCH
 * Utilitaire extrait des templates Twig pour réutilisabilité
 */
class APIClient {
    constructor(options = {}) {
        this.baseUrl = options.baseUrl || '';
        this.timeout = options.timeout || 10000;
        this.headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };
    }
    
    /**
     * Requête GET générique
     */
    async get(endpoint, params = {}) {
        const url = this._buildUrl(endpoint, params);
        
        try {
            const response = await this._fetchWithTimeout(url, {
                method: 'GET',
                headers: this.headers
            });
            
            return await this._handleResponse(response);
        } catch (error) {
            console.error(`GET ${endpoint} failed:`, error);
            throw error;
        }
    }
    
    /**
     * Requête POST générique
     */
    async post(endpoint, data = {}, options = {}) {
        try {
            const response = await this._fetchWithTimeout(this._buildUrl(endpoint), {
                method: 'POST',
                headers: {
                    ...this.headers,
                    ...options.headers
                },
                body: JSON.stringify(data)
            });
            
            return await this._handleResponse(response);
        } catch (error) {
            console.error(`POST ${endpoint} failed:`, error);
            throw error;
        }
    }
    
    /**
     * Requête DELETE générique
     */
    async delete(endpoint, options = {}) {
        try {
            const response = await this._fetchWithTimeout(this._buildUrl(endpoint), {
                method: 'DELETE',
                headers: {
                    ...this.headers,
                    ...options.headers
                }
            });
            
            return await this._handleResponse(response);
        } catch (error) {
            console.error(`DELETE ${endpoint} failed:`, error);
            throw error;
        }
    }
    
    /**
     * Requêtes API spécifiques TopoclimbCH
     */
    
    // Régions
    async getRegions() {
        return this.get('/api/regions');
    }
    
    // Sites
    async getSites(params = {}) {
        return this.get('/api/sites', params);
    }
    
    // Secteurs
    async getSectors(params = {}) {
        return this.get('/api/sectors', params);
    }
    
    // Voies
    async getRoutes(params = {}) {
        return this.get('/api/routes', params);
    }
    
    // Géolocalisation - Sites proches
    async getNearestSites(lat, lng, radius = 50, limit = 10) {
        return this.get('/api/geolocation/nearest-sites', {
            lat, lng, radius, limit
        });
    }
    
    // Géolocalisation - Secteurs proches
    async getNearestSectors(lat, lng, radius = 50, limit = 10) {
        return this.get('/api/geolocation/nearest-sectors', {
            lat, lng, radius, limit
        });
    }
    
    // Géocodage inverse
    async reverseGeocode(lat, lng) {
        return this.get('/api/geolocation/reverse-geocode', { lat, lng });
    }
    
    // Météo
    async getWeather(lat, lng) {
        return this.get('/api/weather/current', { lat, lng });
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
        const url = new URL(endpoint, window.location.origin);
        
        // Ajouter les paramètres de requête
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
                throw new Error(`Request timeout (${this.timeout}ms)`);
            }
            throw error;
        }
    }
    
    async _handleResponse(response) {
        if (!response.ok) {
            const error = new Error(`HTTP ${response.status}: ${response.statusText}`);
            error.status = response.status;
            error.response = response;
            
            // Essayer de récupérer le message d'erreur JSON
            try {
                const errorData = await response.json();
                error.data = errorData;
                error.message = errorData.error || errorData.message || error.message;
            } catch (jsonError) {
                // Ignorer les erreurs de parsing JSON
            }
            
            throw error;
        }
        
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return await response.json();
        }
        
        return await response.text();
    }
    
    /**
     * Méthode utilitaire pour les promesses multiples avec fallback
     */
    static async getAllWithFallback(promises) {
        const results = await Promise.allSettled(promises);
        
        return results.map((result, index) => {
            if (result.status === 'fulfilled') {
                return result.value;
            } else {
                console.warn(`Promise ${index} failed:`, result.reason);
                return { error: result.reason.message, data: [] };
            }
        });
    }
}

// Export pour utilisation comme module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = APIClient;
}