// ===== REGIONS FORM PAGE JAVASCRIPT =====
// Advanced form handling with validation, map integration, and file upload

class RegionForm {
    constructor() {
        this.region = window.formData?.region || null;
        this.countries = window.formData?.countries || [];
        this.swisstopoApiKey = window.formData?.swisstopoApiKey;
        this.nominatimApiUrl = window.formData?.nominatimApiUrl;
        this.csrfToken = window.formData?.csrfToken;

        this.map = null;
        this.currentMarker = null;
        this.isEditing = !!this.region;
        this.isDirty = false;
        this.validationErrors = {};

        // File upload handling
        this.uploadedFiles = {
            cover: null,
            gallery: []
        };

        // Autosave functionality
        this.autosaveInterval = null;
        this.lastSaveData = null;

        this.init();
    }

    async init() {
        this.setupEventListeners();
        this.setupFormValidation();
        this.setupFileUploads();
        this.initializeMap();
        this.setupAutosave();
        this.loadFormData();

        // Prevent accidental navigation away
        this.setupNavigationWarning();

        console.log('RegionForm initialized', this.isEditing ? 'for editing' : 'for creation');
    }

    setupEventListeners() {
        const form = document.getElementById('region-form');
        if (form) {
            form.addEventListener('submit', (e) => this.handleSubmit(e));
            form.addEventListener('input', () => this.markDirty());
            form.addEventListener('change', () => this.markDirty());
        }

        // Map-related buttons
        const mapButtons = [
            'getCurrentLocation',
            'searchLocation',
            'clearCoordinates'
        ];

        mapButtons.forEach(buttonName => {
            const btn = document.querySelector(`[onclick="${buttonName}()"]`);
            if (btn) {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this[buttonName]();
                });
            }
        });

        // Delete confirmation
        const deleteModal = document.getElementById('delete-confirmation');
        if (deleteModal) {
            deleteModal.addEventListener('input', (e) => {
                if (e.target.id === 'delete-confirmation') {
                    this.validateDeleteConfirmation(e.target.value);
                }
            });
        }

        // Coordinate inputs
        const latInput = document.getElementById('coordinates_lat');
        const lngInput = document.getElementById('coordinates_lng');

        if (latInput && lngInput) {
            [latInput, lngInput].forEach(input => {
                input.addEventListener('input', () => this.updateMapFromCoordinates());
            });
        }

        // Save draft button
        const saveDraftBtn = document.getElementById('save-draft');
        if (saveDraftBtn) {
            saveDraftBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.saveDraft();
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                if (e.key === 's') {
                    e.preventDefault();
                    this.saveDraft();
                }
            }
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
    }

    setupFormValidation() {
        // Real-time validation
        const requiredFields = ['name', 'country_id'];

        requiredFields.forEach(fieldName => {
            const field = document.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.addEventListener('blur', () => this.validateField(fieldName));
                field.addEventListener('input', () => this.clearFieldError(fieldName));
            }
        });

        // Custom validators
        this.validators = {
            name: (value) => {
                if (!value.trim()) return 'Le nom de la région est requis';
                if (value.length < 2) return 'Le nom doit contenir au moins 2 caractères';
                if (value.length > 100) return 'Le nom ne peut pas dépasser 100 caractères';
                return null;
            },

            country_id: (value) => {
                if (!value) return 'Le pays est requis';
                return null;
            },

            coordinates_lat: (value) => {
                if (value && (isNaN(value) || value < -90 || value > 90)) {
                    return 'La latitude doit être entre -90 et 90';
                }
                return null;
            },

            coordinates_lng: (value) => {
                if (value && (isNaN(value) || value < -180 || value > 180)) {
                    return 'La longitude doit être entre -180 et 180';
                }
                return null;
            },

            altitude: (value) => {
                if (value && (isNaN(value) || value < 0 || value > 5000)) {
                    return 'L\'altitude doit être entre 0 et 5000 mètres';
                }
                return null;
            }
        };
    }

    validateField(fieldName) {
        const field = document.querySelector(`[name="${fieldName}"]`);
        if (!field || !this.validators[fieldName]) return true;

        const error = this.validators[fieldName](field.value);

        if (error) {
            this.showFieldError(fieldName, error);
            this.validationErrors[fieldName] = error;
            return false;
        } else {
            this.clearFieldError(fieldName);
            delete this.validationErrors[fieldName];
            return true;
        }
    }

    showFieldError(fieldName, message) {
        const field = document.querySelector(`[name="${fieldName}"]`);
        if (!field) return;

        // Remove existing error
        this.clearFieldError(fieldName);

        // Add error class
        field.classList.add('error');

        // Create error message
        const errorEl = document.createElement('div');
        errorEl.className = 'field-error';
        errorEl.textContent = message;
        errorEl.id = `error-${fieldName}`;

        // Insert after field or its container
        const container = field.closest('.form-group') || field.parentNode;
        container.appendChild(errorEl);
    }

    clearFieldError(fieldName) {
        const field = document.querySelector(`[name="${fieldName}"]`);
        const errorEl = document.getElementById(`error-${fieldName}`);

        if (field) {
            field.classList.remove('error');
        }

        if (errorEl) {
            errorEl.remove();
        }

        delete this.validationErrors[fieldName];
    }

    validateForm() {
        let isValid = true;

        // Validate all fields
        Object.keys(this.validators).forEach(fieldName => {
            if (!this.validateField(fieldName)) {
                isValid = false;
            }
        });

        // Custom validations
        const coordinates = this.getCoordinates();
        if ((coordinates.lat && !coordinates.lng) || (!coordinates.lat && coordinates.lng)) {
            this.showNotification('Veuillez renseigner les deux coordonnées ou aucune', 'warning');
            isValid = false;
        }

        return isValid;
    }

    setupFileUploads() {
        // Cover image upload
        const coverInput = document.getElementById('cover_image');
        const coverUpload = document.getElementById('cover-upload');

        if (coverInput && coverUpload) {
            this.setupSingleFileUpload(coverInput, coverUpload, 'cover');
        }

        // Gallery images upload
        const galleryInput = document.getElementById('gallery_images');
        const galleryUpload = document.getElementById('gallery-upload');

        if (galleryInput && galleryUpload) {
            this.setupMultipleFileUpload(galleryInput, galleryUpload, 'gallery');
        }
    }

    setupSingleFileUpload(input, container, type) {
        // Drag and drop
        container.addEventListener('dragover', (e) => {
            e.preventDefault();
            container.classList.add('drag-over');
        });

        container.addEventListener('dragleave', () => {
            container.classList.remove('drag-over');
        });

        container.addEventListener('drop', (e) => {
            e.preventDefault();
            container.classList.remove('drag-over');

            const files = Array.from(e.dataTransfer.files);
            this.handleFileSelection(files.slice(0, 1), type, input);
        });

        // Click to upload
        container.addEventListener('click', () => {
            input.click();
        });

        // File input change
        input.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            this.handleFileSelection(files, type, input);
        });
    }

    setupMultipleFileUpload(input, container, type) {
        // Drag and drop
        container.addEventListener('dragover', (e) => {
            e.preventDefault();
            container.classList.add('drag-over');
        });

        container.addEventListener('dragleave', () => {
            container.classList.remove('drag-over');
        });

        container.addEventListener('drop', (e) => {
            e.preventDefault();
            container.classList.remove('drag-over');

            const files = Array.from(e.dataTransfer.files);
            this.handleFileSelection(files, type, input);
        });

        // Click to upload
        container.addEventListener('click', () => {
            input.click();
        });

        // File input change
        input.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            this.handleFileSelection(files, type, input);
        });
    }

    handleFileSelection(files, type, input) {
        const validFiles = files.filter(file => this.validateFile(file));

        if (validFiles.length === 0) return;

        if (type === 'cover') {
            this.uploadedFiles.cover = validFiles[0];
            this.previewCoverImage(validFiles[0]);
        } else if (type === 'gallery') {
            this.uploadedFiles.gallery = [...this.uploadedFiles.gallery, ...validFiles];
            this.previewGalleryImages(validFiles);
        }

        this.markDirty();
    }

    validateFile(file) {
        // Check file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            this.showNotification('Seuls les fichiers JPG, PNG et WebP sont autorisés', 'error');
            return false;
        }

        // Check file size (5MB max)
        const maxSize = 5 * 1024 * 1024;
        if (file.size > maxSize) {
            this.showNotification('La taille du fichier ne doit pas dépasser 5MB', 'error');
            return false;
        }

        return true;
    }

    previewCoverImage(file) {
        const container = document.getElementById('cover-upload');
        if (!container) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            container.innerHTML = `
                <div class="file-preview">
                    <img src="${e.target.result}" alt="Aperçu" class="preview-image">
                    <div class="preview-overlay">
                        <i class="fas fa-edit"></i>
                        <span>Changer l'image</span>
                    </div>
                    <button type="button" class="preview-remove" onclick="regionForm.removeCoverImage()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    }

    previewGalleryImages(files) {
        const container = document.getElementById('gallery-upload');
        if (!container) return;

        files.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const preview = document.createElement('div');
                preview.className = 'gallery-preview-item';
                preview.innerHTML = `
                    <img src="${e.target.result}" alt="Aperçu ${index + 1}">
                    <button type="button" class="preview-remove" onclick="regionForm.removeGalleryImage(${this.uploadedFiles.gallery.indexOf(file)})">
                        <i class="fas fa-times"></i>
                    </button>
                `;

                container.appendChild(preview);
            };
            reader.readAsDataURL(file);
        });
    }

    removeCoverImage() {
        this.uploadedFiles.cover = null;

        const container = document.getElementById('cover-upload');
        if (container) {
            container.innerHTML = `
                <input type="file" name="cover_image" id="cover_image" accept="image/*" class="file-input">
                <div class="upload-content">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>Cliquez ou glissez pour ajouter une image de couverture</span>
                    <small>JPG, PNG, max 5MB</small>
                </div>
            `;

            // Re-setup file upload
            const input = container.querySelector('input[type="file"]');
            if (input) {
                this.setupSingleFileUpload(input, container, 'cover');
            }
        }

        this.markDirty();
    }

    removeGalleryImage(index) {
        this.uploadedFiles.gallery.splice(index, 1);

        // Re-render gallery previews
        const container = document.getElementById('gallery-upload');
        if (container) {
            const previews = container.querySelectorAll('.gallery-preview-item');
            previews.forEach(preview => preview.remove());

            this.previewGalleryImages(this.uploadedFiles.gallery);
        }

        this.markDirty();
    }

    async initializeMap() {
        try {
            // Setup Swiss projections
            this.setupSwissProjections();

            // Initialize map
            const initialCoords = this.getInitialMapCoords();

            this.map = L.map('coordinate-map', {
                center: initialCoords,
                zoom: this.region ? 12 : 8,
                maxZoom: 18,
                minZoom: 6
            });

            // Add base layers
            await this.addMapLayers();

            // Setup map interactions
            this.setupMapInteractions();

            // Add existing marker if editing
            if (this.region && this.region.coordinates_lat && this.region.coordinates_lng) {
                this.addMarker(this.region.coordinates_lat, this.region.coordinates_lng);
            }

        } catch (error) {
            console.error('Map initialization error:', error);
            this.showMapError();
        }
    }

    setupSwissProjections() {
        if (window.proj4) {
            proj4.defs("EPSG:2056", "+proj=somerc +lat_0=46.95240555555556 +lon_0=7.439583333333333 +k_0=1 +x_0=2600000 +y_0=1200000 +ellps=bessel +towgs84=674.374,15.056,405.346,0,0,0,0 +units=m +no_defs");
        }
    }

    getInitialMapCoords() {
        // If editing and has coordinates, use those
        if (this.region && this.region.coordinates_lat && this.region.coordinates_lng) {
            return [this.region.coordinates_lat, this.region.coordinates_lng];
        }

        // Otherwise center on Switzerland
        return [46.8182, 8.2275];
    }

    async addMapLayers() {
        const baseLayers = {};

        if (this.swisstopoApiKey) {
            baseLayers['Carte nationale'] = L.tileLayer(
                'https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.pixelkarte-farbe/default/current/3857/{z}/{x}/{y}.jpeg',
                {
                    attribution: '© swisstopo',
                    maxZoom: 18
                }
            );
        } else {
            baseLayers['OpenStreetMap'] = L.tileLayer(
                'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 18
                }
            );
        }

        // Add first layer
        const firstLayer = Object.values(baseLayers)[0];
        firstLayer.addTo(this.map);

        // Add layer control if multiple layers
        if (Object.keys(baseLayers).length > 1) {
            L.control.layers(baseLayers).addTo(this.map);
        }
    }

    setupMapInteractions() {
        // Click to place marker
        this.map.on('click', (e) => {
            this.addMarker(e.latlng.lat, e.latlng.lng);
            this.updateCoordinateInputs(e.latlng.lat, e.latlng.lng);
        });

        // Add scale control
        L.control.scale({
            metric: true,
            imperial: false,
            position: 'bottomleft'
        }).addTo(this.map);
    }

    addMarker(lat, lng) {
        // Remove existing marker
        if (this.currentMarker) {
            this.map.removeLayer(this.currentMarker);
        }

        // Create new marker
        this.currentMarker = L.marker([lat, lng], {
            draggable: true,
            icon: this.createCustomIcon()
        });

        // Add to map
        this.currentMarker.addTo(this.map);

        // Setup drag event
        this.currentMarker.on('dragend', (e) => {
            const position = e.target.getLatLng();
            this.updateCoordinateInputs(position.lat, position.lng);
        });

        // Add popup
        this.currentMarker.bindPopup(`
            <div class="marker-popup">
                <strong>Position de la région</strong><br>
                Lat: ${lat.toFixed(6)}<br>
                Lng: ${lng.toFixed(6)}<br>
                <small>Faites glisser pour ajuster</small>
            </div>
        `);

        this.markDirty();
    }

    createCustomIcon() {
        return L.divIcon({
            html: '<div class="custom-marker"><i class="fas fa-map-marker-alt"></i></div>',
            className: 'custom-marker-container',
            iconSize: [30, 30],
            iconAnchor: [15, 30],
            popupAnchor: [0, -30]
        });
    }

    updateCoordinateInputs(lat, lng) {
        const latInput = document.getElementById('coordinates_lat');
        const lngInput = document.getElementById('coordinates_lng');

        if (latInput) latInput.value = lat.toFixed(6);
        if (lngInput) lngInput.value = lng.toFixed(6);
    }

    updateMapFromCoordinates() {
        const coordinates = this.getCoordinates();

        if (coordinates.lat && coordinates.lng) {
            if (this.map) {
                this.map.setView([coordinates.lat, coordinates.lng], 12);
                this.addMarker(coordinates.lat, coordinates.lng);
            }
        }
    }

    getCoordinates() {
        const latInput = document.getElementById('coordinates_lat');
        const lngInput = document.getElementById('coordinates_lng');

        return {
            lat: latInput ? parseFloat(latInput.value) || null : null,
            lng: lngInput ? parseFloat(lngInput.value) || null : null
        };
    }

    getCurrentLocation() {
        if (!navigator.geolocation) {
            this.showNotification('Géolocalisation non supportée par votre navigateur', 'error');
            return;
        }

        this.showLoading('Obtention de votre position...');

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                this.updateCoordinateInputs(lat, lng);

                if (this.map) {
                    this.map.setView([lat, lng], 15);
                    this.addMarker(lat, lng);
                }

                this.hideLoading();
                this.showNotification('Position actuelle utilisée', 'success');
            },
            (error) => {
                this.hideLoading();
                let message = 'Impossible d\'obtenir votre position';

                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        message = 'Permission de géolocalisation refusée';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message = 'Position non disponible';
                        break;
                    case error.TIMEOUT:
                        message = 'Délai d\'attente de géolocalisation dépassé';
                        break;
                }

                this.showNotification(message, 'error');
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 60000
            }
        );
    }

    searchLocation() {
        const modal = document.getElementById('location-search-modal');
        if (modal) {
            modal.classList.add('active');
            document.getElementById('location-search')?.focus();
        }
    }

    closeLocationSearch() {
        const modal = document.getElementById('location-search-modal');
        if (modal) {
            modal.classList.remove('active');
        }
    }

    async performLocationSearch() {
        const searchInput = document.getElementById('location-search');
        const resultsContainer = document.getElementById('search-results');

        if (!searchInput || !resultsContainer) return;

        const query = searchInput.value.trim();
        if (!query) {
            this.showNotification('Veuillez saisir une localisation', 'warning');
            return;
        }

        resultsContainer.innerHTML = '<div class="loading-search">Recherche en cours...</div>';

        try {
            const results = await this.geocodeLocation(query);
            this.displaySearchResults(results, resultsContainer);
        } catch (error) {
            console.error('Geocoding error:', error);
            resultsContainer.innerHTML = '<div class="error-search">Erreur lors de la recherche</div>';
        }
    }

    async geocodeLocation(query) {
        const response = await fetch(
            `${this.nominatimApiUrl}/search?format=json&q=${encodeURIComponent(query)}&limit=5&addressdetails=1&countrycodes=ch,fr,it,at,de`
        );

        if (!response.ok) {
            throw new Error('Geocoding request failed');
        }

        return await response.json();
    }

    displaySearchResults(results, container) {
        if (results.length === 0) {
            container.innerHTML = '<div class="no-results">Aucun résultat trouvé</div>';
            return;
        }

        container.innerHTML = results.map(result => `
            <div class="search-result-item" onclick="regionForm.selectSearchResult(${result.lat}, ${result.lon})">
                <div class="result-name">${result.display_name}</div>
                <div class="result-type">${result.type || 'Lieu'}</div>
            </div>
        `).join('');
    }

    selectSearchResult(lat, lng) {
        this.updateCoordinateInputs(lat, lng);

        if (this.map) {
            this.map.setView([lat, lng], 12);
            this.addMarker(lat, lng);
        }

        this.closeLocationSearch();
        this.showNotification('Localisation sélectionnée', 'success');
    }

    clearCoordinates() {
        const latInput = document.getElementById('coordinates_lat');
        const lngInput = document.getElementById('coordinates_lng');

        if (latInput) latInput.value = '';
        if (lngInput) lngInput.value = '';

        if (this.currentMarker && this.map) {
            this.map.removeLayer(this.currentMarker);
            this.currentMarker = null;
        }

        if (this.map) {
            this.map.setView([46.8182, 8.2275], 8);
        }

        this.markDirty();
    }

    setupAutosave() {
        // Autosave every 30 seconds if form is dirty
        this.autosaveInterval = setInterval(() => {
            if (this.isDirty) {
                this.saveDraft(true); // Silent save
            }
        }, 30000);
    }

    loadFormData() {
        // Load any existing draft data
        const draftKey = this.isEditing ? `region_edit_${this.region.id}` : 'region_create';
        const savedDraft = localStorage.getItem(draftKey);

        if (savedDraft && !this.isEditing) {
            try {
                const draftData = JSON.parse(savedDraft);
                this.confirmLoadDraft(draftData);
            } catch (error) {
                console.error('Error loading draft:', error);
                localStorage.removeItem(draftKey);
            }
        }
    }

    confirmLoadDraft(draftData) {
        if (confirm('Un brouillon non sauvegardé a été trouvé. Souhaitez-vous le restaurer ?')) {
            this.restoreDraft(draftData);
        } else {
            this.clearDraft();
        }
    }

    restoreDraft(data) {
        Object.keys(data).forEach(key => {
            const field = document.querySelector(`[name="${key}"]`);
            if (field) {
                if (field.type === 'checkbox') {
                    field.checked = data[key];
                } else {
                    field.value = data[key];
                }
            }
        });

        // Update map if coordinates exist
        if (data.coordinates_lat && data.coordinates_lng) {
            this.updateMapFromCoordinates();
        }

        this.showNotification('Brouillon restauré', 'success');
    }

    saveDraft(silent = false) {
        const formData = this.getFormData();
        const draftKey = this.isEditing ? `region_edit_${this.region.id}` : 'region_create';

        try {
            localStorage.setItem(draftKey, JSON.stringify(formData));
            this.lastSaveData = formData;
            this.isDirty = false;

            if (!silent) {
                this.showNotification('Brouillon sauvegardé', 'success');
            }

            // Update save button state
            const saveBtn = document.getElementById('save-draft');
            if (saveBtn) {
                saveBtn.innerHTML = '<i class="fas fa-check"></i> Sauvegardé';
                setTimeout(() => {
                    saveBtn.innerHTML = '<i class="fas fa-save"></i> Enregistrer brouillon';
                }, 2000);
            }

        } catch (error) {
            console.error('Error saving draft:', error);
            if (!silent) {
                this.showNotification('Erreur lors de la sauvegarde du brouillon', 'error');
            }
        }
    }

    clearDraft() {
        const draftKey = this.isEditing ? `region_edit_${this.region.id}` : 'region_create';
        localStorage.removeItem(draftKey);
    }

    getFormData() {
        const form = document.getElementById('region-form');
        if (!form) return {};

        const formData = new FormData(form);
        const data = {};

        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }

        return data;
    }

    markDirty() {
        this.isDirty = true;

        // Update save button state
        const saveBtn = document.getElementById('save-draft');
        if (saveBtn && saveBtn.innerHTML.includes('Sauvegardé')) {
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Enregistrer brouillon';
        }
    }

    setupNavigationWarning() {
        window.addEventListener('beforeunload', (e) => {
            if (this.isDirty) {
                e.preventDefault();
                e.returnValue = 'Vous avez des modifications non sauvegardées. Êtes-vous sûr de vouloir quitter ?';
                return e.returnValue;
            }
        });

        // Handle navigation via links
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a[href]');
            if (link && this.isDirty && !link.hasAttribute('download')) {
                const href = link.getAttribute('href');
                if (href.startsWith('/') || href.startsWith('http')) {
                    if (!confirm('Vous avez des modifications non sauvegardées. Êtes-vous sûr de vouloir quitter ?')) {
                        e.preventDefault();
                    }
                }
            }
        });
    }

    async handleSubmit(e) {
        e.preventDefault();

        // Validate form
        if (!this.validateForm()) {
            this.showNotification('Veuillez corriger les erreurs dans le formulaire', 'error');
            this.scrollToFirstError();
            return;
        }

        this.showLoading('Enregistrement en cours...');

        try {
            const formData = new FormData(e.target);

            // Add uploaded files
            if (this.uploadedFiles.cover) {
                formData.set('cover_image', this.uploadedFiles.cover);
            }

            this.uploadedFiles.gallery.forEach((file, index) => {
                formData.append('gallery_images[]', file);
            });

            // Submit form
            const response = await fetch(e.target.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-Token': this.csrfToken
                }
            });

            const result = await response.json();

            if (response.ok) {
                // Clear draft on successful save
                this.clearDraft();
                this.isDirty = false;

                this.showNotification('Région sauvegardée avec succès', 'success');

                // Redirect after short delay
                setTimeout(() => {
                    window.location.href = result.redirect || `/regions/${result.region.id}`;
                }, 1500);

            } else {
                throw new Error(result.message || 'Erreur lors de la sauvegarde');
            }

        } catch (error) {
            console.error('Submit error:', error);
            this.showNotification(error.message || 'Erreur lors de la sauvegarde', 'error');
        } finally {
            this.hideLoading();
        }
    }

    scrollToFirstError() {
        const firstError = document.querySelector('.field-error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    // Delete functionality
    confirmDelete() {
        const modal = document.getElementById('delete-modal');
        if (modal) {
            modal.classList.add('active');
        }
    }

    closeDeleteModal() {
        const modal = document.getElementById('delete-modal');
        if (modal) {
            modal.classList.remove('active');
        }

        // Reset confirmation input
        const input = document.getElementById('delete-confirmation');
        if (input) {
            input.value = '';
            this.validateDeleteConfirmation('');
        }
    }

    validateDeleteConfirmation(value) {
        const btn = document.getElementById('confirm-delete-btn');
        const expected = this.region ? this.region.name : '';

        if (btn) {
            btn.disabled = value !== expected;
        }
    }

    async executeDelete() {
        if (!this.region) return;

        this.showLoading('Suppression en cours...');

        try {
            const response = await fetch(`/regions/${this.region.id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': this.csrfToken,
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                this.clearDraft();
                this.showNotification('Région supprimée avec succès', 'success');

                setTimeout(() => {
                    window.location.href = '/regions';
                }, 1500);

            } else {
                throw new Error('Erreur lors de la suppression');
            }

        } catch (error) {
            console.error('Delete error:', error);
            this.showNotification('Erreur lors de la suppression', 'error');
        } finally {
            this.hideLoading();
        }
    }

    // Utility methods
    showLoading(message = 'Chargement...') {
        const overlay = document.getElementById('loading-overlay');
        const text = document.getElementById('loading-text');

        if (overlay) {
            if (text) text.textContent = message;
            overlay.classList.add('active');
        }
    }

    hideLoading() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.classList.remove('active');
        }
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${this.getNotificationIcon(type)}"></i>
            <span>${message}</span>
        `;

        document.body.appendChild(notification);

        setTimeout(() => notification.classList.add('show'), 100);

        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => document.body.removeChild(notification), 300);
        }, 4000);
    }

    getNotificationIcon(type) {
        const icons = {
            info: 'info-circle',
            success: 'check-circle',
            warning: 'exclamation-triangle',
            error: 'times-circle'
        };
        return icons[type] || 'info-circle';
    }

    closeAllModals() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => modal.classList.remove('active'));
    }

    showMapError() {
        const mapContainer = document.getElementById('coordinate-map');
        if (mapContainer) {
            mapContainer.innerHTML = `
                <div class="map-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Erreur lors du chargement de la carte</p>
                    <button onclick="regionForm.initializeMap()" class="btn-retry">
                        <i class="fas fa-redo"></i>
                        Réessayer
                    </button>
                </div>
            `;
        }
    }

    // Cleanup
    destroy() {
        if (this.autosaveInterval) {
            clearInterval(this.autosaveInterval);
        }

        if (this.map) {
            this.map.remove();
        }
    }
}

// Global functions for onclick handlers
window.getCurrentLocation = function () {
    if (window.regionForm) {
        window.regionForm.getCurrentLocation();
    }
};

window.searchLocation = function () {
    if (window.regionForm) {
        window.regionForm.searchLocation();
    }
};

window.clearCoordinates = function () {
    if (window.regionForm) {
        window.regionForm.clearCoordinates();
    }
};

window.closeLocationSearch = function () {
    if (window.regionForm) {
        window.regionForm.closeLocationSearch();
    }
};

window.performLocationSearch = function () {
    if (window.regionForm) {
        window.regionForm.performLocationSearch();
    }
};

window.confirmDelete = function () {
    if (window.regionForm) {
        window.regionForm.confirmDelete();
    }
};

window.closeDeleteModal = function () {
    if (window.regionForm) {
        window.regionForm.closeDeleteModal();
    }
};

window.executeDelete = function () {
    if (window.regionForm) {
        window.regionForm.executeDelete();
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    window.regionForm = new RegionForm();
});

// Cleanup on page unload
window.addEventListener('beforeunload', function () {
    if (window.regionForm) {
        window.regionForm.destroy();
    }
});

// Add notification styles
document.addEventListener('DOMContentLoaded', function () {
    const style = document.createElement('style');
    style.textContent = `
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            color: white;
            font-weight: 600;
            z-index: 3000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            max-width: 300px;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification-info {
            background: #3b82f6;
        }
        
        .notification-success {
            background: #10b981;
        }
        
        .notification-warning {
            background: #f59e0b;
        }
        
        .notification-error {
            background: #ef4444;
        }
        
        .field-error {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .field-error::before {
            content: '⚠';
        }
        
        .form-input.error,
        .form-select.error,
        .form-textarea.error {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }
        
        .custom-marker-container {
            background: transparent;
            border: none;
        }
        
        .custom-marker {
            width: 30px;
            height: 30px;
            background: #667eea;
            border-radius: 50% 50% 50% 0;
            transform: rotate(-45deg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            border: 2px solid white;
        }
        
        .custom-marker i {
            transform: rotate(45deg);
        }
        
        .file-preview {
            position: relative;
            width: 100%;
            height: 200px;
            border-radius: 0.5rem;
            overflow: hidden;
            cursor: pointer;
        }
        
        .preview-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .preview-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            color: white;
            gap: 0.5rem;
        }
        
        .file-preview:hover .preview-overlay {
            opacity: 1;
        }
        
        .preview-remove {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.15s ease;
        }
        
        .preview-remove:hover {
            background: #ef4444;
        }
        
        .gallery-preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 0.375rem;
            overflow: hidden;
            display: inline-block;
            margin: 0.25rem;
        }
        
        .gallery-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .search-result-item {
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        
        .search-result-item:hover {
            background: #f3f4f6;
        }
        
        .search-result-item:last-child {
            border-bottom: none;
        }
        
        .result-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        
        .result-type {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .loading-search,
        .error-search,
        .no-results {
            padding: 2rem;
            text-align: center;
            color: #6b7280;
        }
        
        .error-search {
            color: #ef4444;
        }
        
        .map-error {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #6b7280;
            text-align: center;
            padding: 2rem;
        }
        
        .map-error i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ef4444;
        }
        
        .btn-retry {
            margin-top: 1rem;
            padding: 0.5rem 1rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        
        .btn-retry:hover {
            background: #5a67d8;
        }
        
        .drag-over {
            border-color: #667eea !important;
            background: rgba(102, 126, 234, 0.05) !important;
        }
    `;
    document.head.appendChild(style);
});