/**
 * Script pour le formulaire des secteurs
 */
document.addEventListener('DOMContentLoaded', function () {
    // Initialiser les composants
    initializeComponents();

    // Fonctionnalités spécifiques au formulaire
    initializeFormSpecificFeatures();
});

function initializeComponents() {
    // Initialiser le gestionnaire de médias
    if (typeof MediaManager !== 'undefined') {
        new MediaManager({
            deleteConfirmMessage: 'Êtes-vous sûr de vouloir supprimer cette image ?'
        });
    }

    // Initialiser la carte si l'élément existe
    const mapElement = document.getElementById('map');
    if (mapElement && typeof MapManager !== 'undefined') {
        new MapManager('map', {
            defaultCenter: [46.8, 8.2], // Centre sur la Suisse
            defaultZoom: 8,
            locateZoom: 15
        });
    }
}

function initializeFormSpecificFeatures() {
    // Validation du formulaire
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (!validateSectorForm()) {
                e.preventDefault();
            }
        });
    }

    // Auto-génération du code à partir du nom
    const nameInput = document.getElementById('name');
    const codeInput = document.getElementById('code');

    if (nameInput && codeInput) {
        nameInput.addEventListener('input', function () {
            // Ne générer le code que si le champ code est vide
            if (!codeInput.value.trim()) {
                codeInput.value = generateCodeFromName(this.value);
            }
        });
    }

    // Gestion des expositions
    initializeExposureHandling();

    // Gestion des coordonnées
    initializeCoordinateHandling();
}

function validateSectorForm() {
    const errors = [];

    // Validation des champs obligatoires
    const requiredFields = ['name', 'code', 'book_id'];
    requiredFields.forEach(fieldName => {
        const field = document.getElementById(fieldName);
        if (!field || !field.value.trim()) {
            errors.push(`Le champ "${fieldName}" est obligatoire`);
        }
    });

    // Validation des coordonnées
    const lat = document.getElementById('coordinates_lat');
    const lng = document.getElementById('coordinates_lng');

    if (lat && lat.value) {
        const latValue = parseFloat(lat.value);
        if (isNaN(latValue) || latValue < -90 || latValue > 90) {
            errors.push('La latitude doit être comprise entre -90 et 90');
        }
    }

    if (lng && lng.value) {
        const lngValue = parseFloat(lng.value);
        if (isNaN(lngValue) || lngValue < -180 || lngValue > 180) {
            errors.push('La longitude doit être comprise entre -180 et 180');
        }
    }

    // Validation de l'altitude
    const altitude = document.getElementById('altitude');
    if (altitude && altitude.value) {
        const altValue = parseInt(altitude.value);
        if (isNaN(altValue) || altValue < 0 || altValue > 9000) {
            errors.push('L\'altitude doit être comprise entre 0 et 9000 mètres');
        }
    }

    // Afficher les erreurs
    if (errors.length > 0) {
        alert('Erreurs de validation:\n' + errors.join('\n'));
        return false;
    }

    return true;
}

function generateCodeFromName(name) {
    // Générer un code à partir du nom
    return name
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
        .substring(0, 50);
}

function initializeExposureHandling() {
    // Gérer la sélection de l'exposition principale
    const exposureCheckboxes = document.querySelectorAll('input[name="exposures[]"]');
    const primaryExposureRadios = document.querySelectorAll('input[name="primary_exposure"]');

    exposureCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            const exposureId = this.value;
            const primaryRadio = document.getElementById(`primary_exposure_${exposureId}`);

            if (this.checked) {
                // Activer le radio correspondant
                if (primaryRadio) {
                    primaryRadio.disabled = false;
                }
            } else {
                // Désactiver et décocher le radio correspondant
                if (primaryRadio) {
                    primaryRadio.disabled = true;
                    primaryRadio.checked = false;
                }
            }
        });
    });

    // Initialiser l'état des radios au chargement
    exposureCheckboxes.forEach(checkbox => {
        const exposureId = checkbox.value;
        const primaryRadio = document.getElementById(`primary_exposure_${exposureId}`);

        if (primaryRadio) {
            primaryRadio.disabled = !checkbox.checked;
        }
    });
}

function initializeCoordinateHandling() {
    // Synchronisation des coordonnées
    const latInput = document.getElementById('coordinates_lat');
    const lngInput = document.getElementById('coordinates_lng');

    if (latInput && lngInput) {
        [latInput, lngInput].forEach(input => {
            input.addEventListener('input', function () {
                // Valider le format des coordonnées
                validateCoordinateInput(this);
            });
        });
    }
}

function validateCoordinateInput(input) {
    const value = input.value;

    if (value && isNaN(parseFloat(value))) {
        input.classList.add('is-invalid');

        // Ajouter ou mettre à jour le message d'erreur
        let feedback = input.parentNode.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            input.parentNode.appendChild(feedback);
        }
        feedback.textContent = 'Veuillez entrer une coordonnée valide';
    } else {
        input.classList.remove('is-invalid');

        // Supprimer le message d'erreur
        const feedback = input.parentNode.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.remove();
        }
    }
}