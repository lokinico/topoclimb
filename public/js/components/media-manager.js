/**
 * Media Manager Component
 * Gère l'affichage, la modification et la suppression des médias
 */
class MediaManager {
    constructor(options = {}) {
        this.options = {
            deleteConfirmMessage: 'Êtes-vous sûr de vouloir supprimer cette image ?',
            ...options
        };

        this.init();
    }

    init() {
        this.bindDeleteButtons();
        this.bindEditModals();
        this.bindFileInputs();
    }

    /**
     * Gestion des boutons de suppression
     */
    bindDeleteButtons() {
        document.querySelectorAll('.media-delete-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleDelete(button);
            });
        });
    }

    /**
     * Gestion des modales d'édition
     */
    bindEditModals() {
        // Bootstrap modal events
        const editModal = document.getElementById('editMediaModal');
        if (editModal) {
            $(editModal).on('show.bs.modal', (event) => {
                this.setupEditModal(event);
            });
        }
    }

    /**
     * Gestion des inputs file
     */
    bindFileInputs() {
        // Afficher le nom du fichier sélectionné
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', (e) => {
                this.updateFileLabel(e.target);
            });
        });
    }

    /**
     * Suppression d'un média
     */
    handleDelete(button) {
        if (confirm(this.options.deleteConfirmMessage)) {
            const mediaId = button.dataset.mediaId;
            const csrfToken = button.dataset.csrfToken;

            if (!mediaId || !csrfToken) {
                console.error('Media ID or CSRF token missing');
                return;
            }

            const deleteUrl = `/media/${mediaId}/delete?csrf_token=${csrfToken}`;
            window.location.href = deleteUrl;
        }
    }

    /**
     * Configuration de la modale d'édition
     */
    setupEditModal(event) {
        const button = $(event.relatedTarget);
        const mediaId = button.data('media-id');
        const mediaTitle = button.data('media-title');
        const relationshipType = button.data('relationship-type');

        const modal = $(event.currentTarget);
        modal.find('#edit_media_id').val(mediaId);
        modal.find('#edit_media_title').val(mediaTitle);

        // Sélectionner le type approprié
        modal.find(`#edit_media_type_${relationshipType}`).prop('checked', true);

        // Définir l'action du formulaire
        const formAction = `/media/${mediaId}/update`;
        modal.find('#editMediaForm').attr('action', formAction);
    }

    /**
     * Mise à jour du label du fichier
     */
    updateFileLabel(input) {
        const fileName = input.files[0]?.name || 'Choisir un fichier';
        const label = input.nextElementSibling;
        if (label && label.classList.contains('custom-file-label')) {
            label.textContent = fileName;
        }
    }

    /**
     * Validation des fichiers
     */
    validateFile(file, options = {}) {
        const defaultOptions = {
            maxSize: 5 * 1024 * 1024, // 5MB
            allowedTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
        };

        const settings = { ...defaultOptions, ...options };

        if (file.size > settings.maxSize) {
            throw new Error(`Le fichier est trop volumineux. Taille maximale: ${settings.maxSize / 1024 / 1024}MB`);
        }

        if (!settings.allowedTypes.includes(file.type)) {
            throw new Error(`Type de fichier non autorisé. Types acceptés: ${settings.allowedTypes.join(', ')}`);
        }

        return true;
    }

    /**
     * Prévisualisation d'image
     */
    previewImage(input, previewElement) {
        if (input.files && input.files[0]) {
            try {
                this.validateFile(input.files[0]);

                const reader = new FileReader();
                reader.onload = function (e) {
                    previewElement.src = e.target.result;
                    previewElement.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            } catch (error) {
                alert(error.message);
                input.value = '';
            }
        }
    }
}

// Export pour utilisation dans d'autres scripts
window.MediaManager = MediaManager;