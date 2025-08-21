/**
 * Module de conversion de coordonnées GPS ↔ LV95
 * Utilise l'API REST Swisstopo pour les transformations
 * 
 * @author TopoclimbCH
 * @version 1.0
 */

class CoordinateConverter {
    constructor() {
        this.baseUrls = {
            wgs84tolv95: 'https://geodesy.geo.admin.ch/reframe/wgs84tolv95',
            lv95towgs84: 'https://geodesy.geo.admin.ch/reframe/lv95towgs84'
        };
        
        // Cache pour éviter les appels redondants
        this.cache = new Map();
        this.cacheTimeout = 300000; // 5 minutes
    }

    /**
     * Convertit des coordonnées WGS84 (GPS) vers LV95 (coordonnées suisses)
     * 
     * @param {number} longitude - Longitude en degrés décimaux (ex: 7.43863)
     * @param {number} latitude - Latitude en degrés décimaux (ex: 46.95108)
     * @param {number} altitude - Altitude en mètres (optionnel)
     * @returns {Promise<Object>} Coordonnées LV95 {easting, northing, altitude?}
     */
    async convertWGS84toLV95(longitude, latitude, altitude = null) {
        // Validation des entrées
        if (!this.isValidWGS84(longitude, latitude)) {
            throw new Error('Coordonnées WGS84 invalides');
        }

        const cacheKey = `wgs84_${longitude}_${latitude}_${altitude}`;
        const cached = this.getFromCache(cacheKey);
        if (cached) {
            console.log('🎯 Conversion WGS84→LV95 depuis cache');
            return cached;
        }

        // Construction de l'URL avec paramètres
        const params = new URLSearchParams({
            easting: longitude.toString(),
            northing: latitude.toString(),
            format: 'json'
        });

        if (altitude !== null && !isNaN(altitude)) {
            params.append('altitude', altitude.toString());
        }

        const url = `${this.baseUrls.wgs84tolv95}?${params.toString()}`;
        
        try {
            console.log('🔄 Conversion WGS84→LV95:', { longitude, latitude, altitude });
            
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`Erreur API: ${response.status} ${response.statusText}`);
            }

            const data = await response.json();
            
            // Validation de la réponse
            if (!data.easting || !data.northing) {
                throw new Error('Réponse API invalide');
            }

            const result = {
                easting: parseFloat(data.easting),
                northing: parseFloat(data.northing),
                altitude: data.altitude ? parseFloat(data.altitude) : null
            };

            this.setCache(cacheKey, result);
            console.log('✅ Conversion WGS84→LV95 réussie:', result);
            
            return result;

        } catch (error) {
            console.error('❌ Erreur conversion WGS84→LV95:', error);
            throw new Error(`Conversion impossible: ${error.message}`);
        }
    }

    /**
     * Convertit des coordonnées LV95 (coordonnées suisses) vers WGS84 (GPS)
     * 
     * @param {number} easting - Coordonnée Est LV95 en mètres (ex: 2600000)
     * @param {number} northing - Coordonnée Nord LV95 en mètres (ex: 1200000)
     * @param {number} altitude - Altitude en mètres (optionnel)
     * @returns {Promise<Object>} Coordonnées WGS84 {longitude, latitude, altitude?}
     */
    async convertLV95toWGS84(easting, northing, altitude = null) {
        // Validation des entrées
        if (!this.isValidLV95(easting, northing)) {
            throw new Error('Coordonnées LV95 invalides');
        }

        const cacheKey = `lv95_${easting}_${northing}_${altitude}`;
        const cached = this.getFromCache(cacheKey);
        if (cached) {
            console.log('🎯 Conversion LV95→WGS84 depuis cache');
            return cached;
        }

        // Construction de l'URL avec paramètres
        const params = new URLSearchParams({
            easting: easting.toString(),
            northing: northing.toString(),
            format: 'json'
        });

        if (altitude !== null && !isNaN(altitude)) {
            params.append('altitude', altitude.toString());
        }

        const url = `${this.baseUrls.lv95towgs84}?${params.toString()}`;
        
        try {
            console.log('🔄 Conversion LV95→WGS84:', { easting, northing, altitude });
            
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`Erreur API: ${response.status} ${response.statusText}`);
            }

            const data = await response.json();
            
            // Validation de la réponse
            if (!data.easting || !data.northing) {
                throw new Error('Réponse API invalide');
            }

            const result = {
                longitude: parseFloat(data.easting),
                latitude: parseFloat(data.northing),
                altitude: data.altitude ? parseFloat(data.altitude) : null
            };

            this.setCache(cacheKey, result);
            console.log('✅ Conversion LV95→WGS84 réussie:', result);
            
            return result;

        } catch (error) {
            console.error('❌ Erreur conversion LV95→WGS84:', error);
            throw new Error(`Conversion impossible: ${error.message}`);
        }
    }

    /**
     * Valide des coordonnées WGS84
     */
    isValidWGS84(longitude, latitude) {
        return !isNaN(longitude) && !isNaN(latitude) && 
               longitude >= -180 && longitude <= 180 &&
               latitude >= -90 && latitude <= 90;
    }

    /**
     * Valide des coordonnées LV95 (approximatif pour la Suisse)
     */
    isValidLV95(easting, northing) {
        return !isNaN(easting) && !isNaN(northing) && 
               easting >= 2400000 && easting <= 2900000 &&
               northing >= 1000000 && northing <= 1400000;
    }

    /**
     * Gestion du cache
     */
    getFromCache(key) {
        const cached = this.cache.get(key);
        if (cached && Date.now() - cached.timestamp < this.cacheTimeout) {
            return cached.data;
        }
        return null;
    }

    setCache(key, data) {
        this.cache.set(key, {
            data: data,
            timestamp: Date.now()
        });
    }

    /**
     * Nettoie le cache expiré
     */
    cleanCache() {
        const now = Date.now();
        for (const [key, value] of this.cache.entries()) {
            if (now - value.timestamp >= this.cacheTimeout) {
                this.cache.delete(key);
            }
        }
    }
}

// Instance globale
window.TopoclimbCoordinateConverter = new CoordinateConverter();

// Nettoyage automatique du cache toutes les 10 minutes
setInterval(() => {
    window.TopoclimbCoordinateConverter.cleanCache();
}, 600000);

console.log('📍 Module de conversion de coordonnées TopoclimbCH initialisé');