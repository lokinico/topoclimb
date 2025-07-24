/**
 * CoordinatesHelper - Utilitaires pour coordonnées géographiques
 * Extrait des templates pour gérer les coordonnées suisses et WGS84
 */
class CoordinatesHelper {
    
    /**
     * Valide des coordonnées latitude/longitude
     */
    static isValidLatLng(lat, lng) {
        const latitude = parseFloat(lat);
        const longitude = parseFloat(lng);
        
        return !isNaN(latitude) && !isNaN(longitude) &&
               latitude >= -90 && latitude <= 90 &&
               longitude >= -180 && longitude <= 180 &&
               latitude !== 0 && longitude !== 0; // Éviter les coordonnées nulles
    }
    
    /**
     * Normalise les coordonnées de différents formats d'API
     */
    static normalizeCoordinates(data) {
        // Format standard: latitude/longitude
        let lat = data.latitude || data.lat;
        let lng = data.longitude || data.lng;
        
        // Format alternatif: coordinates_lat/coordinates_lng
        if (!lat || !lng) {
            lat = data.coordinates_lat;
            lng = data.coordinates_lng;
        }
        
        // Conversion en nombres
        lat = parseFloat(lat);
        lng = parseFloat(lng);
        
        return {
            latitude: lat,
            longitude: lng,
            valid: this.isValidLatLng(lat, lng)
        };
    }
    
    /**
     * Calcule la distance entre deux points (formule Haversine)
     */
    static calculateDistance(lat1, lng1, lat2, lng2) {
        const R = 6371; // Rayon de la Terre en km
        
        const dLat = this.toRadians(lat2 - lat1);
        const dLng = this.toRadians(lng2 - lng1);
        
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(this.toRadians(lat1)) * Math.cos(this.toRadians(lat2)) *
                  Math.sin(dLng / 2) * Math.sin(dLng / 2);
        
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        
        return R * c; // Distance en km
    }
    
    /**
     * Convertit degrés en radians
     */
    static toRadians(degrees) {
        return degrees * (Math.PI / 180);
    }
    
    /**
     * Convertit radians en degrés
     */
    static toDegrees(radians) {
        return radians * (180 / Math.PI);
    }
    
    /**
     * Formate les coordonnées pour affichage
     */
    static formatCoordinates(lat, lng, precision = 6) {
        return {
            latitude: parseFloat(lat).toFixed(precision),
            longitude: parseFloat(lng).toFixed(precision),
            display: `${parseFloat(lat).toFixed(precision)}, ${parseFloat(lng).toFixed(precision)}`
        };
    }
    
    /**
     * Centre de la Suisse (coordonnées par défaut)
     */
    static getSwissCenter() {
        return {
            latitude: 46.8182,
            longitude: 8.2275
        };
    }
    
    /**
     * Vérifie si des coordonnées sont dans les limites suisses
     */
    static isInSwitzerland(lat, lng) {
        const latitude = parseFloat(lat);
        const longitude = parseFloat(lng);
        
        // Approximation des limites suisses
        return latitude >= 45.8 && latitude <= 47.8 &&
               longitude >= 5.9 && longitude <= 10.5;
    }
    
    /**
     * Génère des coordonnées aléatoires en Suisse (pour tests)
     */
    static generateRandomSwissCoordinates() {
        // Régions principales d'escalade en Suisse
        const regions = [
            { name: 'Valais', lat: 46.2044, lng: 7.6662, radius: 0.5 },
            { name: 'Vaud', lat: 46.5197, lng: 6.6323, radius: 0.3 },
            { name: 'Tessin', lat: 46.3353, lng: 8.8019, radius: 0.4 },
            { name: 'Grisons', lat: 46.6566, lng: 9.5781, radius: 0.6 },
            { name: 'Bernese', lat: 46.7985, lng: 7.8677, radius: 0.4 }
        ];
        
        // Choisir une région aléatoire
        const region = regions[Math.floor(Math.random() * regions.length)];
        
        // Générer des coordonnées autour de cette région
        const latOffset = (Math.random() - 0.5) * region.radius;
        const lngOffset = (Math.random() - 0.5) * region.radius;
        
        return {
            latitude: region.lat + latOffset,
            longitude: region.lng + lngOffset,
            region: region.name
        };
    }
    
    /**
     * Calcule les limites (bounds) pour un ensemble de coordonnées
     */
    static calculateBounds(coordinates) {
        if (!coordinates || coordinates.length === 0) {
            return null;
        }
        
        let minLat = Infinity, maxLat = -Infinity;
        let minLng = Infinity, maxLng = -Infinity;
        
        coordinates.forEach(coord => {
            const normalized = this.normalizeCoordinates(coord);
            if (normalized.valid) {
                minLat = Math.min(minLat, normalized.latitude);
                maxLat = Math.max(maxLat, normalized.latitude);
                minLng = Math.min(minLng, normalized.longitude);
                maxLng = Math.max(maxLng, normalized.longitude);
            }
        });
        
        if (minLat === Infinity) {
            return null; // Aucune coordonnée valide
        }
        
        return {
            southwest: { lat: minLat, lng: minLng },
            northeast: { lat: maxLat, lng: maxLng },
            center: {
                lat: (minLat + maxLat) / 2,
                lng: (minLng + maxLng) / 2
            }
        };
    }
    
    /**
     * Convertit des coordonnées suisses (LV95) vers WGS84
     * Approximation simplifiée pour TopoclimbCH
     */
    static lv95ToWgs84(east, north) {
        // Formule de conversion approximative
        // Source: swisstopo documentation
        
        const x = (east - 2600000) / 1000000;
        const y = (north - 1200000) / 1000000;
        
        const lambda = 2.6779094 + 4.728982 * x + 0.791484 * x * y + 0.1306 * x * Math.pow(y, 2) - 0.0436 * Math.pow(x, 3);
        const phi = 16.9023892 + 3.238272 * y - 0.270978 * Math.pow(x, 2) - 0.002528 * Math.pow(y, 2) - 0.0447 * Math.pow(x, 2) * y - 0.0140 * Math.pow(y, 3);
        
        return {
            latitude: phi * 100 / 36,
            longitude: lambda * 100 / 36
        };
    }
    
    /**
     * Utilitaire pour input HTML coordinates
     */
    static setupCoordinatesInput(latInputId, lngInputId, onUpdate = null) {
        const latInput = document.getElementById(latInputId);
        const lngInput = document.getElementById(lngInputId);
        
        if (!latInput || !lngInput) {
            console.warn('Coordinates inputs not found');
            return;
        }
        
        const validateAndUpdate = () => {
            const lat = latInput.value;
            const lng = lngInput.value;
            
            if (lat && lng) {
                const normalized = this.normalizeCoordinates({ latitude: lat, longitude: lng });
                
                if (normalized.valid) {
                    latInput.classList.remove('is-invalid');
                    lngInput.classList.remove('is-invalid');
                    
                    if (onUpdate) {
                        onUpdate(normalized.latitude, normalized.longitude);
                    }
                } else {
                    latInput.classList.add('is-invalid');
                    lngInput.classList.add('is-invalid');
                }
            }
        };
        
        latInput.addEventListener('change', validateAndUpdate);
        lngInput.addEventListener('change', validateAndUpdate);
        
        return {
            setCoordinates: (lat, lng) => {
                latInput.value = parseFloat(lat).toFixed(8);
                lngInput.value = parseFloat(lng).toFixed(8);
                validateAndUpdate();
            },
            getCoordinates: () => {
                return this.normalizeCoordinates({
                    latitude: latInput.value,
                    longitude: lngInput.value
                });
            }
        };
    }
}

// Export pour utilisation comme module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CoordinatesHelper;
}