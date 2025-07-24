/**
 * SiteFormManager - Gestionnaire de formulaire de site
 * Extrait de resources/views/sites/form.twig
 */
class SiteFormManager {
    constructor(options = {}) {
        this.options = {
            mapId: 'map',
            latInputId: 'coordinates_lat',
            lngInputId: 'coordinates_lng',
            ...options
        };
        
        this.map = null;
        this.siteMarker = null;
        this.coordinatesHelper = null;
        this.apiClient = new APIClient();
        
        this.init();
    }
    
    init() {
        this.initializeMap();
        this.setupCoordinatesInputs();
        this.setupFormValidation();
        this.setupMediaHandling();
        this.setupCodeGeneration();
    }
    
    /**
     * Initialise la carte Leaflet
     */
    initializeMap() {
        const mapContainer = document.getElementById(this.options.mapId);
        if (!mapContainer) {
            console.warn('Map container not found');
            return;
        }
        
        // Initialiser la carte au centre de la Suisse
        const swissCenter = CoordinatesHelper.getSwissCenter();
        this.map = L.map(this.options.mapId).setView([swissCenter.latitude, swissCenter.longitude], 7);

        // Ajouter la couche de base OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(this.map);
        
        // Si coordonnées existantes, placer le marker
        const latInput = document.getElementById(this.options.latInputId);
        const lngInput = document.getElementById(this.options.lngInputId);
        
        if (latInput && lngInput && latInput.value && lngInput.value) {
            const coords = CoordinatesHelper.normalizeCoordinates({
                latitude: latInput.value,
                longitude: lngInput.value
            });
            
            if (coords.valid) {
                this.siteMarker = L.marker([coords.latitude, coords.longitude]).addTo(this.map);
                this.map.setView([coords.latitude, coords.longitude], 13);
            }
        }

        // Clic sur la carte pour placer/déplacer le marker
        this.map.on('click', (e) => {
            this.onMapClick(e);
        });
        
        console.log('✅ Carte initialisée pour formulaire site');
    }
    
    /**
     * Gère le clic sur la carte
     */
    onMapClick(e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;
        
        // Mettre à jour les champs de coordonnées
        this.updateCoordinatesInputs(lat, lng);
        
        // Placer/déplacer le marker
        if (this.siteMarker) {
            this.siteMarker.setLatLng([lat, lng]);
        } else {
            this.siteMarker = L.marker([lat, lng]).addTo(this.map);
        }
    }
    
    /**
     * Configure les inputs de coordonnées
     */
    setupCoordinatesInputs() {
        this.coordinatesHelper = CoordinatesHelper.setupCoordinatesInput(
            this.options.latInputId,
            this.options.lngInputId,
            (lat, lng) => {
                this.updateMapFromCoordinates(lat, lng);
            }
        );
    }
    
    /**
     * Met à jour les inputs de coordonnées
     */
    updateCoordinatesInputs(lat, lng) {
        if (this.coordinatesHelper) {
            this.coordinatesHelper.setCoordinates(lat, lng);
        }
    }
    
    /**
     * Met à jour la carte depuis les coordonnées saisies
     */
    updateMapFromCoordinates(lat, lng) {
        if (!this.map) return;
        
        if (this.siteMarker) {
            this.siteMarker.setLatLng([lat, lng]);
        } else {
            this.siteMarker = L.marker([lat, lng]).addTo(this.map);
        }
        this.map.setView([lat, lng], 13);
    }
    
    /**
     * Configure la validation du formulaire
     */
    setupFormValidation() {
        const form = document.querySelector('.site-form');
        if (!form) return;
        
        form.addEventListener('submit', (e) => {
            if (!this.validateForm()) {
                e.preventDefault();
                this.showValidationErrors();
            }
        });
        
        // Validation en temps réel des champs requis
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            field.addEventListener('blur', () => {
                this.validateField(field);
            });
        });
    }
    
    /**
     * Valide le formulaire
     */
    validateForm() {
        const form = document.querySelector('.site-form');
        if (!form) return false;
        
        let isValid = true;
        const errors = [];
        
        // Vérifier les champs requis
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                errors.push(`Le champ "${field.labels[0]?.textContent || field.name}" est requis`);
                field.classList.add('is-invalid');
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        // Vérifier les coordonnées si fournies
        const coordinates = this.coordinatesHelper?.getCoordinates();
        if (coordinates && !coordinates.valid) {
            isValid = false;
            errors.push('Les coordonnées fournies ne sont pas valides');
        }
        
        this.validationErrors = errors;
        return isValid;
    }
    
    /**
     * Valide un champ individuel
     */
    validateField(field) {
        const isValid = field.checkValidity();
        field.classList.toggle('is-invalid', !isValid);
        field.classList.toggle('is-valid', isValid);
        
        return isValid;
    }
    
    /**
     * Affiche les erreurs de validation
     */
    showValidationErrors() {
        if (!this.validationErrors || this.validationErrors.length === 0) return;
        
        // Créer un message d'erreur
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger';
        errorDiv.innerHTML = `
            <h6><i class="fa fa-exclamation-triangle"></i> Erreurs de validation:</h6>
            <ul class="mb-0">
                ${this.validationErrors.map(error => `<li>${error}</li>`).join('')}
            </ul>
        `;
        
        // Insérer avant le formulaire
        const form = document.querySelector('.site-form');
        if (form) {
            form.parentNode.insertBefore(errorDiv, form);
            
            // Faire défiler vers l'erreur
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Supprimer après 10 secondes
            setTimeout(() => {
                errorDiv.remove();
            }, 10000);
        }
    }
    
    /**
     * Configure la gestion des médias
     */
    setupMediaHandling() {
        // Gestion du nom de fichier
        const mediaFileInput = document.getElementById('media_file');
        if (mediaFileInput) {
            mediaFileInput.addEventListener('change', (e) => {
                this.updateFileLabel(e.target);
            });
        }
        
        // Suppression de médias
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('media-delete-btn') || e.target.closest('.media-delete-btn')) {
                this.handleMediaDelete(e);
            }
        });
    }
    
    /**
     * Met à jour le label du fichier sélectionné
     */
    updateFileLabel(input) {
        const label = input.nextElementSibling;
        if (label) {
            const fileName = input.files[0]?.name || 'Choisir un fichier';
            label.textContent = fileName;
        }
    }
    
    /**
     * Gère la suppression de média
     */
    async handleMediaDelete(e) {
        e.preventDefault();
        
        const btn = e.target.classList.contains('media-delete-btn') ? 
                    e.target : e.target.closest('.media-delete-btn');
        
        const mediaId = btn.dataset.mediaId;
        const csrfToken = btn.dataset.csrfToken;
        
        if (!mediaId || !csrfToken) {
            console.error('Missing media ID or CSRF token');
            return;
        }
        
        if (!confirm('Êtes-vous sûr de vouloir supprimer cette image ?')) {
            return;
        }
        
        try {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Suppression...';
            
            const result = await this.apiClient.deleteMedia(mediaId, csrfToken);
            
            if (result.success) {
                // Supprimer l'élément de l'interface
                const mediaItem = btn.closest('.col-md-4');
                if (mediaItem) {
                    mediaItem.remove();
                }
                
                // Afficher un message de succès
                this.showMessage('Image supprimée avec succès', 'success');
            } else {
                throw new Error(result.message || 'Erreur lors de la suppression');
            }
        } catch (error) {
            console.error('Media deletion failed:', error);
            this.showMessage('Erreur lors de la suppression: ' + error.message, 'danger');
            
            // Restaurer le bouton
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-trash"></i> Supprimer';
        }
    }
    
    /**
     * Configure la génération automatique du code
     */
    setupCodeGeneration() {
        const nameInput = document.getElementById('name');
        const codeInput = document.getElementById('code');
        
        if (!nameInput || !codeInput) return;
        
        nameInput.addEventListener('input', () => {
            if (!codeInput.value || codeInput.dataset.autoGenerated === 'true') {
                const generatedCode = this.generateCodeFromName(nameInput.value);
                codeInput.value = generatedCode;
                codeInput.dataset.autoGenerated = 'true';
            }
        });
        
        codeInput.addEventListener('input', () => {
            codeInput.dataset.autoGenerated = 'false';
        });
    }
    
    /**
     * Génère un code à partir du nom
     */
    generateCodeFromName(name) {
        return name
            .toUpperCase()
            .replace(/[^A-Z0-9]/g, '_')
            .replace(/_{2,}/g, '_')
            .replace(/^_|_$/g, '')
            .substring(0, 20);
    }
    
    /**
     * Affiche un message temporaire
     */
    showMessage(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insérer au début de la page
        const pageHeader = document.querySelector('.page-header');
        if (pageHeader) {
            pageHeader.parentNode.insertBefore(alertDiv, pageHeader.nextSibling);
        }
        
        // Auto-suppression après 5 secondes
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
    
    /**
     * Détruit l'instance (nettoyage)
     */
    destroy() {
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
    }
}

// Export pour utilisation comme module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SiteFormManager;
}