/**
 * GeolocationManager - Gestionnaire de géolocalisation
 * Extrait de resources/views/geolocation/index.twig
 */
class GeolocationManager {
    constructor() {
        this.userPosition = null;
        this.watchId = null;
        this.apiClient = new APIClient();
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.checkGeolocationSupport();
    }

    setupEventListeners() {
        document.getElementById('getLocationBtn')?.addEventListener('click', () => {
            this.getCurrentLocation();
        });

        document.getElementById('searchBtn')?.addEventListener('click', () => {
            this.searchNearbyClimbing();
        });

        // Auto-search when search parameters change
        ['searchRadius', 'searchType', 'maxResults'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => {
                if (this.userPosition) {
                    this.searchNearbyClimbing();
                }
            });
        });
    }

    checkGeolocationSupport() {
        if (!navigator.geolocation) {
            this.showError('La géolocalisation n\'est pas supportée par votre navigateur');
            return false;
        }
        return true;
    }

    getCurrentLocation() {
        if (!this.checkGeolocationSupport()) return;

        const options = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 300000 // 5 minutes
        };

        this.showLocationStatus('Localisation en cours...');
        
        navigator.geolocation.getCurrentPosition(
            (position) => this.onLocationSuccess(position),
            (error) => this.onLocationError(error),
            options
        );
    }

    onLocationSuccess(position) {
        this.userPosition = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy
        };

        this.displayLocationInfo();
        this.reverseGeocode();
        this.enableSearch();
        this.searchNearbyClimbing();
    }

    onLocationError(error) {
        let message = 'Erreur de géolocalisation';
        switch(error.code) {
            case error.PERMISSION_DENIED:
                message = 'Géolocalisation refusée par l\'utilisateur';
                break;
            case error.POSITION_UNAVAILABLE:
                message = 'Position indisponible';
                break;
            case error.TIMEOUT:
                message = 'Timeout de géolocalisation';
                break;
        }
        this.showError(message);
    }

    displayLocationInfo() {
        const latElement = document.getElementById('userLatitude');
        const lngElement = document.getElementById('userLongitude');
        const accuracyElement = document.getElementById('locationAccuracy');
        
        if (latElement) latElement.textContent = this.userPosition.latitude.toFixed(6);
        if (lngElement) lngElement.textContent = this.userPosition.longitude.toFixed(6);
        if (accuracyElement) accuracyElement.textContent = Math.round(this.userPosition.accuracy) + ' m';
        
        document.getElementById('locationStatus')?.classList.add('d-none');
        document.getElementById('locationInfo')?.classList.remove('d-none');
    }

    async reverseGeocode() {
        try {
            const data = await this.apiClient.reverseGeocode(
                this.userPosition.latitude, 
                this.userPosition.longitude
            );
            
            const addressElement = document.getElementById('userAddress');
            if (addressElement) {
                if (data.results && data.results.length > 0) {
                    const address = data.results[0].address || data.results[0].locality || 'Adresse inconnue';
                    addressElement.textContent = address;
                } else {
                    addressElement.textContent = 'Adresse non trouvée';
                }
            }
        } catch (error) {
            console.error('Erreur géocodage inverse:', error);
            const addressElement = document.getElementById('userAddress');
            if (addressElement) {
                addressElement.textContent = 'Erreur de géocodage';
            }
        }
    }

    enableSearch() {
        const searchBtn = document.getElementById('searchBtn');
        if (searchBtn) {
            searchBtn.disabled = false;
        }
    }

    async searchNearbyClimbing() {
        if (!this.userPosition) return;

        const radiusElement = document.getElementById('searchRadius');
        const typeElement = document.getElementById('searchType');
        const limitElement = document.getElementById('maxResults');
        
        const radius = radiusElement?.value || 50;
        const type = typeElement?.value || 'both';
        const limit = limitElement?.value || 10;

        this.showLoading();
        this.hideError();

        const promises = [];
        
        if (type === 'sites' || type === 'both') {
            promises.push(this.searchNearestSites(radius, limit));
        }
        
        if (type === 'sectors' || type === 'both') {
            promises.push(this.searchNearestSectors(radius, limit));
        }

        try {
            const results = await Promise.all(promises);
            this.displayResults(results, type);
        } catch (error) {
            this.showError('Erreur lors de la recherche: ' + error.message);
        } finally {
            this.hideLoading();
        }
    }

    async searchNearestSites(radius, limit) {
        try {
            const data = await this.apiClient.getNearestSites(
                this.userPosition.latitude,
                this.userPosition.longitude,
                radius,
                limit
            );
            return { type: 'sites', data };
        } catch (error) {
            throw new Error(`Sites search failed: ${error.message}`);
        }
    }

    async searchNearestSectors(radius, limit) {
        try {
            const data = await this.apiClient.getNearestSectors(
                this.userPosition.latitude,
                this.userPosition.longitude,
                radius,
                limit
            );
            return { type: 'sectors', data };
        } catch (error) {
            throw new Error(`Sectors search failed: ${error.message}`);
        }
    }

    displayResults(results, searchType) {
        const resultsGrid = document.getElementById('resultsGrid');
        if (!resultsGrid) return;
        
        resultsGrid.innerHTML = '';

        let totalResults = 0;
        let allItems = [];

        results.forEach(result => {
            if (result.type === 'sites') {
                allItems.push(...result.data.results.map(item => ({
                    ...item,
                    type: 'site'
                })));
            } else if (result.type === 'sectors') {
                allItems.push(...result.data.results.map(item => ({
                    ...item,
                    type: 'sector'
                })));
            }
        });

        // Trier par distance
        allItems.sort((a, b) => a.distance_km - b.distance_km);

        totalResults = allItems.length;

        if (totalResults === 0) {
            resultsGrid.innerHTML = '<div class="alert alert-info">Aucun site d\'escalade trouvé dans le rayon spécifié.</div>';
        } else {
            allItems.forEach(item => {
                resultsGrid.appendChild(this.createResultCard(item));
            });
        }

        const resultsCount = document.getElementById('resultsCount');
        if (resultsCount) {
            resultsCount.textContent = totalResults;
        }
        
        document.getElementById('resultsSection')?.classList.remove('d-none');
    }

    createResultCard(item) {
        const card = document.createElement('div');
        card.className = 'result-card card mb-3';
        
        const typeInfo = item.type === 'site' ? 
            { icon: 'mountain', name: item.site.name, entity: item.site } :
            { icon: 'circle', name: item.sector.name, entity: item.sector };

        card.innerHTML = `
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="result-info">
                        <h5 class="card-title">
                            <i class="fa fa-${typeInfo.icon}"></i>
                            ${typeInfo.name}
                        </h5>
                        <p class="card-text">
                            <small class="text-muted">
                                ${item.type === 'site' ? 'Site d\'escalade' : 'Secteur'} - 
                                ${typeInfo.entity.region_name || 'Région inconnue'}
                            </small>
                        </p>
                        ${item.type === 'sector' ? 
                            `<p class="card-text">
                                <small class="text-muted">
                                    Site: ${item.sector.site_name || 'Non spécifié'}
                                </small>
                            </p>` : ''
                        }
                    </div>
                    <div class="result-distance">
                        <span class="badge badge-primary badge-lg">
                            ${item.distance_km} km
                        </span>
                    </div>
                </div>
                
                <div class="travel-info mt-2">
                    <small class="text-muted">
                        <i class="fa fa-car"></i>
                        ${item.travel_time.driving.total_minutes} min en voiture
                        + ${item.travel_time.approach.minutes} min à pied
                    </small>
                </div>
                
                <div class="card-actions mt-3">
                    <a href="${item.type === 'site' ? '/sites/' + typeInfo.entity.id : '/sectors/' + typeInfo.entity.id}" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="fa fa-info-circle"></i> Détails
                    </a>
                    ${item.type === 'site' ? 
                        `<a href="/geolocation/directions/${typeInfo.entity.id}?lat=${this.userPosition.latitude}&lng=${this.userPosition.longitude}" 
                           class="btn btn-success btn-sm">
                            <i class="fa fa-directions"></i> Navigation
                        </a>` : ''
                    }
                </div>
            </div>
        `;

        return card;
    }

    showLoading() {
        document.getElementById('loadingSection')?.classList.remove('d-none');
        document.getElementById('resultsSection')?.classList.add('d-none');
    }

    hideLoading() {
        document.getElementById('loadingSection')?.classList.add('d-none');
    }

    showError(message) {
        const errorMessage = document.getElementById('errorMessage');
        const errorSection = document.getElementById('errorSection');
        
        if (errorMessage) errorMessage.textContent = message;
        if (errorSection) errorSection.classList.remove('d-none');
    }

    hideError() {
        document.getElementById('errorSection')?.classList.add('d-none');
    }

    showLocationStatus(message) {
        const locationStatus = document.getElementById('locationStatus');
        if (locationStatus) {
            locationStatus.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Chargement...</span>
                    </div>
                    <p class="mt-2">${message}</p>
                </div>
            `;
        }
    }
}

// Export pour utilisation comme module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = GeolocationManager;
}