/**
 * SwissMapManager - Gestionnaire de cartes suisses avec tuiles Swisstopo
 * Extrait des contrôleurs PHP pour réutilisabilité
 */
class SwissMapManager {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.options = {
            center: [46.8182, 8.2275], // Centre Suisse
            zoom: 8,
            maxZoom: 18,
            minZoom: 6,
            showControls: true,
            showLegend: true,
            ...options
        };
        
        this.map = null;
        this.currentLayer = 'pixelkarte';
        this.markers = [];
        
        // Couches officielles Swisstopo
        this.swissLayers = {
            pixelkarte: {
                name: "Carte couleur",
                url: "https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.pixelkarte-farbe/default/current/3857/{z}/{x}/{y}.jpeg",
                attribution: "© swisstopo"
            },
            orthophoto: {
                name: "Photos aériennes", 
                url: "https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.swissimage/default/current/3857/{z}/{x}/{y}.jpeg",
                attribution: "© swisstopo"
            },
            topo: {
                name: "Topographique",
                url: "https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.landeskarte-farbe-10/default/current/3857/{z}/{x}/{y}.jpeg", 
                attribution: "© swisstopo"
            },
            hiking: {
                name: "Randonnée",
                url: "https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.wanderkarten500/default/current/3857/{z}/{x}/{y}.jpeg",
                attribution: "© swisstopo"
            }
        };
        
        this.layerInstances = {};
    }
    
    /**
     * Initialise la carte avec les paramètres configurés
     */
    init() {
        console.log(`🗺️ Initialisation SwissMapManager pour #${this.containerId}`);
        
        // Vérifier que le conteneur existe
        const container = document.getElementById(this.containerId);
        if (!container) {
            throw new Error(`Conteneur #${this.containerId} non trouvé`);
        }
        
        // Vérifier que Leaflet est disponible
        if (typeof L === 'undefined') {
            throw new Error('Leaflet n\'est pas chargé');
        }
        
        // Créer la carte
        this.map = L.map(this.containerId, {
            center: this.options.center,
            zoom: this.options.zoom,
            maxZoom: this.options.maxZoom,
            minZoom: this.options.minZoom,
            zoomControl: this.options.showControls,
            attributionControl: false
        });
        
        // Créer les instances de couches
        this._createLayers();
        
        // Ajouter la couche par défaut
        this._setLayer(this.currentLayer);
        
        // Ajouter les contrôles si demandé
        if (this.options.showControls) {
            this._addControls();
        }
        
        console.log('✅ Carte suisse initialisée avec succès');
        return this;
    }
    
    /**
     * Crée les instances des couches Swisstopo
     */
    _createLayers() {
        Object.keys(this.swissLayers).forEach(key => {
            const layer = this.swissLayers[key];
            this.layerInstances[key] = L.tileLayer(layer.url, {
                attribution: layer.attribution,
                maxZoom: this.options.maxZoom
            });
        });
    }
    
    /**
     * Change la couche active
     */
    _setLayer(layerKey) {
        if (!this.layerInstances[layerKey]) {
            console.warn(`Couche ${layerKey} non disponible`);
            return;
        }
        
        // Supprimer la couche actuelle
        if (this.layerInstances[this.currentLayer]) {
            this.map.removeLayer(this.layerInstances[this.currentLayer]);
        }
        
        // Ajouter la nouvelle couche
        this.layerInstances[layerKey].addTo(this.map);
        this.currentLayer = layerKey;
        
        // Mettre à jour l'UI si présente
        this._updateLayerStatus();
        
        console.log(`🔄 Couche changée: ${this.swissLayers[layerKey].name}`);
    }
    
    /**
     * Ajoute les contrôles de couches
     */
    _addControls() {
        // Créer le contrôle de changement de couches
        const layerControl = L.control({ position: 'topright' });
        
        layerControl.onAdd = () => {
            const div = L.DomUtil.create('div', 'swiss-map-controls');
            div.innerHTML = `
                <div class="control-group">
                    <button class="control-btn" data-layer="pixelkarte" title="Carte couleur">
                        🗺️
                    </button>
                    <button class="control-btn" data-layer="orthophoto" title="Photos aériennes">
                        📸
                    </button>
                    <button class="control-btn" data-layer="topo" title="Topographique">
                        ⛰️
                    </button>
                    <button class="control-btn" data-layer="hiking" title="Randonnée">
                        🥾
                    </button>
                </div>
            `;
            
            // Ajouter les événements
            div.addEventListener('click', (e) => {
                if (e.target.dataset.layer) {
                    this.setLayer(e.target.dataset.layer);
                }
            });
            
            return div;
        };
        
        layerControl.addTo(this.map);
    }
    
    /**
     * API publique pour changer de couche
     */
    setLayer(layerKey) {
        this._setLayer(layerKey);
        return this;
    }
    
    /**
     * Ajoute un marqueur à la carte
     */
    addMarker(lat, lng, options = {}) {
        const marker = L.marker([lat, lng], options).addTo(this.map);
        this.markers.push(marker);
        
        if (options.popup) {
            marker.bindPopup(options.popup);
        }
        
        return marker;
    }
    
    /**
     * Ajoute un marqueur circulaire
     */
    addCircleMarker(lat, lng, options = {}) {
        const defaultOptions = {
            radius: 8,
            fillColor: '#e74c3c',
            color: '#ffffff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.8
        };
        
        const marker = L.circleMarker([lat, lng], { ...defaultOptions, ...options })
            .addTo(this.map);
        this.markers.push(marker);
        
        if (options.popup) {
            marker.bindPopup(options.popup);
        }
        
        return marker;
    }
    
    /**
     * Centre la carte sur des coordonnées
     */
    setView(lat, lng, zoom = null) {
        this.map.setView([lat, lng], zoom || this.options.zoom);
        return this;
    }
    
    /**
     * Ajuste la vue pour contenir tous les marqueurs
     */
    fitBounds(padding = 20) {
        if (this.markers.length === 0) return this;
        
        const group = new L.featureGroup(this.markers);
        this.map.fitBounds(group.getBounds().pad(padding / 100));
        return this;
    }
    
    /**
     * Efface tous les marqueurs
     */
    clearMarkers() {
        this.markers.forEach(marker => this.map.removeLayer(marker));
        this.markers = [];
        return this;
    }
    
    /**
     * Met à jour le status de la couche dans l'UI
     */
    _updateLayerStatus() {
        // Mettre à jour un élément status s'il existe
        const statusElement = document.getElementById('layer-name');
        if (statusElement) {
            statusElement.textContent = this.swissLayers[this.currentLayer].name;
        }
        
        // Mettre à jour les boutons actifs
        document.querySelectorAll('.control-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.layer === this.currentLayer);
        });
    }
    
    /**
     * Destroy l'instance de carte
     */
    destroy() {
        if (this.map) {
            this.map.remove();
            this.map = null;
            console.log('🗑️ Carte détruite');
        }
    }
    
    /**
     * Obtient l'instance Leaflet pour utilisation avancée
     */
    getLeafletMap() {
        return this.map;
    }
}

// Export pour utilisation comme module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SwissMapManager;
}