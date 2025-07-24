/**
 * Site Form Page - Version moderne modulaire
 * Remplace l'ancien form.js avec architecture moderne
 */

// Enregistrement du module de page site form
TopoclimbCH.modules.register('page-site-form', ['utils', 'api', 'ui'], async (utils, api, ui) => {
    
    class SiteFormPage {
        constructor() {
            this.isEdit = window.location.pathname.includes('/edit');
            this.regionId = this.getRegionId();
            this.siteId = this.getSiteId();
            this.validationRules = this.setupValidationRules();
            this.autoSaveEnabled = true;
            this.autoSaveInterval = null;
            this.initialized = false;
        }
        
        /**
         * Initialise la page formulaire
         */
        async init() {
            if (this.initialized) {
                console.warn('Site form page already initialized');
                return;
            }
            
            console.log(`📝 Initializing site form page: ${this.isEdit ? 'edit' : 'create'}`);
            
            try {
                // Configuration de la validation moderne
                await this.setupModernValidation();
                
                // Amélioration des champs
                this.setupFieldEnhancements();
                
                // Configuration des fonctionnalités avancées
                this.setupAdvancedFeatures();
                
                // Configuration des raccourcis et UX
                this.setupUserExperience();
                
                // Auto-sauvegarde intelligente
                if (this.isEdit) {
                    this.setupIntelligentAutoSave();
                }
                
                this.initialized = true;
                console.log('✅ Site form page initialized successfully');
                
            } catch (error) {
                console.error('❌ Failed to initialize site form page:', error);
                this.initializeFallback();
            }
        }
        
        /**
         * Extrait l'ID de la région depuis les données du formulaire
         */
        getRegionId() {
            const regionInput = document.querySelector('input[name="region_id"]');
            return regionInput ? parseInt(regionInput.value) : null;
        }
        
        /**
         * Extrait l'ID du site depuis l'URL (mode édition)
         */
        getSiteId() {
            if (!this.isEdit) return null;
            const matches = window.location.pathname.match(/\/sites\/(\d+)\/edit/);
            return matches ? parseInt(matches[1]) : null;
        }
        
        /**
         * Configuration des règles de validation modernes
         */
        setupValidationRules() {
            return {
                name: {
                    required: true,
                    minLength: 2,
                    maxLength: 255,
                    pattern: /^[a-zA-ZÀ-ÿ0-9\s\-'\.]+$/,
                    async: false
                },
                code: {
                    required: false,
                    minLength: 1,
                    maxLength: 50,
                    pattern: /^[A-Z0-9]+$/i,
                    async: true, // Vérification d'unicité
                    debounce: 500
                },
                description: {
                    maxLength: 65535,
                    async: false
                },
                year: {
                    min: 1900,
                    max: new Date().getFullYear() + 5,
                    async: false
                },
                publisher: {
                    maxLength: 100,
                    async: false
                },
                isbn: {
                    pattern: /^[\d\-Xx]*$/,
                    validator: this.validateIsbn.bind(this),
                    async: false
                }
            };
        }
        
        /**
         * Configuration de la validation moderne avec async/await
         */
        async setupModernValidation() {
            const form = document.querySelector('.site-form-content');
            if (!form) return;
            
            // Configuration de la validation pour chaque champ
            for (const [fieldName, rules] of Object.entries(this.validationRules)) {
                const field = form.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    await this.setupFieldValidation(field, rules);
                }
            }
            
            // Validation globale du formulaire
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.handleFormSubmission(form);
            });
            
            console.log('📋 Modern validation system configured');
        }
        
        /**
         * Configuration avancée de la validation d'un champ
         */
        async setupFieldValidation(field, rules) {
            const fieldGroup = field.closest('.form-group');
            
            // Validation en temps réel avec debouncing intelligent
            let validationTimeout;
            const debouncedValidate = utils.debounce(async () => {
                await this.validateField(field, rules);
            }, rules.debounce || 300);
            
            // Événements de validation
            field.addEventListener('input', () => {
                this.updateFieldVisuals(field);
                debouncedValidate();
            });
            
            field.addEventListener('blur', async () => {
                await this.validateField(field, rules);
            });
            
            // Amélioration UX pendant la frappe
            field.addEventListener('input', () => {
                this.provideLiveFieldFeedback(field, rules);
            });
        }
        
        /**
         * Validation moderne d'un champ avec gestion async
         */
        async validateField(field, rules) {
            const value = field.value.trim();
            const errors = [];
            
            try {
                // Validation synchrone
                const syncErrors = this.performSyncValidation(value, rules);
                errors.push(...syncErrors);
                
                // Validation asynchrone si nécessaire
                if (rules.async && value && errors.length === 0) {
                    const asyncErrors = await this.performAsyncValidation(field, value, rules);
                    errors.push(...asyncErrors);
                }
                
                // Validation personnalisée
                if (rules.validator && value && errors.length === 0) {
                    const customErrors = await rules.validator(value);
                    if (customErrors) errors.push(...(Array.isArray(customErrors) ? customErrors : [customErrors]));
                }
                
                this.displayFieldErrors(field, errors);
                return errors.length === 0;
                
            } catch (error) {
                console.error(`Validation error for field ${field.name}:`, error);
                this.displayFieldErrors(field, ['Erreur de validation']);
                return false;
            }
        }
        
        /**
         * Validation synchrone
         */
        performSyncValidation(value, rules) {
            const errors = [];
            
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
            
            if (rules.min !== undefined || rules.max !== undefined) {
                const numValue = parseInt(value);
                if (value && isNaN(numValue)) {
                    errors.push('Doit être un nombre');
                } else if (rules.min !== undefined && numValue < rules.min) {
                    errors.push(`Minimum ${rules.min}`);
                } else if (rules.max !== undefined && numValue > rules.max) {
                    errors.push(`Maximum ${rules.max}`);
                }
            }
            
            return errors;
        }
        
        /**
         * Validation asynchrone (unicité, etc.)
         */
        async performAsyncValidation(field, value, rules) {
            const errors = [];
            
            if (field.name === 'code') {
                try {
                    const exists = await this.checkCodeUniqueness(value);
                    if (exists) {
                        errors.push('Ce code est déjà utilisé');
                    }
                } catch (error) {
                    console.warn('Code uniqueness check failed:', error);
                }
            }
            
            return errors;
        }
        
        /**
         * Vérification d'unicité du code avec cache
         */
        async checkCodeUniqueness(code) {
            if (!code.trim()) return false;
            
            const cacheKey = `code-uniqueness-${code}-${this.siteId || 'new'}`;
            
            try {
                const result = await api.get(`/api/sites/check-code`, {
                    code: code,
                    site_id: this.siteId || ''
                }, { cache: true, cacheKey, cacheTime: 30000 });
                
                return result.exists;
            } catch (error) {
                console.error('Code uniqueness check error:', error);
                return false;
            }
        }
        
        /**
         * Validation ISBN personnalisée
         */
        validateIsbn(isbn) {
            if (!isbn.trim()) return null;
            
            const cleanIsbn = isbn.replace(/[\-\s]/g, '');
            
            if (cleanIsbn.length === 13 && /^\d{12}[\dXx]$/.test(cleanIsbn)) {
                return null; // Valide
            } else if (cleanIsbn.length === 10 && /^\d{9}[\dXx]$/.test(cleanIsbn)) {
                return null; // Valide
            } else if (cleanIsbn.length > 0) {
                return 'Format ISBN invalide (ISBN-10 ou ISBN-13 attendu)';
            }
            
            return null;
        }
        
        /**
         * Affichage moderne des erreurs de champ
         */
        displayFieldErrors(field, errors) {
            const fieldGroup = field.closest('.form-group');
            
            // Nettoyer les erreurs existantes
            fieldGroup.querySelectorAll('.field-error').forEach(el => el.remove());
            
            // État visuel moderne
            field.classList.remove('is-valid', 'is-invalid', 'is-validating');
            
            if (errors.length > 0) {
                field.classList.add('is-invalid');
                
                // Afficher les erreurs avec animation
                errors.forEach((error, index) => {
                    setTimeout(() => {
                        const errorEl = document.createElement('div');
                        errorEl.className = 'field-error text-danger mt-1 fade-in';
                        errorEl.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${error}`;
                        fieldGroup.appendChild(errorEl);
                    }, index * 100);
                });
                
            } else if (field.value.trim()) {
                field.classList.add('is-valid');
            }
        }
        
        /**
         * Feedback visuel en temps réel pendant la frappe
         */
        provideLiveFieldFeedback(field, rules) {
            const value = field.value.trim();
            
            // Indicateur de progression pour les champs longs
            if (rules.maxLength) {
                this.updateCharacterCounter(field, value.length, rules.maxLength);
            }
            
            // Suggestions contextuelles
            switch (field.name) {
                case 'name':
                    this.updateHierarchyPreview();
                    this.suggestCodeFromName(value);
                    break;
                case 'code':
                    this.updateCodePreview(field, value);
                    break;
                case 'isbn':
                    this.formatIsbnInput(field, value);
                    break;
            }
        }
        
        /**
         * Compteur de caractères moderne avec couleurs progressives
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
            let icon = 'fa-info-circle';
            
            if (percentage >= 95) {
                className = 'text-danger';
                icon = 'fa-exclamation-triangle';
            } else if (percentage >= 85) {
                className = 'text-warning';
                icon = 'fa-exclamation-circle';
            } else if (percentage >= 70) {
                className = 'text-info';
                icon = 'fa-info-circle';
            }
            
            counter.className = `char-counter mt-1 ${className}`;
            counter.innerHTML = `<i class="fas ${icon}"></i> ${current}/${max} caractères`;
            
            // Barre de progression visuelle
            const progressBar = counter.querySelector('.progress-bar') || document.createElement('div');
            if (!counter.querySelector('.progress-bar')) {
                progressBar.className = 'progress-bar mt-1';
                progressBar.style.cssText = `
                    height: 3px;
                    background: linear-gradient(90deg, #28a745 0%, #ffc107 70%, #dc3545 90%);
                    border-radius: 2px;
                    transition: all 0.3s ease;
                `;
                counter.appendChild(progressBar);
            }
            
            progressBar.style.width = Math.min(percentage, 100) + '%';
        }
        
        /**
         * Suggestion automatique de code basée sur le nom
         */
        suggestCodeFromName(name) {
            const codeField = document.querySelector('[name="code"]');
            if (!codeField || codeField.value.trim()) return;
            
            const suggested = name
                .replace(/[^a-zA-Z0-9\s]/g, '')
                .split(' ')
                .filter(word => word.length > 0)
                .map(word => word.substring(0, 3).toUpperCase())
                .join('')
                .substring(0, 8);
            
            if (suggested.length >= 2) {
                this.showCodeSuggestion(codeField, suggested);
            }
        }
        
        /**
         * Affichage moderne des suggestions de code
         */
        showCodeSuggestion(field, suggested) {
            let suggestion = field.parentNode.querySelector('.code-suggestion');
            
            if (!suggestion) {
                suggestion = document.createElement('div');
                suggestion.className = 'code-suggestion mt-2 fade-in';
                field.parentNode.appendChild(suggestion);
            }
            
            suggestion.innerHTML = `
                <div class="suggestion-card">
                    <i class="fas fa-lightbulb text-warning"></i>
                    <span>Suggestion: </span>
                    <button type="button" class="btn-suggestion" data-suggestion="${suggested}">
                        ${suggested}
                    </button>
                </div>
            `;
            
            // Accepter la suggestion
            const suggestionBtn = suggestion.querySelector('.btn-suggestion');
            suggestionBtn.addEventListener('click', () => {
                field.value = suggested;
                field.dispatchEvent(new Event('input'));
                suggestion.remove();
                ui.toast.success('Code suggéré appliqué !');
            });
        }
        
        /**
         * Aperçu en temps réel du code formaté
         */
        updateCodePreview(field, value) {
            let preview = field.parentNode.querySelector('.code-preview');
            
            if (value.trim()) {
                if (!preview) {
                    preview = document.createElement('div');
                    preview.className = 'code-preview mt-2';
                    field.parentNode.appendChild(preview);
                }
                
                const formattedCode = value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                preview.innerHTML = `
                    <div class="preview-card">
                        <i class="fas fa-eye text-info"></i>
                        <span>Aperçu: </span>
                        <strong class="code-display">${formattedCode}</strong>
                    </div>
                `;
            } else if (preview) {
                preview.remove();
            }
        }
        
        /**
         * Formatage automatique de l'ISBN
         */
        formatIsbnInput(field, value) {
            let formatted = value.replace(/[^\dXx\-]/g, '');
            
            // Format ISBN-13: 978-3-xxx-xxxxx-x
            if (formatted.length > 3 && !formatted.includes('-')) {
                if (formatted.startsWith('978') || formatted.startsWith('979')) {
                    formatted = formatted.replace(/(\d{3})(\d{1})(\d{3})(\d{5})(\d{1})/, '$1-$2-$3-$4-$5');
                }
            }
            
            if (formatted !== field.value) {
                field.value = formatted;
            }
        }
        
        /**
         * Amélioration des champs du formulaire
         */
        setupFieldEnhancements() {
            // Auto-resize intelligent des textareas
            this.setupAutoResizeTextareas();
            
            // Aide contextuelle à la rédaction
            this.setupWritingAssistance();
            
            // Formatage automatique des champs
            this.setupAutoFormatting();
            
            // Suggestions intelligentes
            this.setupIntelligentSuggestions();
        }
        
        /**
         * Auto-resize intelligent des textareas
         */
        setupAutoResizeTextareas() {
            const textareas = document.querySelectorAll('textarea');
            
            textareas.forEach(textarea => {
                // Resize initial
                this.resizeTextarea(textarea);
                
                // Resize dynamique
                textarea.addEventListener('input', () => {
                    this.resizeTextarea(textarea);
                });
                
                // Animation fluide
                textarea.style.transition = 'height 0.2s ease';
            });
        }
        
        /**
         * Redimensionnement fluide d'un textarea
         */
        resizeTextarea(textarea) {
            textarea.style.height = 'auto';
            const newHeight = Math.max(textarea.scrollHeight, 100);
            textarea.style.height = newHeight + 'px';
        }
        
        /**
         * Aide contextuelle à la rédaction
         */
        setupWritingAssistance() {
            const descField = document.querySelector('[name="description"]');
            if (!descField) return;
            
            const suggestions = [
                "🗺️ Décrivez l'emplacement et l'accès général",
                "🪨 Mentionnez le type de roche et les caractéristiques",
                "📅 Indiquez la meilleure période pour grimper",
                "⚠️ Notez les restrictions ou précautions particulières",
                "🚗 Précisez les informations de stationnement",
                "🏕️ Mentionnez les possibilités d'hébergement à proximité"
            ];
            
            const helpElement = document.createElement('div');
            helpElement.className = 'writing-assistance mt-2';
            helpElement.innerHTML = `
                <details class="assistance-panel">
                    <summary class="assistance-trigger">
                        <i class="fas fa-question-circle text-info"></i>
                        <span>Aide à la rédaction</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </summary>
                    <div class="assistance-content">
                        <p class="assistance-intro">Incluez ces éléments pour une description complète :</p>
                        <ul class="assistance-list">
                            ${suggestions.map(tip => `<li>${tip}</li>`).join('')}
                        </ul>
                        <div class="assistance-actions">
                            <button type="button" class="btn-assistance" data-action="template">
                                <i class="fas fa-file-alt"></i> Utiliser un modèle
                            </button>
                        </div>
                    </div>
                </details>
            `;
            
            descField.parentNode.appendChild(helpElement);
            
            // Action du modèle
            const templateBtn = helpElement.querySelector('[data-action="template"]');
            templateBtn.addEventListener('click', () => {
                this.insertDescriptionTemplate(descField);
            });
        }
        
        /**
         * Insertion d'un modèle de description
         */
        insertDescriptionTemplate(field) {
            const template = `📍 Emplacement et accès :


🪨 Type de roche et caractéristiques :


📅 Meilleure période :


⚠️ Restrictions et précautions :


🚗 Stationnement :


ℹ️ Informations complémentaires :

`;
            
            if (field.value.trim() === '') {
                field.value = template;
                field.dispatchEvent(new Event('input'));
                this.resizeTextarea(field);
                ui.toast.success('Modèle de description inséré !');
            } else {
                ui.modal.confirm({
                    title: 'Remplacer le contenu ?',
                    message: 'Voulez-vous remplacer le contenu actuel par le modèle ?',
                    onConfirm: () => {
                        field.value = template;
                        field.dispatchEvent(new Event('input'));
                        this.resizeTextarea(field);
                        ui.toast.success('Modèle de description appliqué !');
                    }
                });
            }
        }
        
        /**
         * Configuration des fonctionnalités avancées
         */
        setupAdvancedFeatures() {
            if (this.isEdit) {
                this.setupMoveModal();
                this.setupDeleteProtection();
                this.setupDuplicationFeature();
            }
            
            this.setupHierarchyPreview();
            this.setupFormPersistence();
        }
        
        /**
         * Prévisualisation hiérarchique en temps réel
         */
        setupHierarchyPreview() {
            const preview = document.querySelector('.hierarchy-preview');
            if (!preview) return;
            
            const nameField = document.querySelector('[name="name"]');
            if (nameField) {
                nameField.addEventListener('input', utils.debounce(() => {
                    this.updateHierarchyPreview();
                }, 300));
            }
        }
        
        /**
         * Mise à jour de l'aperçu hiérarchique
         */
        updateHierarchyPreview() {
            const preview = document.querySelector('.hierarchy-preview');
            const nameField = document.querySelector('[name="name"]');
            
            if (!preview || !nameField) return;
            
            const siteName = nameField.value.trim() || (this.isEdit ? 'Site existant' : 'Nouveau site');
            const siteItem = preview.querySelector('.hierarchy-item.active span');
            
            if (siteItem) {
                siteItem.textContent = siteName;
                
                // Animation de mise à jour
                siteItem.classList.add('updated');
                setTimeout(() => siteItem.classList.remove('updated'), 300);
            }
        }
        
        /**
         * Auto-sauvegarde intelligente
         */
        setupIntelligentAutoSave() {
            const form = document.querySelector('.site-form-content');
            if (!form) return;
            
            let lastSaveData = new FormData(form);
            let autoSaveTimeout;
            let isFormDirty = false;
            
            // Détecter les changements
            form.addEventListener('input', () => {
                isFormDirty = true;
                clearTimeout(autoSaveTimeout);
                
                autoSaveTimeout = setTimeout(async () => {
                    if (isFormDirty) {
                        await this.performAutoSave(form);
                        isFormDirty = false;
                    }
                }, 5000); // Auto-save après 5 secondes d'inactivité
            });
            
            // Sauvegarde avant fermeture
            window.addEventListener('beforeunload', (e) => {
                if (isFormDirty) {
                    e.preventDefault();
                    e.returnValue = 'Des modifications non sauvegardées seront perdues.';
                }
            });
            
            console.log('💾 Intelligent auto-save configured');
        }
        
        /**
         * Exécution de l'auto-sauvegarde
         */
        async performAutoSave(form) {
            const formData = new FormData(form);
            formData.append('auto_save', '1');
            
            try {
                this.showAutoSaveIndicator('saving');
                
                const response = await api.post(form.action, formData, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                if (response.success) {
                    this.showAutoSaveIndicator('saved');
                } else {
                    this.showAutoSaveIndicator('error');
                }
                
            } catch (error) {
                console.error('Auto-save error:', error);
                this.showAutoSaveIndicator('error');
            }
        }
        
        /**
         * Indicateur d'auto-sauvegarde moderne
         */
        showAutoSaveIndicator(status) {
            let indicator = document.querySelector('.auto-save-indicator');
            
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.className = 'auto-save-indicator';
                document.body.appendChild(indicator);
            }
            
            const icons = {
                saving: { icon: 'fa-spinner fa-spin', color: 'info', text: 'Sauvegarde...' },
                saved: { icon: 'fa-check', color: 'success', text: 'Sauvegardé' },
                error: { icon: 'fa-exclamation-triangle', color: 'warning', text: 'Erreur de sauvegarde' }
            };
            
            const config = icons[status];
            indicator.innerHTML = `
                <div class="indicator-content">
                    <i class="fas ${config.icon} text-${config.color}"></i>
                    <span>${config.text}</span>
                </div>
            `;
            
            indicator.className = `auto-save-indicator show ${config.color}`;
            
            if (status !== 'saving') {
                setTimeout(() => {
                    indicator.classList.remove('show');
                }, 3000);
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
         * Raccourcis clavier modernes
         */
        setupKeyboardShortcuts() {
            const shortcuts = {
                'ctrl+s': () => this.handleFormSubmission(document.querySelector('.site-form-content')),
                'ctrl+shift+s': () => this.performAutoSave(document.querySelector('.site-form-content')),
                'escape': () => this.handleCancelAction(),
                'ctrl+z': () => this.undoLastChange(),
                'f1': () => this.showHelpModal()
            };
            
            document.addEventListener('keydown', (e) => {
                const key = this.getShortcutKey(e);
                if (shortcuts[key]) {
                    e.preventDefault();
                    shortcuts[key]();
                }
            });
        }
        
        /**
         * Obtient la combinaison de touches
         */
        getShortcutKey(e) {
            const keys = [];
            if (e.ctrlKey || e.metaKey) keys.push('ctrl');
            if (e.shiftKey) keys.push('shift');
            if (e.altKey) keys.push('alt');
            keys.push(e.key.toLowerCase());
            return keys.join('+');
        }
        
        /**
         * Soumission moderne du formulaire
         */
        async handleFormSubmission(form) {
            try {
                // Validation complète
                const isValid = await this.validateCompleteForm(form);
                if (!isValid) {
                    this.showValidationSummary();
                    return;
                }
                
                // Indicateur de soumission
                this.showSubmissionProgress();
                
                // Préparation des données
                const formData = this.prepareFormData(form);
                
                // Soumission
                const response = await api.post(form.action, formData);
                
                if (response.success) {
                    ui.toast.success('Site sauvegardé avec succès !');
                    
                    // Redirection ou mise à jour
                    if (response.redirect) {
                        window.location.href = response.redirect;
                    } else {
                        this.updateFormAfterSave(response.data);
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
            const fields = form.querySelectorAll('[name]');
            let isValid = true;
            
            for (const field of fields) {
                const fieldName = field.name;
                const rules = this.validationRules[fieldName];
                
                if (rules) {
                    const fieldValid = await this.validateField(field, rules);
                    if (!fieldValid) isValid = false;
                }
            }
            
            return isValid;
        }
        
        /**
         * Affichage du résumé de validation
         */
        showValidationSummary() {
            const errors = document.querySelectorAll('.field-error');
            const errorCount = errors.length;
            
            if (errorCount > 0) {
                ui.toast.error(`${errorCount} erreur(s) de validation détectée(s)`, {
                    duration: 5000,
                    action: {
                        text: 'Voir',
                        handler: () => {
                            errors[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }
                });
            }
        }
        
        /**
         * Mode de secours
         */
        initializeFallback() {
            console.log('🔄 Initializing fallback mode for site form');
            
            // Fonctionnalités de base seulement
            this.setupBasicValidation();
            this.setupBasicKeyboardShortcuts();
            
            ui.toast.warning('Formulaire chargé en mode simplifié', { duration: 5000 });
        }
        
        /**
         * Validation de base pour le mode de secours
         */
        setupBasicValidation() {
            const form = document.querySelector('.site-form-content');
            if (!form) return;
            
            form.addEventListener('submit', (e) => {
                const nameField = form.querySelector('[name="name"]');
                if (!nameField.value.trim()) {
                    e.preventDefault();
                    alert('Le nom du site est requis');
                    nameField.focus();
                }
            });
        }
        
        /**
         * Raccourcis de base pour le mode de secours
         */
        setupBasicKeyboardShortcuts() {
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    document.querySelector('.site-form-content').submit();
                }
            });
        }
        
        /**
         * Nettoyage des ressources
         */
        cleanup() {
            // Nettoyer les timeouts
            if (this.autoSaveInterval) {
                clearInterval(this.autoSaveInterval);
            }
            
            // Nettoyer les événements
            document.removeEventListener('keydown', this.handleKeyboardShortcuts);
            window.removeEventListener('beforeunload', this.handleBeforeUnload);
            
            console.log('🧹 Site form page cleaned up');
        }
    }
    
    return SiteFormPage;
});

// Auto-initialisation
document.addEventListener('DOMContentLoaded', async () => {
    // Vérifier qu'on est sur une page formulaire site
    if (!document.querySelector('.site-form-content') && 
        !window.location.pathname.match(/\/sites\/(create|new|\d+\/edit)/)) {
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
        const SiteFormPage = await TopoclimbCH.modules.load('page-site-form');
        const formPage = new SiteFormPage();
        await formPage.init();
        
        // Nettoyage
        window.addEventListener('beforeunload', () => {
            formPage.cleanup();
        });
        
    } catch (error) {
        console.error('❌ Failed to initialize site form page:', error);
    }
});

console.log('📝 Site Form Page module ready');