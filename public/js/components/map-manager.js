/**
 * Map Manager Component
 * Gère les cartes Leaflet pour la sélection de coordonnées
 */
class MapManager {
    constructor(mapId, options = {}) {
        this.mapId = mapId;
        this.options = {
            defaultCenter: [46.8, 8.2], // Centre sur la Suisse
            defaultZoom: 8,
            locateZoom: 15,
            tileLayer: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            ...options
        };

        this.map = null;
        this.marker = null;
        this.init();
    }

    init() {
        if (!this.isLeafletLoaded()) {
            console.error('Leaflet library not loaded');
            return;
        }

        this.initMap();
        this.bindEvents();
    }

    isLeafletLoaded() {
        return typeof L !== 'undefined';
    }

    initMap() {
        const mapElement = document.getElementById(this.mapId);
        if (!mapElement) {
            console.error(`Map element with ID "${this.mapId}" not found`);
            return;
        }

        // Initialiser la carte
        this.map = L.map(this.mapId).setView(this.options.defaultCenter, this.options.defaultZoom);

        // Ajouter les tuiles
        L.tileLayer(this.options.tileLayer, {
            attribution: this.options.attribution
        }).addTo(this.map);

        // Initialiser le marqueur si des coordonnées sont disponibles
        this.initializeMarkerFromInputs();

        // Écouter les clics sur la carte
        this.map.on('click', (e) => {
            this.updateMarkerAndInputs(e.latlng.lat, e.latlng.lng);
        });
    }

    bindEvents() {
        // Bouton de localisation
        const locateButton = document.getElementById('locate-button');
        if (locateButton) {
            locateButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.locateUser();
            });
        }

        // Écouter les changements dans les inputs de coordonnées
        const latInput = document.getElementById('coordinates_lat');
        const lngInput = document.getElementById('coordinates_lng');

        if (latInput && lngInput) {
            [latInput, lngInput].forEach(input => {
                input.addEventListener('change', () => {
                    this.updateMarkerFromInputs();
                });
            });
        }
    }

    initializeMarkerFromInputs() {
        const lat = this.getLatFromInput();
        const lng = this.getLngFromInput();

        if (lat && lng) {
            this.updateMarkerAndInputs(lat, lng, false);
            this.map.setView([lat, lng], this.options.locateZoom);
        }
    }

    updateMarkerFromInputs() {
        const lat = this.getLatFromInput();
        const lng = this.getLngFromInput();

        if (lat && lng) {
            this.updateMarkerAndInputs(lat, lng, false);
        }
    }

    updateMarkerAndInputs(lat, lng, updateInputs = true) {
        // Supprimer l'ancien marqueur
        if (this.marker) {
            this.map.removeLayer(this.marker);
        }

        // Ajouter le nouveau marqueur
        this.marker = L.marker([lat, lng]).addTo(this.map);

        // Mettre à jour les inputs si demandé
        if (updateInputs) {
            this.updateCoordinateInputs(lat, lng);
        }
    }

    updateCoordinateInputs(lat, lng) {
        const latInput = document.getElementById('coordinates_lat');
        const lngInput = document.getElementById('coordinates_lng');

        if (latInput) latInput.value = parseFloat(lat).toFixed(8);
        if (lngInput) lngInput.value = parseFloat(lng).toFixed(8);

        // Convertir en coordonnées suisses si la fonction globale est disponible
        if (typeof window.updateCoordinatesFromMap === 'function') {
            window.updateCoordinatesFromMap(lat, lng);
        }
    }

    getLatFromInput() {
        const latInput = document.getElementById('coordinates_lat');
        return latInput ? parseFloat(latInput.value) : null;
    }

    getLngFromInput() {
        const lngInput = document.getElementById('coordinates_lng');
        return lngInput ? parseFloat(lngInput.value) : null;
    }

    locateUser() {
        if (!navigator.geolocation) {
            alert("La géolocalisation n'est pas prise en charge par votre navigateur");
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                this.updateMarkerAndInputs(lat, lng);
                this.map.setView([lat, lng], this.options.locateZoom);
            },
            (error) => {
                console.error("Erreur de géolocalisation:", error.message);
                alert("Impossible d'obtenir votre position. Erreur: " + error.message);
            },
            {
                enableHighAccuracy: true,
                timeout: 5000,
                maximumAge: 0
            }
        );
    }

    // Méthodes utilitaires publiques
    setView(lat, lng, zoom = null) {
        this.map.setView([lat, lng], zoom || this.options.locateZoom);
    }

    addMarker(lat, lng, popup = null) {
        const marker = L.marker([lat, lng]).addTo(this.map);
        if (popup) {
            marker.bindPopup(popup);
        }
        return marker;
    }

    clearMarkers() {
        if (this.marker) {
            this.map.removeLayer(this.marker);
            this.marker = null;
        }
    }
}

// Export pour utilisation dans d'autres scripts
window.MapManager = MapManager;