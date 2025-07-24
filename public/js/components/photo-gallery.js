/**
 * Photo Gallery Component - Gestionnaire de galerie photos moderne
 * Composant réutilisable pour l'affichage et la gestion des galeries
 */

TopoclimbCH.modules.register('photo-gallery', ['utils', 'ui'], async (utils, ui) => {
    
    class PhotoGalleryManager {
        constructor(container, options = {}) {
            this.container = typeof container === 'string' ? document.getElementById(container) : container;
            this.options = {
                // Configuration par défaut
                enableLightbox: true,
                enableLazyLoading: true,
                enableInfiniteScroll: false,
                enableUpload: false,
                enableSorting: false,
                enableDelete: false,
                
                // Layout
                layout: 'grid', // 'grid', 'masonry', 'carousel'
                columns: 'auto', // 'auto', 1, 2, 3, 4
                gap: 12,
                aspectRatio: 'auto', // 'auto', '16:9', '4:3', '1:1'
                
                // Lightbox
                lightboxTheme: 'dark',
                showCaptions: true,
                showDownload: true,
                showShare: true,
                
                // Upload (si activé)
                maxFiles: 20,
                maxFileSize: 10 * 1024 * 1024, // 10MB
                allowedTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
                
                // API
                apiEndpoint: null,
                uploadEndpoint: null,
                deleteEndpoint: null,
                
                // Callbacks
                onPhotoClick: null,
                onPhotoLoad: null,
                onUploadSuccess: null,
                onDeleteSuccess: null,
                onError: null,
                
                ...options
            };
            
            this.photos = [];
            this.currentIndex = 0;
            this.lightbox = null;
            this.isLoading = false;
            this.observer = null;
            this.sortable = null;
            this.initialized = false;
        }
        
        /**
         * Initialise la galerie
         */
        async init() {
            if (this.initialized) {
                console.warn('Photo gallery already initialized');
                return;
            }
            
            if (!this.container) {
                throw new Error('Gallery container not found');
            }
            
            try {
                console.log('📸 Initializing photo gallery');
                
                // Structure HTML de base
                this.setupGalleryStructure();
                
                // Configuration des fonctionnalités
                await this.setupFeatures();
                
                // Chargement des photos
                await this.loadPhotos();
                
                this.initialized = true;
                console.log('✅ Photo gallery initialized successfully');
                
            } catch (error) {
                console.error('❌ Failed to initialize photo gallery:', error);
                if (this.options.onError) {
                    this.options.onError(error);
                }
                throw error;
            }
        }
        
        /**
         * Configuration de la structure HTML
         */
        setupGalleryStructure() {
            this.container.className = `photo-gallery ${this.options.layout}-layout`;
            
            // Conteneur principal
            this.container.innerHTML = `
                <div class="gallery-header">
                    ${this.options.enableUpload ? this.getUploadButtonHTML() : ''}
                    <div class="gallery-controls">
                        ${this.getControlsHTML()}
                    </div>
                </div>
                <div class="gallery-content">
                    <div class="gallery-grid" style="--columns: ${this.options.columns}; --gap: ${this.options.gap}px;">
                        ${this.getLoadingHTML()}
                    </div>
                </div>
                <div class="gallery-footer">
                    <div class="gallery-stats"></div>
                    ${this.options.enableInfiniteScroll ? '<div class="loading-more"></div>' : ''}
                </div>
            `;
            
            // Références aux éléments
            this.elements = {
                header: this.container.querySelector('.gallery-header'),
                controls: this.container.querySelector('.gallery-controls'),
                content: this.container.querySelector('.gallery-content'),
                grid: this.container.querySelector('.gallery-grid'),
                footer: this.container.querySelector('.gallery-footer'),
                stats: this.container.querySelector('.gallery-stats'),
                loadingMore: this.container.querySelector('.loading-more')
            };
        }
        
        /**
         * HTML du bouton d'upload
         */
        getUploadButtonHTML() {
            return `
                <div class="upload-section">
                    <input type="file" id="gallery-upload" multiple accept="${this.options.allowedTypes.map(t => '.' + t.split('/')[1]).join(',')}" style="display: none;">
                    <button type="button" class="btn btn-primary upload-btn" data-action="upload">
                        <i class="fas fa-plus"></i>
                        <span>Ajouter des photos</span>
                    </button>
                    <div class="upload-progress" style="display: none;">
                        <div class="progress">
                            <div class="progress-bar" role="progressbar"></div>
                        </div>
                        <small class="upload-status"></small>
                    </div>
                </div>
            `;
        }
        
        /**
         * HTML des contrôles
         */
        getControlsHTML() {
            return `
                <div class="view-controls">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary ${this.options.layout === 'grid' ? 'active' : ''}" data-layout="grid" title="Grille">
                            <i class="fas fa-th"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary ${this.options.layout === 'masonry' ? 'active' : ''}" data-layout="masonry" title="Mosaïque">
                            <i class="fas fa-th-large"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary ${this.options.layout === 'carousel' ? 'active' : ''}" data-layout="carousel" title="Carrousel">
                            <i class="fas fa-images"></i>
                        </button>
                    </div>
                    <div class="sort-controls ms-2">
                        <select class="form-select form-select-sm" data-sort>
                            <option value="date_desc">Plus récent</option>
                            <option value="date_asc">Plus ancien</option>
                            <option value="name_asc">Nom A-Z</option>
                            <option value="name_desc">Nom Z-A</option>
                            <option value="size_desc">Plus volumineux</option>
                            <option value="size_asc">Plus léger</option>
                        </select>
                    </div>
                </div>
            `;
        }
        
        /**
         * HTML de chargement
         */
        getLoadingHTML() {
            return `
                <div class="gallery-loading">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Chargement des photos...</p>
                    </div>
                </div>
            `;
        }
        
        /**
         * Configuration des fonctionnalités
         */
        async setupFeatures() {
            // Événements des contrôles
            this.setupControlEvents();
            
            // Upload si activé
            if (this.options.enableUpload) {
                this.setupUploadFeature();
            }
            
            // Lazy loading si activé
            if (this.options.enableLazyLoading) {
                this.setupLazyLoading();
            }
            
            // Scroll infini si activé
            if (this.options.enableInfiniteScroll) {
                this.setupInfiniteScroll();
            }
            
            // Tri si activé
            if (this.options.enableSorting) {
                this.setupSortingFeature();
            }
            
            // Lightbox si activé
            if (this.options.enableLightbox) {
                this.setupLightbox();
            }
        }
        
        /**
         * Configuration des événements de contrôle
         */
        setupControlEvents() {
            // Changement de layout
            this.elements.controls.addEventListener('click', (e) => {
                const layoutBtn = e.target.closest('[data-layout]');
                if (layoutBtn) {
                    this.changeLayout(layoutBtn.dataset.layout);
                }
            });
            
            // Changement de tri
            const sortSelect = this.elements.controls.querySelector('[data-sort]');
            if (sortSelect) {
                sortSelect.addEventListener('change', (e) => {
                    this.sortPhotos(e.target.value);
                });
            }
        }
        
        /**
         * Configuration de l'upload
         */
        setupUploadFeature() {
            const uploadBtn = this.container.querySelector('.upload-btn');
            const uploadInput = this.container.querySelector('#gallery-upload');
            
            if (uploadBtn && uploadInput) {
                uploadBtn.addEventListener('click', () => {
                    uploadInput.click();
                });
                
                uploadInput.addEventListener('change', (e) => {
                    this.handleFileUpload(Array.from(e.target.files));
                });
            }
        }
        
        /**
         * Configuration du lazy loading
         */
        setupLazyLoading() {
            if ('IntersectionObserver' in window) {
                this.observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            this.loadLazyImage(entry.target);
                            this.observer.unobserve(entry.target);
                        }
                    });
                }, {
                    rootMargin: '50px 0px',
                    threshold: 0.01
                });
            }
        }
        
        /**
         * Configuration du lightbox
         */
        setupLightbox() {
            // Créer le lightbox modal
            this.createLightboxModal();
            
            // Événement de clic sur les photos
            this.elements.grid.addEventListener('click', (e) => {
                const photoItem = e.target.closest('.photo-item');
                if (photoItem && !e.target.closest('.photo-actions')) {
                    const index = parseInt(photoItem.dataset.index);
                    this.openLightbox(index);
                }
            });
        }
        
        /**
         * Chargement des photos
         */
        async loadPhotos() {
            if (this.options.apiEndpoint) {
                await this.loadFromAPI();
            } else {
                this.loadFromDOM();
            }
            
            this.renderPhotos();
            this.updateStats();
        }
        
        /**
         * Chargement depuis l'API
         */
        async loadFromAPI() {
            try {
                this.isLoading = true;
                this.showLoading();
                
                const response = await fetch(this.options.apiEndpoint);
                const data = await response.json();
                
                this.photos = data.photos || data || [];
                
            } catch (error) {
                console.error('Failed to load photos from API:', error);
                throw new Error('Impossible de charger les photos');
            } finally {
                this.isLoading = false;
                this.hideLoading();
            }
        }
        
        /**
         * Chargement depuis le DOM
         */
        loadFromDOM() {
            const existingImages = this.container.querySelectorAll('img[src], img[data-src]');
            
            this.photos = Array.from(existingImages).map((img, index) => ({
                id: img.dataset.id || index,
                src: img.src || img.dataset.src,
                thumb: img.dataset.thumb || img.src || img.dataset.src,
                alt: img.alt || '',
                title: img.title || '',
                caption: img.dataset.caption || '',
                width: parseInt(img.dataset.width) || null,
                height: parseInt(img.dataset.height) || null,
                size: parseInt(img.dataset.size) || null,
                date: img.dataset.date || new Date().toISOString()
            }));
        }
        
        /**
         * Rendu des photos
         */
        renderPhotos() {
            if (this.photos.length === 0) {
                this.showEmptyState();
                return;
            }
            
            const photosHTML = this.photos.map((photo, index) => 
                this.getPhotoHTML(photo, index)
            ).join('');
            
            this.elements.grid.innerHTML = photosHTML;
            
            // Configuration du lazy loading
            if (this.options.enableLazyLoading && this.observer) {
                this.elements.grid.querySelectorAll('.lazy-image').forEach(img => {
                    this.observer.observe(img);
                });
            }
            
            // Animation d'apparition
            this.animatePhotos();
        }
        
        /**
         * HTML d'une photo
         */
        getPhotoHTML(photo, index) {
            const aspectRatioStyle = this.options.aspectRatio !== 'auto' 
                ? `aspect-ratio: ${this.options.aspectRatio.replace(':', '/')};` 
                : '';
            
            return `
                <div class="photo-item" data-index="${index}" data-id="${photo.id}">
                    <div class="photo-container" style="${aspectRatioStyle}">
                        <img class="${this.options.enableLazyLoading ? 'lazy-image' : ''}" 
                             ${this.options.enableLazyLoading ? `data-src="${photo.thumb}"` : `src="${photo.thumb}"`}
                             alt="${utils.escapeHtml(photo.alt)}"
                             title="${utils.escapeHtml(photo.title)}"
                             loading="lazy">
                        <div class="photo-overlay">
                            <div class="photo-info">
                                ${photo.title ? `<h6 class="photo-title">${utils.escapeHtml(photo.title)}</h6>` : ''}
                                ${photo.caption ? `<p class="photo-caption">${utils.escapeHtml(photo.caption)}</p>` : ''}
                            </div>
                            <div class="photo-actions">
                                ${this.options.enableLightbox ? '<button class="btn btn-sm btn-light view-btn" title="Voir"><i class="fas fa-search-plus"></i></button>' : ''}
                                ${this.options.showDownload ? '<button class="btn btn-sm btn-light download-btn" title="Télécharger"><i class="fas fa-download"></i></button>' : ''}
                                ${this.options.showShare ? '<button class="btn btn-sm btn-light share-btn" title="Partager"><i class="fas fa-share"></i></button>' : ''}
                                ${this.options.enableDelete ? '<button class="btn btn-sm btn-danger delete-btn" title="Supprimer"><i class="fas fa-trash"></i></button>' : ''}
                            </div>
                        </div>
                        <div class="photo-loading">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                    </div>
                </div>
            `;
        }
        
        /**
         * Chargement d'une image lazy
         */
        loadLazyImage(img) {
            const photoContainer = img.closest('.photo-container');
            
            img.addEventListener('load', () => {
                img.classList.add('loaded');
                photoContainer.classList.add('loaded');
                
                if (this.options.onPhotoLoad) {
                    this.options.onPhotoLoad(img, this.photos[parseInt(img.closest('.photo-item').dataset.index)]);
                }
            });
            
            img.addEventListener('error', () => {
                img.classList.add('error');
                photoContainer.classList.add('error');
            });
            
            img.src = img.dataset.src;
        }
        
        /**
         * Animation des photos
         */
        animatePhotos() {
            const photos = this.elements.grid.querySelectorAll('.photo-item');
            
            photos.forEach((photo, index) => {
                photo.style.animationDelay = `${index * 0.05}s`;
                photo.classList.add('animate-in');
            });
        }
        
        /**
         * Changement de layout
         */
        changeLayout(layout) {
            this.options.layout = layout;
            this.container.className = `photo-gallery ${layout}-layout`;
            
            // Mise à jour des boutons actifs
            this.elements.controls.querySelectorAll('[data-layout]').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.layout === layout);
            });
            
            // Re-rendu si nécessaire
            if (layout === 'masonry') {
                this.initMasonry();
            } else if (layout === 'carousel') {
                this.initCarousel();
            }
        }
        
        /**
         * Tri des photos
         */
        sortPhotos(criteria) {
            const [field, direction] = criteria.split('_');
            
            this.photos.sort((a, b) => {
                let valueA = a[field];
                let valueB = b[field];
                
                // Traitement spécial selon le champ
                if (field === 'date') {
                    valueA = new Date(valueA);
                    valueB = new Date(valueB);
                } else if (field === 'name') {
                    valueA = (a.title || a.alt || '').toLowerCase();
                    valueB = (b.title || b.alt || '').toLowerCase();
                }
                
                const result = valueA < valueB ? -1 : valueA > valueB ? 1 : 0;
                return direction === 'desc' ? -result : result;
            });
            
            this.renderPhotos();
        }
        
        /**
         * Ouverture du lightbox
         */
        openLightbox(index) {
            this.currentIndex = index;
            const photo = this.photos[index];
            
            if (!photo) return;
            
            // Mise à jour du contenu
            this.updateLightboxContent(photo);
            
            // Affichage
            const modal = new bootstrap.Modal(this.lightbox);
            modal.show();
            
            // Callbacks
            if (this.options.onPhotoClick) {
                this.options.onPhotoClick(photo, index);
            }
        }
        
        /**
         * Création du modal lightbox
         */
        createLightboxModal() {
            this.lightbox = document.createElement('div');
            this.lightbox.className = 'modal fade photo-lightbox';
            this.lightbox.innerHTML = `
                <div class="modal-dialog modal-xl modal-fullscreen-lg-down">
                    <div class="modal-content bg-dark">
                        <div class="modal-header border-0">
                            <h5 class="modal-title text-white"></h5>
                            <div class="lightbox-actions">
                                ${this.options.showDownload ? '<button class="btn btn-sm btn-outline-light me-2" data-action="download"><i class="fas fa-download"></i></button>' : ''}
                                ${this.options.showShare ? '<button class="btn btn-sm btn-outline-light me-2" data-action="share"><i class="fas fa-share"></i></button>' : ''}
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                        </div>
                        <div class="modal-body p-0 d-flex align-items-center justify-content-center">
                            <div class="lightbox-image-container">
                                <img class="lightbox-image" src="" alt="">
                                <div class="lightbox-loading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                </div>
                            </div>
                            <button class="lightbox-nav lightbox-prev" data-action="prev">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="lightbox-nav lightbox-next" data-action="next">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        <div class="modal-footer border-0">
                            <div class="lightbox-caption text-white"></div>
                            <div class="lightbox-counter text-muted"></div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(this.lightbox);
            
            // Événements de navigation
            this.lightbox.addEventListener('click', (e) => {
                const action = e.target.closest('[data-action]')?.dataset.action;
                if (action) {
                    this.handleLightboxAction(action);
                }
            });
            
            // Navigation clavier
            this.lightbox.addEventListener('keydown', (e) => {
                switch (e.key) {
                    case 'ArrowLeft':
                        this.handleLightboxAction('prev');
                        break;
                    case 'ArrowRight':
                        this.handleLightboxAction('next');
                        break;
                    case 'Escape':
                        bootstrap.Modal.getInstance(this.lightbox).hide();
                        break;
                }
            });
        }
        
        /**
         * Mise à jour du contenu lightbox
         */
        updateLightboxContent(photo) {
            const title = this.lightbox.querySelector('.modal-title');
            const image = this.lightbox.querySelector('.lightbox-image');
            const caption = this.lightbox.querySelector('.lightbox-caption');
            const counter = this.lightbox.querySelector('.lightbox-counter');
            
            title.textContent = photo.title || photo.alt || '';
            image.src = photo.src;
            image.alt = photo.alt || '';
            caption.textContent = photo.caption || '';
            counter.textContent = `${this.currentIndex + 1} / ${this.photos.length}`;
            
            // Affichage/masquage des boutons de navigation
            const prevBtn = this.lightbox.querySelector('.lightbox-prev');
            const nextBtn = this.lightbox.querySelector('.lightbox-next');
            
            prevBtn.style.display = this.currentIndex > 0 ? 'block' : 'none';
            nextBtn.style.display = this.currentIndex < this.photos.length - 1 ? 'block' : 'none';
        }
        
        /**
         * Gestion des actions lightbox
         */
        handleLightboxAction(action) {
            switch (action) {
                case 'prev':
                    if (this.currentIndex > 0) {
                        this.currentIndex--;
                        this.updateLightboxContent(this.photos[this.currentIndex]);
                    }
                    break;
                    
                case 'next':
                    if (this.currentIndex < this.photos.length - 1) {
                        this.currentIndex++;
                        this.updateLightboxContent(this.photos[this.currentIndex]);
                    }
                    break;
                    
                case 'download':
                    this.downloadPhoto(this.photos[this.currentIndex]);
                    break;
                    
                case 'share':
                    this.sharePhoto(this.photos[this.currentIndex]);
                    break;
            }
        }
        
        /**
         * Téléchargement d'une photo
         */
        downloadPhoto(photo) {
            const link = document.createElement('a');
            link.href = photo.src;
            link.download = photo.title || `photo-${photo.id}`;
            link.click();
        }
        
        /**
         * Partage d'une photo
         */
        sharePhoto(photo) {
            if (navigator.share) {
                navigator.share({
                    title: photo.title || 'Photo',
                    text: photo.caption || '',
                    url: photo.src
                });
            } else {
                // Fallback: copier le lien
                navigator.clipboard.writeText(photo.src).then(() => {
                    ui.toast.success('Lien copié dans le presse-papiers !');
                });
            }
        }
        
        /**
         * Mise à jour des statistiques
         */
        updateStats() {
            const stats = this.elements.stats;
            if (stats) {
                stats.innerHTML = `
                    <span class="photos-count">${this.photos.length} photo${this.photos.length > 1 ? 's' : ''}</span>
                `;
            }
        }
        
        /**
         * Affichage de l'état vide
         */
        showEmptyState() {
            this.elements.grid.innerHTML = `
                <div class="empty-gallery">
                    <div class="empty-content">
                        <i class="fas fa-images empty-icon"></i>
                        <h5>Aucune photo</h5>
                        <p class="text-muted">La galerie est vide.</p>
                        ${this.options.enableUpload ? '<button class="btn btn-primary upload-btn" data-action="upload"><i class="fas fa-plus"></i> Ajouter des photos</button>' : ''}
                    </div>
                </div>
            `;
        }
        
        /**
         * Affichage du chargement
         */
        showLoading() {
            this.elements.grid.querySelector('.gallery-loading').style.display = 'flex';
        }
        
        /**
         * Masquage du chargement
         */
        hideLoading() {
            const loading = this.elements.grid.querySelector('.gallery-loading');
            if (loading) {
                loading.style.display = 'none';
            }
        }
        
        /**
         * Ajout de nouvelles photos
         */
        addPhotos(newPhotos) {
            this.photos.push(...newPhotos);
            this.renderPhotos();
            this.updateStats();
        }
        
        /**
         * Suppression d'une photo
         */
        removePhoto(photoId) {
            this.photos = this.photos.filter(photo => photo.id !== photoId);
            this.renderPhotos();
            this.updateStats();
        }
        
        /**
         * Destruction de la galerie
         */
        destroy() {
            // Nettoyer l'observer
            if (this.observer) {
                this.observer.disconnect();
            }
            
            // Nettoyer le sortable
            if (this.sortable) {
                this.sortable.destroy();
            }
            
            // Supprimer le lightbox
            if (this.lightbox) {
                this.lightbox.remove();
            }
            
            // Vider le conteneur
            this.container.innerHTML = '';
            
            console.log('🧹 Photo gallery destroyed');
        }
    }
    
    return PhotoGalleryManager;
});

console.log('📸 Photo Gallery component ready');