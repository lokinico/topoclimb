/**
 * Region Form Page - Version moderne modulaire
 * Remplace l'ancien form.js avec architecture moderne
 */

// Enregistrement du module de page région form
TopoclimbCH.modules.register('page-region-form', ['utils', 'api', 'ui'], async (utils, api, ui) => {
    
    class RegionFormPage {
        constructor() {
            this.region = window.formData?.region || null;
            this.countries = window.formData?.countries || [];
            this.isEditing = !!this.region;
            this.isDirty = false;
            this.validationErrors = {};
            this.components = {};
            this.initialized = false;
            
            // Configuration
            this.config = {
                autosave: {
                    enabled: true,
                    interval: 30000, // 30 secondes
                    maxRetries: 3
                },
                map: {
                    defaultCenter: [46.8182, 8.2275], // Centre de la Suisse
                    defaultZoom: 8,
                    maxZoom: 18
                },
                upload: {
                    maxFileSize: 10 * 1024 * 1024, // 10MB
                    allowedTypes: ['image/jpeg', 'image/png', 'image/webp'],
                    maxFiles: 10
                }
            };
            
            // État des fichiers
            this.uploadedFiles = {
                cover: null,
                gallery: []
            };
        }
        
        /**
         * Initialise la page formulaire région
         */
        async init() {
            if (this.initialized) {
                console.warn('Region form page already initialized');
                return;
            }
            
            console.log(`🏔️ Initializing region form page: ${this.isEditing ? 'edit' : 'create'}`);
            
            try {
                // Chargement des composants requis
                await this.loadRequiredComponents();
                
                // Configuration du formulaire
                await this.setupFormManagement();
                
                // Configuration de la carte interactive
                await this.setupInteractiveMap();
                
                // Configuration des uploads de fichiers
                this.setupFileUploads();
                
                // Configuration de l'expérience utilisateur
                this.setupUserExperience();
                
                // Auto-sauvegarde intelligente
                if (this.isEditing) {
                    this.setupIntelligentAutosave();
                }
                
                // Chargement des données
                this.loadFormData();
                
                this.initialized = true;
                console.log('✅ Region form page initialized successfully');
                
            } catch (error) {
                console.error('❌ Failed to initialize region form page:', error);
                this.initializeFallback();
            }
        }
        
        /**
         * Charge les composants requis dynamiquement
         */
        async loadRequiredComponents() {
            const componentsToLoad = [];
            
            // Carte interactive
            if (document.getElementById('region-map')) {
                componentsToLoad.push(this.loadScript('/js/components/swiss-map-manager.js'));
            }
            
            // Géolocalisation
            if (document.querySelector('[data-geolocation]')) {
                componentsToLoad.push(this.loadScript('/js/components/geolocation-manager.js'));
            }
            
            await Promise.all(componentsToLoad);
            console.log('📦 Required components loaded');
        }
        
        /**
         * Configuration moderne de la gestion du formulaire
         */
        async setupFormManagement() {
            const form = document.getElementById('region-form');
            if (!form) return;
            
            // Validation moderne en temps réel
            await this.setupModernValidation(form);
            
            // Soumission moderne avec feedback
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.handleModernSubmit(form);
            });
            
            // Détection des changements
            form.addEventListener('input', utils.debounce(() => {
                this.markDirty();
                this.validateFormSection(form);
            }, 300));
            
            form.addEventListener('change', () => {
                this.markDirty();
            });
            
            console.log('📝 Modern form management configured');
        }
        
        /**
         * Configuration de la validation moderne
         */
        async setupModernValidation(form) {
            const validationRules = {
                name: {
                    required: true,
                    minLength: 2,
                    maxLength: 255,
                    pattern: /^[a-zA-ZÀ-ÿ0-9\s\-'\.]+$/
                },
                country_id: {
                    required: true,
                    validator: (value) => this.countries.some(c => c.id == value)
                },
                description: {
                    maxLength: 65535,
                    minLength: 10
                },
                coordinates_lat: {
                    pattern: /^-?([0-8]?[0-9](\.\d+)?|90(\.0+)?)$/,
                    validator: (value) => !value || (value >= -90 && value <= 90)
                },
                coordinates_lng: {
                    pattern: /^-?((1[0-7]|[0-9])?[0-9](\.\d+)?|180(\.0+)?)$/,
                    validator: (value) => !value || (value >= -180 && value <= 180)
                }
            };
            
            // Configuration des validateurs pour chaque champ
            for (const [fieldName, rules] of Object.entries(validationRules)) {
                const field = form.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    this.setupFieldValidator(field, rules);
                }
            }
        }
        
        /**
         * Configuration d'un validateur de champ
         */
        setupFieldValidator(field, rules) {
            const debouncedValidate = utils.debounce(async () => {
                await this.validateField(field, rules);
            }, 300);
            
            field.addEventListener('input', debouncedValidate);
            field.addEventListener('blur', () => this.validateField(field, rules));
            
            // Feedback visuel immédiat
            field.addEventListener('input', () => {
                this.provideInstantFeedback(field, rules);
            });
        }
        
        /**
         * Validation d'un champ avec feedback moderne
         */
        async validateField(field, rules) {
            const value = field.value.trim();
            const errors = [];
            
            // Validation synchrone
            if (rules.required && !value) {
                errors.push('Ce champ est requis');
            }
            
            if (rules.minLength && value.length > 0 && value.length < rules.minLength) {
                errors.push(`Minimum ${rules.minLength} caractères`);
            }
            
            if (rules.maxLength && value.length > rules.maxLength) {
                errors.push(`Maximum ${rules.maxLength} caractères`);
            }
            
            if (rules.pattern && value && !rules.pattern.test(value)) {
                errors.push('Format invalide');
            }
            
            // Validation personnalisée
            if (rules.validator && value) {
                try {
                    const customError = await rules.validator(value);
                    if (customError && customError !== true) {
                        errors.push(typeof customError === 'string' ? customError : 'Valeur invalide');
                    }
                } catch (error) {
                    console.warn('Custom validation error:', error);
                }
            }
            
            this.displayFieldValidation(field, errors);
            this.validationErrors[field.name] = errors.length > 0 ? errors : null;
            
            return errors.length === 0;
        }
        
        /**
         * Affichage moderne de la validation
         */
        displayFieldValidation(field, errors) {
            const fieldGroup = field.closest('.form-group') || field.parentNode;
            
            // Nettoyer les erreurs existantes
            fieldGroup.querySelectorAll('.field-error').forEach(el => el.remove());
            
            // États visuels
            field.classList.remove('is-valid', 'is-invalid');
            
            if (errors.length > 0) {
                field.classList.add('is-invalid');
                
                // Afficher les erreurs
                const errorContainer = document.createElement('div');
                errorContainer.className = 'field-errors mt-1';
                
                errors.forEach(error => {
                    const errorEl = document.createElement('div');
                    errorEl.className = 'field-error text-danger';
                    errorEl.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${error}`;
                    errorContainer.appendChild(errorEl);
                });
                
                fieldGroup.appendChild(errorContainer);
                
            } else if (field.value.trim()) {
                field.classList.add('is-valid');
            }
        }
        
        /**
         * Feedback visuel instantané
         */
        provideInstantFeedback(field, rules) {
            const value = field.value.trim();
            
            // Compteur de caractères pour les champs longs
            if (rules.maxLength) {
                this.updateCharacterCounter(field, value.length, rules.maxLength);
            }
            
            // Suggestions contextuelles
            switch (field.name) {
                case 'name':
                    this.suggestSlugFromName(value);
                    break;
                case 'coordinates_lat':
                case 'coordinates_lng':
                    this.updateMapFromCoordinates();
                    break;
            }
        }
        
        /**
         * Mise à jour du compteur de caractères
         */
        updateCharacterCounter(field, current, max) {
            let counter = field.parentNode.querySelector('.char-counter');
            
            if (!counter) {
                counter = document.createElement('div');
                counter.className = 'char-counter mt-1';
                field.parentNode.appendChild(counter);
            }
            
            const percentage = (current / max) * 100;
            let className = 'text-muted';
            
            if (percentage >= 95) className = 'text-danger';
            else if (percentage >= 85) className = 'text-warning';
            else if (percentage >= 70) className = 'text-info';
            
            counter.className = `char-counter mt-1 ${className}`;
            counter.innerHTML = `
                <i class="fas fa-info-circle"></i> 
                ${current}/${max} caractères
                <div class="progress mt-1" style="height: 3px;">
                    <div class="progress-bar bg-${className.replace('text-', '')}" 
                         style="width: ${Math.min(percentage, 100)}%"></div>
                </div>
            `;
        }
        
        /**
         * Configuration de la carte interactive
         */
        async setupInteractiveMap() {
            const mapContainer = document.getElementById('region-map');
            if (!mapContainer) return;
            
            try {
                // Charger le gestionnaire de carte suisse
                if (!window.SwissMapManager) {
                    await this.loadScript('/js/components/swiss-map-manager.js');
                }
                
                // Initialiser la carte
                this.components.map = new SwissMapManager('region-map', {
                    center: this.region?.coordinates_lat && this.region?.coordinates_lng 
                        ? [this.region.coordinates_lat, this.region.coordinates_lng]
                        : this.config.map.defaultCenter,
                    zoom: this.config.map.defaultZoom,
                    maxZoom: this.config.map.maxZoom,
                    showControls: true,
                    enableGeolocation: true
                });
                
                this.components.map.init();
                
                // Configuration des interactions carte
                this.setupMapInteractions();
                
                console.log('🗺️ Interactive map configured');
                
            } catch (error) {
                console.error('Map initialization failed:', error);
                this.showMapFallback();
            }
        }
        
        /**
         * Configuration des interactions avec la carte
         */
        setupMapInteractions() {
            const map = this.components.map;
            if (!map) return;
            
            // Clic sur la carte pour placer un marqueur
            map.onMapClick((coords) => {
                this.updateCoordinatesFromMap(coords.lat, coords.lng);
            });
            
            // Boutons de géolocalisation
            const geoBtn = document.getElementById('get-location-btn');
            if (geoBtn) {
                geoBtn.addEventListener('click', async () => {
                    await this.getCurrentLocation();
                });
            }
            
            // Bouton de recherche
            const searchBtn = document.getElementById('search-location-btn');
            if (searchBtn) {
                searchBtn.addEventListener('click', () => {
                    this.showLocationSearchModal();
                });
            }
            
            // Bouton de nettoyage
            const clearBtn = document.getElementById('clear-coordinates-btn');
            if (clearBtn) {
                clearBtn.addEventListener('click', () => {
                    this.clearCoordinates();
                });
            }
        }
        
        /**
         * Mise à jour des coordonnées depuis la carte
         */
        updateCoordinatesFromMap(lat, lng) {
            const latInput = document.getElementById('coordinates_lat');
            const lngInput = document.getElementById('coordinates_lng');
            
            if (latInput && lngInput) {
                latInput.value = lat.toFixed(6);
                lngInput.value = lng.toFixed(6);
                
                // Déclencher la validation
                latInput.dispatchEvent(new Event('input'));
                lngInput.dispatchEvent(new Event('input'));
                
                // Ajouter/mettre à jour le marqueur
                this.components.map.clearMarkers();
                this.components.map.addMarker(lat, lng, {
                    popup: 'Position de la région',
                    draggable: true,
                    onDragEnd: (newCoords) => {
                        this.updateCoordinatesFromMap(newCoords.lat, newCoords.lng);
                    }
                });
                
                this.markDirty();
                ui.toast.success('Coordonnées mises à jour !');
            }
        }
        
        /**
         * Mise à jour de la carte depuis les coordonnées
         */
        updateMapFromCoordinates() {
            const latInput = document.getElementById('coordinates_lat');
            const lngInput = document.getElementById('coordinates_lng');
            
            if (!latInput || !lngInput || !this.components.map) return;
            
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);
            
            if (!isNaN(lat) && !isNaN(lng)) {
                this.components.map.clearMarkers();
                this.components.map.setView([lat, lng], 12);
                this.components.map.addMarker(lat, lng, {
                    popup: 'Position de la région',
                    draggable: true,
                    onDragEnd: (newCoords) => {
                        this.updateCoordinatesFromMap(newCoords.lat, newCoords.lng);
                    }
                });
            }
        }
        
        /**
         * Obtention de la position actuelle
         */
        async getCurrentLocation() {
            try {
                ui.toast.info('Localisation en cours...', { duration: 5000 });
                
                const position = await new Promise((resolve, reject) => {
                    if (!navigator.geolocation) {
                        reject(new Error('Géolocalisation non supportée'));
                    }
                    
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 60000
                    });
                });
                
                const { latitude, longitude } = position.coords;
                this.updateCoordinatesFromMap(latitude, longitude);
                
            } catch (error) {
                console.error('Geolocation error:', error);
                ui.toast.error('Impossible d\\'obtenir votre position : ' + error.message);
            }
        }
        
        /**
         * Nettoyage des coordonnées
         */
        clearCoordinates() {
            const latInput = document.getElementById('coordinates_lat');
            const lngInput = document.getElementById('coordinates_lng');
            
            if (latInput && lngInput) {
                latInput.value = '';
                lngInput.value = '';
                
                latInput.dispatchEvent(new Event('input'));
                lngInput.dispatchEvent(new Event('input'));
                
                if (this.components.map) {
                    this.components.map.clearMarkers();
                    this.components.map.setView(this.config.map.defaultCenter, this.config.map.defaultZoom);
                }
                
                this.markDirty();
                ui.toast.info('Coordonnées effacées');
            }
        }
        
        /**
         * Configuration des uploads de fichiers
         */
        setupFileUploads() {
            this.setupCoverImageUpload();
            this.setupGalleryUpload();
            console.log('📁 File uploads configured');
        }
        
        /**
         * Configuration de l'upload d'image de couverture
         */
        setupCoverImageUpload() {
            const coverInput = document.getElementById('cover_image');
            const coverPreview = document.getElementById('cover-preview');
            
            if (!coverInput) return;
            
            coverInput.addEventListener('change', async (e) => {
                const file = e.target.files[0];
                if (!file) return;
                
                try {
                    this.validateFile(file);
                    await this.previewCoverImage(file, coverPreview);
                    this.markDirty();
                } catch (error) {
                    ui.toast.error('Erreur fichier : ' + error.message);
                    coverInput.value = '';
                }
            });
        }
        
        /**
         * Configuration de l'upload de galerie
         */
        setupGalleryUpload() {
            const galleryInput = document.getElementById('gallery_images');
            const galleryPreview = document.getElementById('gallery-preview');
            
            if (!galleryInput) return;
            
            galleryInput.addEventListener('change', async (e) => {
                const files = Array.from(e.target.files);
                
                try {
                    // Validation des fichiers
                    files.forEach(file => this.validateFile(file));
                    
                    if (files.length > this.config.upload.maxFiles) {
                        throw new Error(`Maximum ${this.config.upload.maxFiles} fichiers autorisés`);
                    }
                    
                    await this.previewGalleryImages(files, galleryPreview);
                    this.markDirty();
                    
                } catch (error) {
                    ui.toast.error('Erreur fichiers : ' + error.message);
                    galleryInput.value = '';
                }
            });
        }
        
        /**
         * Validation d'un fichier
         */
        validateFile(file) {
            if (file.size > this.config.upload.maxFileSize) {
                throw new Error(`Fichier trop volumineux (max ${utils.formatBytes(this.config.upload.maxFileSize)})`);
            }
            
            if (!this.config.upload.allowedTypes.includes(file.type)) {
                throw new Error('Type de fichier non autorisé (JPG, PNG, WebP seulement)');
            }
        }
        
        /**
         * Prévisualisation de l'image de couverture
         */
        async previewCoverImage(file, container) {
            if (!container) return;
            
            const imageUrl = URL.createObjectURL(file);
            
            container.innerHTML = `
                <div class="cover-preview">
                    <img src="${imageUrl}" alt="Aperçu couverture" class="img-fluid rounded">
                    <div class="preview-overlay">
                        <div class="file-info">
                            <strong>${file.name}</strong><br>
                            <small>${utils.formatBytes(file.size)}</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger remove-cover">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
            
            // Bouton de suppression
            container.querySelector('.remove-cover').addEventListener('click', () => {
                container.innerHTML = '';
                document.getElementById('cover_image').value = '';
                URL.revokeObjectURL(imageUrl);
                this.markDirty();
            });
        }
        
        /**
         * Auto-sauvegarde intelligente
         */
        setupIntelligentAutosave() {
            if (!this.config.autosave.enabled) return;
            
            let autosaveTimeout;
            let retryCount = 0;
            
            const performAutosave = async () => {
                try {
                    if (this.isDirty) {
                        await this.saveFormDraft();
                        retryCount = 0;
                    }
                } catch (error) {
                    console.warn('Autosave failed:', error);
                    retryCount++;
                    
                    if (retryCount >= this.config.autosave.maxRetries) {
                        ui.toast.warning('Auto-sauvegarde désactivée (trop d\\'échecs)');
                        this.config.autosave.enabled = false;
                    }
                }
            };
            
            // Auto-sauvegarde périodique
            setInterval(performAutosave, this.config.autosave.interval);
            
            // Auto-sauvegarde avant fermeture
            window.addEventListener('beforeunload', (e) => {
                if (this.isDirty) {
                    e.preventDefault();
                    e.returnValue = 'Des modifications non sauvegardées seront perdues.';
                }
            });
            
            console.log('💾 Intelligent autosave configured');
        }
        
        /**
         * Sauvegarde de brouillon
         */
        async saveFormDraft() {
            const form = document.getElementById('region-form');
            if (!form) return;
            
            const formData = new FormData(form);
            formData.append('action', 'save_draft');
            
            try {
                const response = await api.post('/admin/regions/draft', formData);
                
                if (response.success) {
                    this.isDirty = false;
                    this.showAutosaveIndicator('saved');
                }
            } catch (error) {
                this.showAutosaveIndicator('error');
                throw error;
            }
        }
        
        /**
         * Indicateur d'auto-sauvegarde
         */
        showAutosaveIndicator(status) {
            let indicator = document.querySelector('.autosave-indicator');
            
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.className = 'autosave-indicator';
                document.body.appendChild(indicator);
            }
            
            const configs = {
                saving: { icon: 'fa-spinner fa-spin', color: 'info', text: 'Sauvegarde...' },
                saved: { icon: 'fa-check', color: 'success', text: 'Brouillon sauvegardé' },
                error: { icon: 'fa-exclamation-triangle', color: 'warning', text: 'Erreur de sauvegarde' }
            };
            
            const config = configs[status];
            indicator.innerHTML = `
                <div class="indicator-content">
                    <i class="fas ${config.icon} text-${config.color}"></i>
                    <span>${config.text}</span>
                </div>
            `;
            
            indicator.className = `autosave-indicator show ${config.color}`;
            
            if (status !== 'saving') {
                setTimeout(() => indicator.classList.remove('show'), 3000);
            }
        }
        
        /**
         * Configuration de l'expérience utilisateur
         */
        setupUserExperience() {
            this.setupKeyboardShortcuts();
            this.setupProgressIndicator();
            this.setupAccessibilityFeatures();
        }
        
        /**
         * Configuration des raccourcis clavier
         */
        setupKeyboardShortcuts() {
            document.addEventListener('keydown', (e) => {
                // Ctrl+S : Sauvegarder
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    this.handleFormSubmit();
                }
                
                // Ctrl+Shift+S : Sauvegarder brouillon
                if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'S') {
                    e.preventDefault();
                    this.saveFormDraft();
                }
                
                // Escape : Annuler
                if (e.key === 'Escape') {
                    this.handleCancelAction();
                }
                
                // F1 : Aide
                if (e.key === 'F1') {
                    e.preventDefault();
                    this.showHelpModal();
                }
            });
        }
        
        /**
         * Soumission moderne du formulaire
         */
        async handleModernSubmit(form) {
            try {
                // Validation complète
                const isValid = await this.validateCompleteForm(form);
                if (!isValid) {
                    this.showValidationSummary();
                    return;
                }
                
                // Indicateur de progression
                this.showSubmissionProgress();
                
                // Préparation des données
                const formData = new FormData(form);
                
                // Soumission
                const response = await api.post(form.action, formData, {
                    onUploadProgress: (progress) => {
                        this.updateUploadProgress(progress);
                    }
                });
                
                if (response.success) {
                    ui.toast.success('Région sauvegardée avec succès !');
                    this.isDirty = false;
                    
                    if (response.redirect) {
                        window.location.href = response.redirect;
                    }
                } else {
                    throw new Error(response.message || 'Erreur de sauvegarde');
                }
                
            } catch (error) {
                console.error('Form submission error:', error);
                ui.toast.error('Erreur lors de la sauvegarde : ' + error.message);
            } finally {
                this.hideSubmissionProgress();
            }
        }
        
        /**
         * Validation complète du formulaire
         */
        async validateCompleteForm(form) {
            const errors = Object.values(this.validationErrors).filter(e => e && e.length > 0);
            return errors.length === 0;
        }
        
        /**
         * Marquer le formulaire comme modifié
         */
        markDirty() {
            if (!this.isDirty) {
                this.isDirty = true;
                this.updatePageTitle();
            }
        }
        
        /**
         * Mise à jour du titre de la page
         */
        updatePageTitle() {
            const title = document.title;
            if (this.isDirty && !title.startsWith('●')) {
                document.title = '● ' + title;
            } else if (!this.isDirty && title.startsWith('●')) {
                document.title = title.substring(2);
            }
        }
        
        /**
         * Chargement des données du formulaire
         */
        loadFormData() {
            if (this.region) {
                // Charger les coordonnées sur la carte
                if (this.region.coordinates_lat && this.region.coordinates_lng) {
                    this.updateMapFromCoordinates();
                }
                
                console.log('📊 Form data loaded for region:', this.region.name);
            }
        }
        
        /**
         * Mode de secours
         */
        initializeFallback() {
            console.log('🔄 Initializing fallback mode for region form');
            
            // Fonctionnalités de base seulement
            this.setupBasicValidation();
            this.setupBasicKeyboardShortcuts();
            
            ui.toast.warning('Formulaire chargé en mode simplifié', { duration: 5000 });
        }
        
        /**
         * Validation de base
         */
        setupBasicValidation() {
            const form = document.getElementById('region-form');
            if (!form) return;
            
            form.addEventListener('submit', (e) => {
                const nameField = form.querySelector('[name="name"]');
                const countryField = form.querySelector('[name="country_id"]');
                
                if (!nameField.value.trim()) {
                    e.preventDefault();
                    alert('Le nom de la région est requis');
                    nameField.focus();
                    return;
                }
                
                if (!countryField.value) {
                    e.preventDefault();
                    alert('Le pays est requis');
                    countryField.focus();
                    return;
                }
            });
        }
        
        /**
         * Chargement dynamique de script
         */
        loadScript(src) {
            return new Promise((resolve, reject) => {
                if (document.querySelector(`script[src="${src}"]`)) {
                    resolve();
                    return;
                }
                
                const script = document.createElement('script');
                script.src = src;
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }
        
        /**
         * Nettoyage des ressources
         */
        cleanup() {
            // Nettoyer les composants
            Object.values(this.components).forEach(component => {
                if (component && component.destroy) {
                    component.destroy();
                }
            });
            
            // Nettoyer les URLs d'objets
            document.querySelectorAll('img[src^="blob:"]').forEach(img => {
                URL.revokeObjectURL(img.src);
            });
            
            // Restaurer le titre
            this.isDirty = false;
            this.updatePageTitle();
            
            console.log('🧹 Region form page cleaned up');
        }
    }
    
    return RegionFormPage;
});

// Auto-initialisation
document.addEventListener('DOMContentLoaded', async () => {
    // Vérifier qu'on est sur une page formulaire région
    if (!document.getElementById('region-form') && 
        !window.location.pathname.match(/\/regions\/(create|new|\d+\/edit)/)) {
        return;
    }
    
    try {
        // Attendre TopoclimbCH
        if (!window.TopoclimbCH || !window.TopoclimbCH.initialized) {
            await new Promise(resolve => {
                const checkReady = () => {
                    if (window.TopoclimbCH && window.TopoclimbCH.initialized) {
                        resolve();
                    } else {
                        setTimeout(checkReady, 100);
                    }
                };
                checkReady();
            });
        }
        
        // Initialiser la page
        const RegionFormPage = await TopoclimbCH.modules.load('page-region-form');
        const formPage = new RegionFormPage();
        await formPage.init();
        
        // Nettoyage
        window.addEventListener('beforeunload', () => {
            formPage.cleanup();
        });
        
    } catch (error) {
        console.error('❌ Failed to initialize region form page:', error);
    }
});

console.log('🏔️ Region Form Page module ready');