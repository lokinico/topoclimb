/**
 * InteractiveMapManager - Gestionnaire de cartes interactives avec clustering
 * Extrait des contrôleurs pour gestion avancée des marqueurs
 */
class InteractiveMapManager extends SwissMapManager {
    constructor(containerId, options = {}) {
        super(containerId, options);
        
        this.options = {
            ...this.options,
            enableClustering: true,
            clusterMaxRadius: 80,
            showHierarchy: true,
            ...options
        };
        
        this.climbingData = {
            regions: [],
            sites: [],
            sectors: []
        };
        
        this.clusterGroups = {
            regions: null,
            sites: null,
            sectors: null
        };
        
        this.hierarchyColors = {
            regions: '#e74c3c',  // Rouge
            sites: '#3498db',    // Bleu  
            sectors: '#2ecc71'   // Vert
        };
    }
    
    /**
     * Initialise la carte avec clustering
     */
    init() {
        super.init();
        
        if (this.options.enableClustering && typeof L.markerClusterGroup !== 'undefined') {
            this._initializeClusters();
        }
        
        if (this.options.showHierarchy) {
            this._addLegend();
        }
        
        return this;
    }
    
    /**
     * Initialise les groupes de clusters
     */
    _initializeClusters() {
        // Cluster pour les régions
        this.clusterGroups.regions = L.markerClusterGroup({
            iconCreateFunction: (cluster) => {
                const count = cluster.getChildCount();
                let size = 'small';
                if (count > 10) size = 'large';
                else if (count > 5) size = 'medium';
                
                return new L.DivIcon({
                    html: `<div><span>${count}</span></div>`,
                    className: `marker-cluster marker-cluster-${size} cluster-regions`,
                    iconSize: new L.Point(40, 40)
                });
            },
            maxClusterRadius: this.options.clusterMaxRadius,
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false
        });
        
        // Cluster pour les sites
        this.clusterGroups.sites = L.markerClusterGroup({
            iconCreateFunction: (cluster) => {
                const count = cluster.getChildCount();
                return new L.DivIcon({
                    html: `<div><span>${count}</span></div>`,
                    className: 'marker-cluster marker-cluster-medium cluster-sites',
                    iconSize: new L.Point(35, 35)
                });
            },
            maxClusterRadius: 60,
            spiderfyOnMaxZoom: true
        });
        
        // Cluster pour les secteurs
        this.clusterGroups.sectors = L.markerClusterGroup({
            iconCreateFunction: (cluster) => {
                const count = cluster.getChildCount();
                return new L.DivIcon({
                    html: `<div><span>${count}</span></div>`,
                    className: 'marker-cluster marker-cluster-small cluster-sectors',
                    iconSize: new L.Point(30, 30)
                });
            },
            maxClusterRadius: 40,
            spiderfyOnMaxZoom: true
        });
        
        // Ajouter tous les clusters à la carte
        Object.values(this.clusterGroups).forEach(cluster => {
            if (cluster) cluster.addTo(this.map);
        });
        
        console.log('✅ Clustering initialisé');
    }
    
    /**
     * Charge les données depuis les APIs
     */
    async loadClimbingData() {
        console.log('🔄 Chargement des données d\'escalade...');
        
        try {
            const [regionsRes, sitesRes, sectorsRes] = await Promise.all([
                fetch('/api/regions').catch(() => ({ ok: false })),
                fetch('/api/sites').catch(() => ({ ok: false })),
                fetch('/api/sectors').catch(() => ({ ok: false }))
            ]);
            
            // Charger les régions
            if (regionsRes.ok) {
                const regionsData = await regionsRes.json();
                this.climbingData.regions = regionsData.data || [];
                console.log(`🏔️ ${this.climbingData.regions.length} régions chargées`);
            }
            
            // Charger les sites
            if (sitesRes.ok) {
                const sitesData = await sitesRes.json();
                this.climbingData.sites = sitesData.data || [];
                console.log(`🧗 ${this.climbingData.sites.length} sites chargés`);
            }
            
            // Charger les secteurs
            if (sectorsRes.ok) {
                const sectorsData = await sectorsRes.json();
                this.climbingData.sectors = sectorsData.data || [];
                console.log(`🎯 ${this.climbingData.sectors.length} secteurs chargés`);
            }
            
            // Vérifier si on a des coordonnées valides
            const hasValidData = this._hasValidCoordinates();
            
            if (!hasValidData) {
                console.log('⚠️ Pas de coordonnées valides, utilisation des données de test');
                this._loadTestData();
            }
            
            // Ajouter les marqueurs
            this._addHierarchicalMarkers();
            this._updateStatus();
            
        } catch (error) {
            console.error('❌ Erreur chargement données:', error);
            this._loadTestData();
            this._addHierarchicalMarkers();
            this._updateStatus();
        }
    }
    
    /**
     * Vérifie si les données ont des coordonnées valides
     */
    _hasValidCoordinates() {
        // Vérifier les régions (format: latitude/longitude)
        const validRegions = this.climbingData.regions.some(r => {
            const lat = r.latitude || r.coordinates_lat;
            const lng = r.longitude || r.coordinates_lng;
            return lat && lng && parseFloat(lat) !== 0 && parseFloat(lng) !== 0;
        });
        
        // Vérifier les sites (format: coordinates_lat/coordinates_lng)
        const validSites = this.climbingData.sites.some(s => {
            return s.coordinates_lat && s.coordinates_lng && 
                   parseFloat(s.coordinates_lat) !== 0 && parseFloat(s.coordinates_lng) !== 0;
        });
        
        return validRegions || validSites;
    }
    
    /**
     * Charge des données de test
     */
    _loadTestData() {
        this.climbingData.regions = [
            {id: 1, name: "Valais", latitude: 46.1947, longitude: 7.144, description: "Région alpine majeure", site_count: 12, total_routes: 850},
            {id: 2, name: "Vaud", latitude: 46.5197, longitude: 6.6323, description: "Région lémanique", site_count: 8, total_routes: 420},
            {id: 3, name: "Tessin", latitude: 46.3353, longitude: 8.8019, description: "Granit et gneiss", site_count: 10, total_routes: 680}
        ];
        
        this.climbingData.sites = [
            {id: 1, name: "Saillon", latitude: 46.1817, longitude: 7.1947, region_id: 1, description: "Site sportif réputé", sector_count: 8, route_count: 120},
            {id: 2, name: "Freyr", latitude: 46.7089, longitude: 6.2333, region_id: 2, description: "Bord du lac", sector_count: 12, route_count: 200}
        ];
        
        this.climbingData.sectors = [
            {id: 1, name: "Secteur Principal", latitude: 46.1827, longitude: 7.1957, region_id: 1, site_id: 1, description: "Secteur principal", route_count: 65},
            {id: 2, name: "Secteur Débutants", latitude: 46.1807, longitude: 7.1937, region_id: 1, site_id: 1, description: "Voies faciles", route_count: 35}
        ];
        
        console.log('✅ Données de test chargées');
    }
    
    /**
     * Ajoute les marqueurs hiérarchiques
     */
    _addHierarchicalMarkers() {
        // Ajouter les marqueurs des régions
        this.climbingData.regions.forEach(region => {
            const lat = region.latitude || region.coordinates_lat;
            const lng = region.longitude || region.coordinates_lng;
            
            if (lat && lng) {
                const marker = L.circleMarker([parseFloat(lat), parseFloat(lng)], {
                    radius: 12,
                    fillColor: this.hierarchyColors.regions,
                    color: '#ffffff',
                    weight: 3,
                    opacity: 1,
                    fillOpacity: 0.9,
                    className: 'region-marker'
                });
                
                marker.bindPopup(this._createRegionPopup(region));
                
                if (this.clusterGroups.regions) {
                    this.clusterGroups.regions.addLayer(marker);
                } else {
                    marker.addTo(this.map);
                }
            }
        });
        
        // Ajouter les marqueurs des sites
        this.climbingData.sites.forEach(site => {
            const lat = site.latitude || site.coordinates_lat;
            const lng = site.longitude || site.coordinates_lng;
            
            if (lat && lng) {
                const marker = L.circleMarker([parseFloat(lat), parseFloat(lng)], {
                    radius: 8,
                    fillColor: this.hierarchyColors.sites,
                    color: '#ffffff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.8,
                    className: 'site-marker'
                });
                
                marker.bindPopup(this._createSitePopup(site));
                
                if (this.clusterGroups.sites) {
                    this.clusterGroups.sites.addLayer(marker);
                } else {
                    marker.addTo(this.map);
                }
            }
        });
        
        // Ajouter les marqueurs des secteurs
        this.climbingData.sectors.forEach(sector => {
            const lat = sector.latitude || sector.coordinates_lat;
            const lng = sector.longitude || sector.coordinates_lng;
            
            if (lat && lng) {
                const marker = L.circleMarker([parseFloat(lat), parseFloat(lng)], {
                    radius: 6,
                    fillColor: this.hierarchyColors.sectors,
                    color: '#ffffff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.8,
                    className: 'sector-marker'
                });
                
                marker.bindPopup(this._createSectorPopup(sector));
                
                if (this.clusterGroups.sectors) {
                    this.clusterGroups.sectors.addLayer(marker);
                } else {
                    marker.addTo(this.map);
                }
            }
        });
        
        console.log('✅ Marqueurs hiérarchiques ajoutés');
    }
    
    /**
     * Crée le popup pour une région
     */
    _createRegionPopup(region) {
        return `
            <div style="min-width: 220px;">
                <h5 style="margin: 0 0 10px 0; color: #e74c3c;">🏔️ ${region.name}</h5>
                <div style="background: #f8f9fa; padding: 8px; border-radius: 6px; margin-bottom: 10px;">
                    <div style="font-size: 13px; color: #666;"><strong>RÉGION</strong></div>
                    <div style="font-size: 14px;">${region.description || ''}</div>
                </div>
                <div style="display: flex; gap: 10px; margin-bottom: 8px;">
                    <div style="flex: 1; text-align: center; background: #e3f2fd; padding: 6px; border-radius: 4px;">
                        <div style="font-size: 16px; font-weight: bold; color: #1976d2;">${region.site_count || 0}</div>
                        <div style="font-size: 11px; color: #666;">Sites</div>
                    </div>
                    <div style="flex: 1; text-align: center; background: #e8f5e8; padding: 6px; border-radius: 4px;">
                        <div style="font-size: 16px; font-weight: bold; color: #388e3c;">${region.total_routes || 0}</div>
                        <div style="font-size: 11px; color: #666;">Voies</dev>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Crée le popup pour un site
     */
    _createSitePopup(site) {
        const region = this.climbingData.regions.find(r => r.id === site.region_id);
        return `
            <div style="min-width: 200px;">
                <h6 style="margin: 0 0 8px 0; color: #3498db;">🧗 ${site.name}</h6>
                <div style="background: #f0f8ff; padding: 6px; border-radius: 4px; margin-bottom: 8px;">
                    <div style="font-size: 12px; color: #666;"><strong>SITE</strong> ${region ? 'en ' + region.name : ''}</div>
                    <div style="font-size: 13px;">${site.description || ''}</div>
                </div>
                <div style="display: flex; gap: 8px; margin-bottom: 6px;">
                    <div style="flex: 1; text-align: center; background: #e8f5e8; padding: 4px; border-radius: 3px;">
                        <div style="font-size: 14px; font-weight: bold; color: #388e3c;">${site.sector_count || 0}</div>
                        <div style="font-size: 10px; color: #666;">Secteurs</div>
                    </div>
                    <div style="flex: 1; text-align: center; background: #fff3e0; padding: 4px; border-radius: 3px;">
                        <div style="font-size: 14px; font-weight: bold; color: #f57c00;">${site.route_count || 0}</div>
                        <div style="font-size: 10px; color: #666;">Voies</div>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Crée le popup pour un secteur
     */
    _createSectorPopup(sector) {
        const region = this.climbingData.regions.find(r => r.id === sector.region_id);
        const site = this.climbingData.sites.find(s => s.id === sector.site_id);
        let locationText = '';
        
        if (site) {
            locationText = 'dans ' + site.name;
        } else if (region) {
            locationText = 'directement en ' + region.name;
        }
        
        return `
            <div style="min-width: 180px;">
                <h6 style="margin: 0 0 6px 0; color: #2ecc71;">🎯 ${sector.name}</h6>
                <div style="background: #f0fff0; padding: 6px; border-radius: 4px; margin-bottom: 6px;">
                    <div style="font-size: 11px; color: #666;"><strong>SECTEUR</strong> ${locationText}</div>
                    <div style="font-size: 12px;">${sector.description || ''}</div>
                </div>
                <div style="text-align: center; background: #fff3e0; padding: 6px; border-radius: 4px;">
                    <div style="font-size: 16px; font-weight: bold; color: #f57c00;">${sector.route_count || 0}</div>
                    <div style="font-size: 11px; color: #666;">Voies d'escalade</div>
                </div>
            </div>
        `;
    }
    
    /**
     * Ajoute la légende hiérarchique
     */
    _addLegend() {
        const legend = L.control({ position: 'topleft' });
        
        legend.onAdd = () => {
            const div = L.DomUtil.create('div', 'climbing-legend');
            div.innerHTML = `
                <div class="legend-title">🗺️ Hiérarchie</div>
                <div class="legend-items">
                    <div class="legend-item">
                        <span class="legend-color" style="background: ${this.hierarchyColors.regions};"></span>
                        <span>Régions</span>
                        <input type="checkbox" id="toggle-regions" checked>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color" style="background: ${this.hierarchyColors.sites};"></span>
                        <span>Sites</span>
                        <input type="checkbox" id="toggle-sites" checked>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color" style="background: ${this.hierarchyColors.sectors};"></span>
                        <span>Secteurs</span>
                        <input type="checkbox" id="toggle-sectors" checked>
                    </div>
                </div>
                <div class="legend-note">💡 Clusters automatiques</div>
            `;
            
            // Ajouter les événements de toggle
            div.addEventListener('change', (e) => {
                this._toggleHierarchyLevel(e.target.id.replace('toggle-', ''));
            });
            
            return div;
        };
        
        legend.addTo(this.map);
    }
    
    /**
     * Toggle l'affichage d'un niveau hiérarchique
     */
    _toggleHierarchyLevel(level) {
        const clusterGroup = this.clusterGroups[level];
        if (!clusterGroup) return;
        
        if (this.map.hasLayer(clusterGroup)) {
            this.map.removeLayer(clusterGroup);
        } else {
            this.map.addLayer(clusterGroup);
        }
    }
    
    /**
     * Met à jour le status
     */
    _updateStatus() {
        const statusElement = document.getElementById('status');
        const countElement = document.getElementById('site-count');
        
        if (statusElement) {
            statusElement.textContent = `${this.climbingData.regions.length}R + ${this.climbingData.sites.length}S + ${this.climbingData.sectors.length}C`;
        }
        
        if (countElement) {
            const total = this.climbingData.regions.length + this.climbingData.sites.length + this.climbingData.sectors.length;
            countElement.textContent = total;
        }
        
        console.log(`📊 Status: ${this.climbingData.regions.length} régions, ${this.climbingData.sites.length} sites, ${this.climbingData.sectors.length} secteurs`);
    }
}

// Export pour utilisation comme module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = InteractiveMapManager;
}