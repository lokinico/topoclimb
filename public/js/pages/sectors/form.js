/**
 * JavaScript pour le formulaire de secteurs
 * Inclut la conversion de coordonnées GPS ↔ Swiss LV95
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Éléments du formulaire
    const latInput = document.getElementById('coordinates_lat');
    const lngInput = document.getElementById('coordinates_lng');
    const swissEInput = document.getElementById('coordinates_swiss_e');
    const swissNInput = document.getElementById('coordinates_swiss_n');
    const convertFromSwissBtn = document.getElementById('convert-from-swiss-btn');
    const convertToSwissBtn = document.getElementById('convert-to-swiss-btn');

    /**
     * Conversion Swiss LV95 vers WGS84 (GPS)
     * Source: swisstopo formules de conversion
     */
    function swissToWGS84(east, north) {
        // Conversion LV95 vers LV03
        const y = (east - 2600000) / 1000000;
        const x = (north - 1200000) / 1000000;
        
        // Formules de conversion approchées pour la Suisse
        const lambda = 2.6779094 
            + 4.728982 * y 
            + 0.791484 * y * x 
            + 0.1306 * y * x * x 
            - 0.0436 * y * y * y;
            
        const phi = 16.9023892
            + 3.238272 * x
            - 0.270978 * y * y
            - 0.002528 * x * x
            - 0.0447 * y * y * x
            - 0.0140 * x * x * x;
            
        // Conversion en degrés décimaux
        const longitude = lambda * 100 / 36;
        const latitude = phi * 100 / 36;
        
        return {
            lat: latitude,
            lng: longitude
        };
    }

    /**
     * Conversion WGS84 (GPS) vers Swiss LV95
     * Source: swisstopo formules de conversion
     */
    function wgs84ToSwiss(lat, lng) {
        // Conversion en auxiliaires (centaines de secondes d'arc)
        const phi = lat * 3600 / 100;
        const lambda = lng * 3600 / 100;
        
        // Formules de conversion approchées
        const y = 600072.37
            + 211455.93 * lambda
            - 10938.51 * lambda * phi
            - 0.36 * lambda * phi * phi
            - 44.54 * lambda * lambda * lambda;
            
        const x = 200147.07
            + 308807.95 * phi
            + 3745.25 * lambda * lambda
            + 76.63 * phi * phi
            - 194.56 * lambda * lambda * phi
            + 119.79 * phi * phi * phi;
            
        // Conversion vers LV95
        const east = y + 2000000;
        const north = x + 1000000;
        
        return {
            east: Math.round(east),
            north: Math.round(north)
        };
    }

    /**
     * Valider les coordonnées GPS pour la Suisse
     */
    function isValidSwissGPS(lat, lng) {
        return lat >= 45.8 && lat <= 47.9 && lng >= 5.9 && lng <= 10.6;
    }

    /**
     * Valider les coordonnées Swiss LV95
     */
    function isValidSwissLV95(east, north) {
        return east >= 2480000 && east <= 2840000 && north >= 1070000 && north <= 1300000;
    }

    /**
     * Mettre à jour les coordonnées Swiss depuis GPS
     */
    function updateSwissFromGPS() {
        const lat = parseFloat(latInput.value);
        const lng = parseFloat(lngInput.value);
        
        if (isNaN(lat) || isNaN(lng)) {
            console.log('Coordonnées GPS invalides');
            return;
        }
        
        if (!isValidSwissGPS(lat, lng)) {
            alert('Les coordonnées GPS sont hors des limites de la Suisse.\nLatitude: 45.8° - 47.9°\nLongitude: 5.9° - 10.6°');
            return;
        }
        
        const swiss = wgs84ToSwiss(lat, lng);
        swissEInput.value = swiss.east;
        swissNInput.value = swiss.north;
        
        console.log(`Conversion GPS → Swiss: ${lat}, ${lng} → ${swiss.east}, ${swiss.north}`);
    }

    /**
     * Mettre à jour les coordonnées GPS depuis Swiss
     */
    function updateGPSFromSwiss() {
        const east = parseInt(swissEInput.value);
        const north = parseInt(swissNInput.value);
        
        if (isNaN(east) || isNaN(north)) {
            console.log('Coordonnées Swiss invalides');
            return;
        }
        
        if (!isValidSwissLV95(east, north)) {
            alert('Les coordonnées suisses LV95 sont invalides.\nE: 2480000 - 2840000\nN: 1070000 - 1300000');
            return;
        }
        
        const gps = swissToWGS84(east, north);
        latInput.value = gps.lat.toFixed(8);
        lngInput.value = gps.lng.toFixed(8);
        
        console.log(`Conversion Swiss → GPS: ${east}, ${north} → ${gps.lat}, ${gps.lng}`);
        
        // Mettre à jour la carte si elle existe
        if (typeof updateMapFromCoordinates === 'function') {
            updateMapFromCoordinates();
        }
    }

    // Event listeners pour les boutons de conversion
    if (convertToSwissBtn) {
        convertToSwissBtn.addEventListener('click', function(e) {
            e.preventDefault();
            updateSwissFromGPS();
        });
    }

    if (convertFromSwissBtn) {
        convertFromSwissBtn.addEventListener('click', function(e) {
            e.preventDefault();
            updateGPSFromSwiss();
        });
    }

    // Auto-conversion lors de la saisie (avec délai)
    let conversionTimeout;
    
    function scheduleConversion(fromGPS = true) {
        clearTimeout(conversionTimeout);
        conversionTimeout = setTimeout(() => {
            if (fromGPS) {
                updateSwissFromGPS();
            } else {
                updateGPSFromSwiss();
            }
        }, 1000); // Délai de 1 seconde après arrêt de saisie
    }

    // Event listeners pour auto-conversion
    if (latInput && lngInput) {
        latInput.addEventListener('input', () => scheduleConversion(true));
        lngInput.addEventListener('input', () => scheduleConversion(true));
    }

    if (swissEInput && swissNInput) {
        swissEInput.addEventListener('input', () => scheduleConversion(false));
        swissNInput.addEventListener('input', () => scheduleConversion(false));
    }

    /**
     * Fonction exposée globalement pour la carte
     */
    window.updateCoordinatesFromMap = function(lat, lng) {
        latInput.value = lat.toFixed(8);
        lngInput.value = lng.toFixed(8);
        updateSwissFromGPS();
    };

    console.log('Système de conversion de coordonnées GPS ↔ Swiss LV95 initialisé');
});