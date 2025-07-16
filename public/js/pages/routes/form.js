// public/js/pages/routes/form.js

/**
 * Gestion du formulaire de création/modification de voies
 * Fonctionnalité principale : sélecteur cascade région → secteur
 */
class RouteFormCascade {
    constructor() {
        this.regionSelect = document.getElementById('region_id');
        this.sectorSelect = document.getElementById('sector_id');
        this.sectorInfo = document.getElementById('sector-info');
        this.loadingSpinner = document.querySelector('.loading-spinner');
        this.form = document.getElementById('route-form') || document.querySelector('form');

        this.currentRegionId = null;
        this.currentSectorId = null;

        this.init();
    }

    init() {
        if (!this.regionSelect || !this.sectorSelect) {
            console.log('Sélecteurs cascade non trouvés - probablement en mode secteur fixe');
            return;
        }

        this.bindEvents();
        this.setupInitialState();
        this.setupFormValidation();

        console.log('RouteFormCascade initialisé');
    }

    bindEvents() {
        // Événement changement de région
        this.regionSelect.addEventListener('change', (e) => {
            this.onRegionChange(e.target.value);
        });

        // Événement changement de secteur  
        this.sectorSelect.addEventListener('change', (e) => {
            this.onSectorChange(e.target.value);
        });

        // Validation en temps réel
        if (this.form) {
            this.form.addEventListener('input', (e) => {
                this.validateField(e.target);
            });

            // Soumission formulaire
            this.form.addEventListener('submit', (e) => {
                this.onFormSubmit(e);
            });
        }
    }

    setupInitialState() {
        // Si on édite une voie existante ou qu'une région est présélectionnée
        const selectedRegionId = this.regionSelect.value;
        if (selectedRegionId) {
            this.currentRegionId = selectedRegionId;
            this.loadSectors(selectedRegionId, false); // false = pas de reset de la sélection
        }
    }

    async onRegionChange(regionId) {
        console.log('Changement région:', regionId);

        if (!regionId) {
            this.resetSectorSelect();
            return;
        }

        if (regionId === this.currentRegionId) {
            return; // Pas de changement
        }

        this.currentRegionId = regionId;
        await this.loadSectors(regionId, true); // true = reset de la sélection
    }

    async loadSectors(regionId, resetSelection = true) {
        try {
            this.showLoading(true);
            this.sectorSelect.disabled = true;

            if (resetSelection) {
                this.resetSectorSelect();
            }

            console.log('Chargement secteurs pour région:', regionId);

            const response = await fetch(`/api/regions/${regionId}/sectors`);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            console.log('Réponse API secteurs:', data);

            if (!data.success) {
                throw new Error(data.error || 'Erreur lors du chargement des secteurs');
            }

            this.populateSectors(data.data);
            this.sectorSelect.disabled = false;

        } catch (error) {
            console.error('Erreur chargement secteurs:', error);
            this.showError('Erreur lors du chargement des secteurs: ' + error.message);
            this.resetSectorSelect();
        } finally {
            this.showLoading(false);
        }
    }

    populateSectors(sectors) {
        console.log('Population secteurs:', sectors.length, 'éléments');

        // Vider et recréer les options
        this.sectorSelect.innerHTML = '';

        // Option par défaut
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Sélectionnez un secteur...';
        this.sectorSelect.appendChild(defaultOption);

        // Ajouter les secteurs
        sectors.forEach(sector => {
            const option = document.createElement('option');
            option.value = sector.id;
            option.textContent = sector.name;

            // Stocker les métadonnées dans les data attributes
            option.dataset.routeCount = sector.routes_count || 0;
            option.dataset.altitude = sector.altitude || '';
            option.dataset.accessTime = sector.access_time || '';

            this.sectorSelect.appendChild(option);
        });

        console.log('Secteurs populés:', this.sectorSelect.children.length - 1, 'secteurs');

        // Maintenir la sélection existante si on ne reset pas
        if (this.currentSectorId && !resetSelection) {
            this.sectorSelect.value = this.currentSectorId;
            this.updateSectorInfo();
        }
    }

    onSectorChange(sectorId) {
        console.log('Changement secteur:', sectorId);
        this.currentSectorId = sectorId;
        this.updateSectorInfo();
        this.validateField(this.sectorSelect);
    }

    updateSectorInfo() {
        if (!this.sectorInfo) return;

        if (!this.currentSectorId) {
            this.sectorInfo.textContent = '';
            return;
        }

        const selectedOption = this.sectorSelect.querySelector(`option[value="${this.currentSectorId}"]`);
        if (!selectedOption) return;

        const routeCount = selectedOption.dataset.routeCount;
        const altitude = selectedOption.dataset.altitude;
        const accessTime = selectedOption.dataset.accessTime;

        let infoText = [];

        if (routeCount) {
            infoText.push(`${routeCount} voie${routeCount > 1 ? 's' : ''}`);
        }

        if (altitude) {
            infoText.push(`${altitude}m d'altitude`);
        }

        if (accessTime) {
            infoText.push(`${accessTime}min de marche`);
        }

        this.sectorInfo.textContent = infoText.join(' • ');
    }

    resetSectorSelect() {
        this.sectorSelect.innerHTML = '<option value="">Choisissez d\'abord une région...</option>';
        this.sectorSelect.disabled = true;
        if (this.sectorInfo) {
            this.sectorInfo.textContent = '';
        }
        this.currentSectorId = null;
        this.clearFieldError(this.sectorSelect);
    }

    showLoading(show) {
        if (this.loadingSpinner) {
            this.loadingSpinner.classList.toggle('d-none', !show);
        }
    }

    showError(message) {
        // Utiliser le système de notification existant ou fallback alert
        if (window.showNotification) {
            window.showNotification(message, 'error');
        } else if (window.toastr) {
            window.toastr.error(message);
        } else {
            alert(message);
        }
        console.error('Erreur RouteFormCascade:', message);
    }

    // Validation des champs
    setupFormValidation() {
        this.validationRules = {
            'region_id': { required: true },
            'sector_id': { required: true },
            'name': { required: true, maxLength: 255 },
            'difficulty': { maxLength: 10 },
            'length': { min: 0, max: 9999 },
            'comment': { maxLength: 1000 }
        };
    }

    validateField(field) {
        if (!field.name || !this.validationRules[field.name]) {
            return true;
        }

        const rules = this.validationRules[field.name];
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';

        // Validation required
        if (rules.required && !value) {
            isValid = false;
            errorMessage = 'Ce champ est requis';
        }
        // Longueur maximale
        else if (rules.maxLength && value.length > rules.maxLength) {
            isValid = false;
            errorMessage = `Maximum ${rules.maxLength} caractères`;
        }
        // Valeur minimale
        else if (rules.min && value && parseFloat(value) < rules.min) {
            isValid = false;
            errorMessage = `Valeur minimale : ${rules.min}`;
        }
        // Valeur maximale
        else if (rules.max && value && parseFloat(value) > rules.max) {
            isValid = false;
            errorMessage = `Valeur maximale : ${rules.max}`;
        }

        // Afficher/masquer l'erreur
        if (isValid) {
            this.clearFieldError(field);
        } else {
            this.showFieldError(field, errorMessage);
        }

        return isValid;
    }

    showFieldError(field, message) {
        field.classList.add('is-invalid');
        field.classList.remove('is-valid');

        const feedback = field.parentNode.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = message;
        }
    }

    clearFieldError(field) {
        field.classList.remove('is-invalid');
        if (field.value.trim()) {
            field.classList.add('is-valid');
        } else {
            field.classList.remove('is-valid');
        }

        const feedback = field.parentNode.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = '';
        }
    }

    validateForm() {
        let isValid = true;

        // Valider tous les champs avec des règles
        Object.keys(this.validationRules).forEach(fieldName => {
            const field = document.querySelector(`[name="${fieldName}"]`);
            if (field && !this.validateField(field)) {
                isValid = false;
            }
        });

        return isValid;
    }

    onFormSubmit(e) {
        if (!this.validateForm()) {
            e.preventDefault();
            this.showError('Veuillez corriger les erreurs dans le formulaire');
            return;
        }

        // Désactiver le bouton de soumission pour éviter les doubles soumissions
        const submitBtn = this.form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sauvegarde...';
        }

        // Le formulaire sera soumis normalement
    }
}

// Auto-initialisation
document.addEventListener('DOMContentLoaded', () => {
    // Initialiser seulement si on est sur une page de formulaire de route
    if (document.getElementById('region_id') || document.getElementById('sector_id')) {
        window.routeFormCascade = new RouteFormCascade();
    }
});