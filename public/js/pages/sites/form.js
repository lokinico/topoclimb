/**
 * Site Form - JavaScript pour le formulaire de création/édition des sites
 */

class SiteForm {
    constructor() {
        this.isEdit = window.location.pathname.includes('/edit');
        this.regionId = this.getRegionId();
        this.siteId = this.getSiteId();
        this.validationRules = this.setupValidationRules();

        this.init();
    }

    init() {
        this.setupFormValidation();
        this.setupFieldEnhancements();
        this.setupHierarchyPreview();
        this.setupAdvancedActions();
        this.setupKeyboardShortcuts();
        this.setupAutoSave();
    }

    /**
     * Extraire l'ID de la région depuis les données du formulaire
     */
    getRegionId() {
        const regionInput = document.querySelector('input[name="region_id"]');
        return regionInput ? parseInt(regionInput.value) : null;
    }

    /**
     * Extraire l'ID du site depuis l'URL (mode édition)
     */
    getSiteId() {
        if (!this.isEdit) return null;

        const matches = window.location.pathname.match(/\/sites\/(\d+)\/edit/);
        return matches ? parseInt(matches[1]) : null;
    }

    /**
     * Configuration des règles de validation
     */
    setupValidationRules() {
        return {
            name: {
                required: true,
                minLength: 2,
                maxLength: 255,
                pattern: null
            },
            code: {
                required: false,
                minLength: 1,
                maxLength: 50,
                pattern: /^[A-Z0-9]+$/i
            },
            description: {
                maxLength: 65535
            },
            year: {
                min: 1900,
                max: new Date().getFullYear() + 5
            },
            publisher: {
                maxLength: 100
            },
            isbn: {
                pattern: /^[\d\-Xx]*$/
            }
        };
    }

    /**
     * Configuration de la validation du formulaire
     */
    setupFormValidation() {
        const form = document.querySelector('.site-form-content');
        if (!form) return;

        // Validation en temps réel
        Object.keys(this.validationRules).forEach(fieldName => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                this.setupFieldValidation(field, this.validationRules[fieldName]);
            }
        });

        // Validation à la soumission
        form.addEventListener('submit', (e) => {
            if (!this.validateForm(form)) {
                e.preventDefault();
                this.showValidationErrors();
            }
        });
    }

    /**
     * Configuration de la validation d'un champ
     */
    setupFieldValidation(field, rules) {
        const fieldGroup = field.closest('.form-group');

        // Validation pendant la frappe
        field.addEventListener('input', () => {
            this.validateField(field, rules);
        });

        // Validation à la perte de focus
        field.addEventListener('blur', () => {
            this.validateField(field, rules);
        });

        // Indication visuelle pendant la frappe
        field.addEventListener('input', () => {
            this.updateFieldVisuals(field);
        });
    }

    /**
     * Valider un champ individuel
     */
    validateField(field, rules) {
        const value = field.value.trim();
        const errors = [];

        // Validation required
        if (rules.required && !value) {
            errors.push('Ce champ est requis');
        }

        // Validation longueur minimum
        if (rules.minLength && value.length > 0 && value.length < rules.minLength) {
            errors.push(`Minimum ${rules.minLength} caractères`);
        }

        // Validation longueur maximum
        if (rules.maxLength && value.length > rules.maxLength) {
            errors.push(`Maximum ${rules.maxLength} caractères`);
        }

        // Validation pattern
        if (rules.pattern && value && !rules.pattern.test(value)) {
            errors.push('Format invalide');
        }

        // Validation numérique
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

        this.displayFieldErrors(field, errors);
        return errors.length === 0;
    }

    /**
     * Afficher les erreurs d'un champ
     */
    displayFieldErrors(field, errors) {
        const fieldGroup = field.closest('.form-group');

        // Supprimer les erreurs existantes
        fieldGroup.querySelectorAll('.field-error').forEach(el => el.remove());

        // État visuel du champ
        if (errors.length > 0) {
            field.classList.add('is-invalid');
            field.classList.remove('is-valid');
        } else if (field.value.trim()) {
            field.classList.add('is-valid');
            field.classList.remove('is-invalid');
        } else {
            field.classList.remove('is-valid', 'is-invalid');
        }

        // Afficher les erreurs
        errors.forEach(error => {
            const errorEl = document.createElement('div');
            errorEl.className = 'field-error text-danger mt-1';
            errorEl.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${error}`;
            fieldGroup.appendChild(errorEl);
        });
    }

    /**
     * Valider tout le formulaire
     */
    validateForm(form) {
        let isValid = true;

        Object.keys(this.validationRules).forEach(fieldName => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                if (!this.validateField(field, this.validationRules[fieldName])) {
                    isValid = false;
                }
            }
        });

        return isValid;
    }

    /**
     * Afficher les erreurs de validation générales
     */
    showValidationErrors() {
        const errorAlert = document.createElement('div');
        errorAlert.className = 'alert alert-danger alert-dismissible fade show';
        errorAlert.innerHTML = `
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Erreurs de validation</strong><br>
            Veuillez corriger les erreurs indiquées ci-dessous.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        const form = document.querySelector('.site-form-content');
        form.insertBefore(errorAlert, form.firstChild);

        // Faire défiler vers la première erreur
        const firstError = form.querySelector('.is-invalid');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
        }
    }

    /**
     * Améliorer visuellement les champs
     */
    updateFieldVisuals(field) {
        const value = field.value.trim();

        // Compteur de caractères pour les champs texte
        if (field.maxLength) {
            this.updateCharacterCounter(field, value.length, field.maxLength);
        }

        // Prévisualisation pour certains champs
        switch (field.name) {
            case 'name':
                this.updateHierarchyPreview();
                break;
            case 'code':
                this.updateCodePreview(field, value);
                break;
        }
    }

    /**
     * Mettre à jour le compteur de caractères
     */
    updateCharacterCounter(field, current, max) {
        let counter = field.parentNode.querySelector('.char-counter');

        if (!counter) {
            counter = document.createElement('div');
            counter.className = 'char-counter text-muted';
            field.parentNode.appendChild(counter);
        }

        const percentage = (current / max) * 100;
        let className = 'text-muted';

        if (percentage >= 90) className = 'text-danger';
        else if (percentage >= 75) className = 'text-warning';
        else if (percentage >= 50) className = 'text-info';

        counter.className = `char-counter ${className}`;
        counter.textContent = `${current}/${max} caractères`;
    }

    /**
     * Prévisualisation du code
     */
    updateCodePreview(field, value) {
        const preview = field.parentNode.querySelector('.code-preview');

        if (value) {
            if (!preview) {
                const previewEl = document.createElement('div');
                previewEl.className = 'code-preview mt-2';
                field.parentNode.appendChild(previewEl);
            }

            const formattedCode = value.toUpperCase();
            field.parentNode.querySelector('.code-preview').innerHTML = `
                <small class="text-info">
                    <i class="fas fa-eye"></i> Aperçu: <strong>${formattedCode}</strong>
                </small>
            `;
        } else if (preview) {
            preview.remove();
        }
    }

    /**
     * Améliorer les champs du formulaire
     */
    setupFieldEnhancements() {
        this.enhanceNameField();
        this.enhanceCodeField();
        this.enhanceDescriptionField();
        this.enhanceYearField();
        this.enhanceIsbnField();
    }

    /**
     * Améliorer le champ nom
     */
    enhanceNameField() {
        const nameField = document.querySelector('[name="name"]');
        if (!nameField) return;

        // Suggestion automatique du code
        nameField.addEventListener('input', () => {
            const codeField = document.querySelector('[name="code"]');
            if (codeField && !codeField.value.trim()) {
                this.suggestCode(nameField.value);
            }
        });

        // Validation de caractères spéciaux
        nameField.addEventListener('input', (e) => {
            const value = e.target.value;
            if (value.length > 0 && !/^[a-zA-ZÀ-ÿ0-9\s\-'\.]+$/.test(value)) {
                this.showFieldWarning(nameField, 'Évitez les caractères spéciaux');
            }
        });
    }

    /**
     * Suggérer un code basé sur le nom
     */
    suggestCode(name) {
        const codeField = document.querySelector('[name="code"]');
        if (!codeField || codeField.value.trim()) return;

        // Générer un code suggéré
        const suggested = name
            .replace(/[^a-zA-Z0-9\s]/g, '')
            .split(' ')
            .map(word => word.substring(0, 3))
            .join('')
            .toUpperCase()
            .substring(0, 6);

        if (suggested.length >= 2) {
            this.showCodeSuggestion(codeField, suggested);
        }
    }

    /**
     * Afficher une suggestion de code
     */
    showCodeSuggestion(field, suggested) {
        let suggestion = field.parentNode.querySelector('.code-suggestion');

        if (!suggestion) {
            suggestion = document.createElement('div');
            suggestion.className = 'code-suggestion mt-2';
            field.parentNode.appendChild(suggestion);
        }

        suggestion.innerHTML = `
            <small class="text-info">
                <i class="fas fa-lightbulb"></i> 
                Suggestion: <button type="button" class="btn btn-link btn-sm p-0" onclick="this.closest('.form-group').querySelector('input').value='${suggested}'; this.closest('.code-suggestion').remove();">${suggested}</button>
            </small>
        `;
    }

    /**
     * Améliorer le champ code
     */
    enhanceCodeField() {
        const codeField = document.querySelector('[name="code"]');
        if (!codeField) return;

        // Formatage automatique en majuscules
        codeField.addEventListener('input', (e) => {
            const value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (value !== e.target.value) {
                e.target.value = value;
            }
        });

        // Vérification de l'unicité (si édition)
        if (this.isEdit) {
            codeField.addEventListener('blur', () => {
                this.checkCodeUniqueness(codeField.value);
            });
        }
    }

    /**
     * Vérifier l'unicité du code
     */
    async checkCodeUniqueness(code) {
        if (!code.trim()) return;

        try {
            const response = await fetch(`/api/sites/check-code?code=${encodeURIComponent(code)}&site_id=${this.siteId || ''}`);
            const data = await response.json();

            const codeField = document.querySelector('[name="code"]');

            if (data.exists) {
                this.showFieldWarning(codeField, 'Ce code est déjà utilisé');
                codeField.classList.add('is-invalid');
            } else {
                this.removeFieldWarning(codeField);
                if (code.trim()) {
                    codeField.classList.add('is-valid');
                }
            }
        } catch (error) {
            console.error('Erreur vérification code:', error);
        }
    }

    /**
     * Améliorer le champ description
     */
    enhanceDescriptionField() {
        const descField = document.querySelector('[name="description"]');
        if (!descField) return;

        // Auto-resize du textarea
        descField.addEventListener('input', () => {
            descField.style.height = 'auto';
            descField.style.height = descField.scrollHeight + 'px';
        });

        // Aide à la rédaction
        this.addWritingAssistance(descField);
    }

    /**
     * Ajouter une aide à la rédaction
     */
    addWritingAssistance(field) {
        const suggestions = [
            "Décrivez l'emplacement et l'accès général",
            "Mentionnez le type de roche et les caractéristiques",
            "Indiquez la meilleure période pour grimper",
            "Notez les restrictions ou précautions particulières"
        ];

        const helpEl = document.createElement('div');
        helpEl.className = 'writing-help mt-2';
        helpEl.innerHTML = `
            <details>
                <summary class="text-info"><i class="fas fa-question-circle"></i> Aide à la rédaction</summary>
                <ul class="mt-2 mb-0">
                    ${suggestions.map(tip => `<li>${tip}</li>`).join('')}
                </ul>
            </details>
        `;

        field.parentNode.appendChild(helpEl);
    }

    /**
     * Améliorer le champ année
     */
    enhanceYearField() {
        const yearField = document.querySelector('[name="year"]');
        if (!yearField) return;

        // Suggestion année courante
        const currentYear = new Date().getFullYear();

        yearField.addEventListener('focus', () => {
            if (!yearField.value) {
                this.showFieldSuggestion(yearField, `Suggestion: ${currentYear}`);
            }
        });
    }

    /**
     * Améliorer le champ ISBN
     */
    enhanceIsbnField() {
        const isbnField = document.querySelector('[name="isbn"]');
        if (!isbnField) return;

        // Formatage automatique de l'ISBN
        isbnField.addEventListener('input', (e) => {
            let value = e.target.value.replace(/[^\dXx\-]/g, '');

            // Format ISBN-13: 978-3-xxx-xxxxx-x
            if (value.length > 3 && !value.includes('-')) {
                if (value.startsWith('978') || value.startsWith('979')) {
                    value = value.replace(/(\d{3})(\d{1})(\d{3})(\d{5})(\d{1})/, '$1-$2-$3-$4-$5');
                }
            }

            if (value !== e.target.value) {
                e.target.value = value;
            }
        });

        // Validation ISBN
        isbnField.addEventListener('blur', () => {
            this.validateIsbn(isbnField.value);
        });
    }

    /**
     * Valider un ISBN
     */
    validateIsbn(isbn) {
        const isbnField = document.querySelector('[name="isbn"]');

        if (!isbn.trim()) {
            this.removeFieldWarning(isbnField);
            return;
        }

        const cleanIsbn = isbn.replace(/[\-\s]/g, '');

        if (cleanIsbn.length === 13 && /^\d{12}[\dXx]$/.test(cleanIsbn)) {
            this.showFieldSuccess(isbnField, 'ISBN-13 valide');
        } else if (cleanIsbn.length === 10 && /^\d{9}[\dXx]$/.test(cleanIsbn)) {
            this.showFieldSuccess(isbnField, 'ISBN-10 valide');
        } else if (cleanIsbn.length > 0) {
            this.showFieldWarning(isbnField, 'Format ISBN invalide');
        }
    }

    /**
     * Mettre à jour l'aperçu hiérarchique
     */
    setupHierarchyPreview() {
        const preview = document.querySelector('.hierarchy-preview');
        if (!preview) return;

        // Mettre à jour en temps réel
        const nameField = document.querySelector('[name="name"]');
        if (nameField) {
            nameField.addEventListener('input', () => {
                this.updateHierarchyPreview();
            });
        }
    }

    updateHierarchyPreview() {
        const preview = document.querySelector('.hierarchy-preview');
        const nameField = document.querySelector('[name="name"]');

        if (!preview || !nameField) return;

        const siteName = nameField.value.trim() || (this.isEdit ? 'Site existant' : 'Nouveau site');
        const siteItem = preview.querySelector('.hierarchy-item.active span');

        if (siteItem) {
            siteItem.textContent = siteName;
        }
    }

    /**
     * Configuration des actions avancées (mode édition)
     */
    setupAdvancedActions() {
        if (!this.isEdit) return;

        this.setupMoveModal();
        this.setupDeleteProtection();
    }

    /**
     * Configuration du modal de déplacement
     */
    setupMoveModal() {
        const moveModal = document.getElementById('moveSectorsModal');
        if (!moveModal) return;

        // Charger les sites disponibles quand le modal s'ouvre
        moveModal.addEventListener('show.bs.modal', () => {
            this.loadAvailableSites();
        });

        // Gérer le changement de type de déplacement
        const radioButtons = moveModal.querySelectorAll('input[name="move_type"]');
        radioButtons.forEach(radio => {
            radio.addEventListener('change', () => {
                this.toggleSiteSelection(radio.value === 'site');
            });
        });
    }

    /**
     * Charger les sites disponibles
     */
    async loadAvailableSites() {
        try {
            const response = await fetch(`/api/sites?region_id=${this.regionId}&exclude=${this.siteId}`);
            const sites = await response.json();

            const select = document.getElementById('target_site');
            select.innerHTML = '<option value="">Choisir un site...</option>';

            sites.forEach(site => {
                select.innerHTML += `<option value="${site.id}">${site.name} (${site.code})</option>`;
            });

        } catch (error) {
            console.error('Erreur chargement sites:', error);
        }
    }

    /**
     * Afficher/masquer la sélection de site
     */
    toggleSiteSelection(show) {
        const siteSelection = document.getElementById('site_selection');
        if (siteSelection) {
            siteSelection.style.display = show ? 'block' : 'none';
        }
    }

    /**
     * Protection contre la suppression accidentelle
     */
    setupDeleteProtection() {
        const deleteLinks = document.querySelectorAll('a[href*="/delete"], button[data-action="delete"]');

        deleteLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.confirmDeletion(link);
            });
        });
    }

    /**
     * Confirmer la suppression
     */
    confirmDeletion(element) {
        const siteName = document.querySelector('[name="name"]').value || 'ce site';

        if (confirm(`Êtes-vous vraiment sûr de vouloir supprimer ${siteName} ?\n\nCette action ne peut pas être annulée.`)) {
            if (confirm('Confirmation finale : tous les secteurs seront déplacés vers la région. Continuer ?')) {
                // Procéder à la suppression
                if (element.href) {
                    window.location.href = element.href;
                } else {
                    element.form?.submit();
                }
            }
        }
    }

    /**
     * Raccourcis clavier
     */
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            switch (e.key) {
                case 's':
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        document.querySelector('.site-form-content').submit();
                    }
                    break;

                case 'Escape':
                    const cancelBtn = document.querySelector('a[href*="cancel"], .btn-outline-secondary');
                    if (cancelBtn) cancelBtn.click();
                    break;
            }
        });
    }

    /**
     * Auto-sauvegarde (brouillon)
     */
    setupAutoSave() {
        if (!this.isEdit) return; // Seulement en mode édition

        const form = document.querySelector('.site-form-content');
        const fields = form.querySelectorAll('input, textarea, select');

        let autoSaveTimeout;

        fields.forEach(field => {
            field.addEventListener('input', () => {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(() => {
                    this.autoSave();
                }, 2000); // 2 secondes après la dernière modification
            });
        });
    }

    /**
     * Sauvegarde automatique
     */
    async autoSave() {
        const form = document.querySelector('.site-form-content');
        const formData = new FormData(form);
        formData.append('auto_save', '1');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                this.showAutoSaveIndicator();
            }
        } catch (error) {
            console.error('Erreur auto-sauvegarde:', error);
        }
    }

    /**
     * Indicateur de sauvegarde automatique
     */
    showAutoSaveIndicator() {
        let indicator = document.querySelector('.auto-save-indicator');

        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'auto-save-indicator';
            document.body.appendChild(indicator);
        }

        indicator.innerHTML = `
            <i class="fas fa-save text-success"></i>
            <span>Sauvegardé automatiquement</span>
        `;

        indicator.classList.add('show');

        setTimeout(() => {
            indicator.classList.remove('show');
        }, 2000);
    }

    /**
     * Utilitaires pour les messages de champ
     */
    showFieldWarning(field, message) {
        this.removeFieldMessage(field);
        const warning = document.createElement('div');
        warning.className = 'field-message text-warning mt-1';
        warning.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
        field.parentNode.appendChild(warning);
    }

    showFieldSuccess(field, message) {
        this.removeFieldMessage(field);
        const success = document.createElement('div');
        success.className = 'field-message text-success mt-1';
        success.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
        field.parentNode.appendChild(success);
    }

    showFieldSuggestion(field, message) {
        this.removeFieldMessage(field);
        const suggestion = document.createElement('div');
        suggestion.className = 'field-message text-info mt-1';
        suggestion.innerHTML = `<i class="fas fa-lightbulb"></i> ${message}`;
        field.parentNode.appendChild(suggestion);
    }

    removeFieldWarning(field) {
        this.removeFieldMessage(field);
    }

    removeFieldMessage(field) {
        field.parentNode.querySelectorAll('.field-message').forEach(el => el.remove());
    }
}

// Initialiser quand le DOM est prêt
document.addEventListener('DOMContentLoaded', () => {
    new SiteForm();
});

// CSS additionnel pour les améliorations
const additionalCSS = `
    .field-error {
        font-size: 12px;
        font-weight: 600;
    }
    
    .field-message {
        font-size: 12px;
        font-weight: 600;
    }
    
    .char-counter {
        font-size: 11px;
        text-align: right;
        margin-top: 4px;
    }
    
    .writing-help details {
        cursor: pointer;
    }
    
    .writing-help summary {
        font-size: 13px;
        padding: 8px 0;
    }
    
    .writing-help ul {
        font-size: 12px;
        padding-left: 20px;
    }
    
    .auto-save-indicator {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        border: 1px solid var(--success-color, #28a745);
        border-radius: 6px;
        padding: 10px 15px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1000;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
    }
    
    .auto-save-indicator.show {
        transform: translateX(0);
    }
    
    .form-control.is-valid {
        border-color: var(--success-color, #28a745);
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='m2.3 6.73.7-.04 1.05-1.05 1.05 1.05.7.04L8.5 4.07l-.04-.7L7.41 2.32 8.46 1.27l.04-.7L6.82-.09l-.7.04L5.07 1 4.02-.04l-.7.04L1.64 1.65l.04.7L2.73 3.4 1.68 4.45l-.04.7L3.3 6.73z'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(.375em + .1875rem) center;
        background-size: calc(.75em + .375rem) calc(.75em + .375rem);
    }
    
    .form-control.is-invalid {
        border-color: var(--danger-color, #dc3545);
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 4.6 0 2.8m0 1.8h.1'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(.375em + .1875rem) center;
        background-size: calc(.75em + .375rem) calc(.75em + .375rem);
    }
`;

// Injecter le CSS additionnel
if (!document.querySelector('#site-form-enhancement-css')) {
    const style = document.createElement('style');
    style.id = 'site-form-enhancement-css';
    style.textContent = additionalCSS;
    document.head.appendChild(style);
}