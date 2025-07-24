/**
 * Route Show Page - Version moderne modulaire
 * Page d'affichage des voies d'escalade avec architecture moderne
 */

// Enregistrement du module de page route
TopoclimbCH.modules.register('page-route-show', ['utils', 'api', 'ui'], async (utils, api, ui) => {
    
    class RouteShowPage {
        constructor() {
            this.route = window.routeData || null;
            this.routeId = this.extractRouteIdFromUrl();
            this.components = {};
            this.initialized = false;
        }
        
        /**
         * Initialise la page route
         */
        async init() {
            if (this.initialized) {
                console.warn('Route show page already initialized');
                return;
            }
            
            if (!this.routeId) {
                console.error('No route ID found in URL');
                return;
            }
            
            console.log(`🧗 Initializing route page: ${this.routeId}`);
            
            try {
                // Charger les données si pas déjà présentes
                if (!this.route) {
                    await this.loadRouteData();
                }
                
                // Initialiser les composants
                await this.initializeComponents();
                
                // Configuration des fonctionnalités
                this.setupInteractions();
                this.setupAnimations();
                this.setupKeyboardShortcuts();
                
                this.initialized = true;
                console.log('✅ Route show page initialized successfully');
                
            } catch (error) {
                console.error('❌ Failed to initialize route page:', error);
                this.initializeFallback();
            }
        }
        
        /**
         * Extrait l'ID de la route depuis l'URL
         */
        extractRouteIdFromUrl() {
            const path = window.location.pathname;
            const matches = path.match(/\/routes\/(\d+)/);
            return matches ? parseInt(matches[1]) : null;
        }
        
        /**
         * Charge les données de la route
         */
        async loadRouteData() {
            try {
                this.route = await api.getRoute(this.routeId);
                console.log('📊 Route data loaded:', this.route);
            } catch (error) {
                console.error('Failed to load route data:', error);
                ui.toast.error('Erreur lors du chargement des données de la voie');
                throw error;
            }
        }
        
        /**
         * Initialise tous les composants
         */
        async initializeComponents() {
            // 1. Informations principales
            this.initializeRouteInfo();
            
            // 2. Carte de localisation
            await this.initializeRouteMap();
            
            // 3. Galerie photos
            this.initializePhotoGallery();
            
            // 4. Statistiques et graphiques
            this.initializeStatistics();
            
            // 5. Commentaires et avis
            this.initializeComments();
            
            // 6. Actions utilisateur
            this.initializeUserActions();
        }
        
        /**
         * Améliore l'affichage des informations de la route
         */
        initializeRouteInfo() {
            // Animation des badges de difficulté
            const difficultyBadges = document.querySelectorAll('.difficulty-badge');
            difficultyBadges.forEach((badge, index) => {
                badge.style.animationDelay = `${index * 0.1}s`;
                badge.classList.add('fade-in-up');
                
                // Ajout d'infobulles détaillées
                this.addDifficultyTooltip(badge);
            });
            
            // Animation des statistiques
            this.animateRouteStats();
            
            // Amélioration de la description
            this.enhanceDescription();
            
            console.log('📋 Route info enhanced');
        }
        
        /**
         * Animation des statistiques de la route
         */
        animateRouteStats() {
            const statNumbers = document.querySelectorAll('.stat-number');
            
            statNumbers.forEach(stat => {
                const finalValue = parseInt(stat.textContent) || 0;
                let currentValue = 0;
                const increment = finalValue / 30;
                const duration = 1000;
                const stepTime = duration / 30;
                
                const animate = () => {
                    currentValue += increment;
                    if (currentValue >= finalValue) {
                        stat.textContent = finalValue;
                        return;
                    }
                    stat.textContent = Math.floor(currentValue);
                    setTimeout(animate, stepTime);
                };
                
                // Démarrer l'animation quand visible
                if (utils.isElementVisible(stat)) {
                    setTimeout(animate, 200);
                } else {
                    const observer = new IntersectionObserver(entries => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                animate();
                                observer.unobserve(entry.target);
                            }
                        });
                    });
                    observer.observe(stat);
                }
            });
        }
        
        /**
         * Ajoute des tooltips détaillés pour les difficultés
         */
        addDifficultyTooltip(badge) {
            const difficulty = badge.textContent.trim();
            const tooltips = {
                '3': 'Facile - Idéal pour débuter',
                '4': 'Modéré - Technique de base requise',
                '5': 'Difficile - Bonne expérience nécessaire',
                '6': 'Très difficile - Niveau avancé',
                '7': 'Extrême - Niveau expert',
                '8': 'Elite - Niveau professionnel'
            };
            
            const grade = difficulty.charAt(0);
            const tooltip = tooltips[grade];
            
            if (tooltip) {
                badge.title = `${difficulty} - ${tooltip}`;
                badge.setAttribute('data-bs-toggle', 'tooltip');
                badge.setAttribute('data-bs-placement', 'top');
            }
        }
        
        /**
         * Améliore l'affichage de la description
         */
        enhanceDescription() {
            const description = document.querySelector('.route-description');
            if (!description) return;
            
            // Détection automatique de liens
            const text = description.innerHTML;
            const urlRegex = /(https?:\/\/[^\s]+)/g;
            const linkedText = text.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener">$1</a>');
            description.innerHTML = linkedText;
            
            // Mise en forme des références d'escalade
            const gradeRegex = /(\d[a-c]?\+?)/g;
            description.innerHTML = description.innerHTML.replace(gradeRegex, '<span class="grade-ref">$1</span>');
        }
        
        /**
         * Initialise la carte de localisation
         */
        async initializeRouteMap() {
            const mapContainer = document.getElementById('route-map');
            if (!mapContainer) return;
            
            try {
                // Charger le gestionnaire de carte si nécessaire
                if (!window.SwissMapManager) {
                    await this.loadScript('/js/components/swiss-map-manager.js');
                }
                
                const SwissMapManager = await TopoclimbCH.modules.load('swiss-map-manager');
                
                // Coordonnées de la route ou du secteur
                const lat = this.route?.coordinates_lat || this.route?.sector?.coordinates_lat;
                const lng = this.route?.coordinates_lng || this.route?.sector?.coordinates_lng;
                
                if (lat && lng) {
                    this.components.map = new SwissMapManager('route-map', {
                        center: [lat, lng],
                        zoom: 16,
                        showControls: true
                    });
                    
                    this.components.map.init();
                    
                    // Marqueur de la route
                    this.components.map.addMarker(lat, lng, {
                        fillColor: '#e74c3c',
                        popup: `<h6>${this.route.name}</h6><p>Difficulté: ${this.route.difficulty || 'N/A'}</p>`
                    });
                    
                    console.log('🗺️ Route map initialized');
                }
                
            } catch (error) {
                console.error('Route map initialization failed:', error);
                this.showMapFallback();
            }
        }
        
        /**
         * Initialise la galerie photos
         */
        initializePhotoGallery() {
            const galleryContainer = document.querySelector('.route-gallery');
            if (!galleryContainer) return;
            
            const images = galleryContainer.querySelectorAll('img');
            
            images.forEach((img, index) => {
                // Lazy loading avec animation
                if (img.dataset.src) {
                    img.addEventListener('load', () => {
                        img.classList.add('loaded');
                    });
                    
                    if ('IntersectionObserver' in window) {
                        const observer = new IntersectionObserver(entries => {
                            entries.forEach(entry => {
                                if (entry.isIntersecting) {
                                    const img = entry.target;
                                    img.src = img.dataset.src;
                                    observer.unobserve(img);
                                }
                            });
                        });
                        observer.observe(img);
                    } else {
                        img.src = img.dataset.src;
                    }
                }
                
                // Animation d'apparition
                img.style.animationDelay = `${index * 0.1}s`;
                img.classList.add('fade-in-up');
                
                // Lightbox simple
                img.addEventListener('click', () => {
                    this.openImageLightbox(img);
                });
            });
            
            console.log(`📸 Enhanced ${images.length} gallery images`);
        }
        
        /**
         * Ouvre une image en lightbox
         */
        openImageLightbox(img) {
            const lightbox = document.createElement('div');
            lightbox.className = 'image-lightbox';
            lightbox.innerHTML = `
                <div class="lightbox-backdrop">
                    <div class="lightbox-content">
                        <img src="${img.src}" alt="${img.alt}" class="lightbox-image">
                        <button class="lightbox-close" type="button">&times;</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(lightbox);
            
            // Fermeture
            const closeHandlers = [
                lightbox.querySelector('.lightbox-close'),
                lightbox.querySelector('.lightbox-backdrop')
            ];
            
            closeHandlers.forEach(element => {
                element.addEventListener('click', () => {
                    lightbox.remove();
                });
            });
            
            // Fermeture au clavier
            document.addEventListener('keydown', function closeOnEscape(e) {
                if (e.key === 'Escape') {
                    lightbox.remove();
                    document.removeEventListener('keydown', closeOnEscape);
                }
            });
        }
        
        /**
         * Initialise les statistiques et graphiques
         */
        initializeStatistics() {
            // Graphique de répartition des ascensions par mois
            this.createMonthlyChart();
            
            // Graphique des temps d'ascension
            this.createTimeChart();
            
            // Mise à jour des statistiques en temps réel
            this.updateLiveStats();
        }
        
        /**
         * Crée un graphique des ascensions mensuelles
         */
        createMonthlyChart() {
            const chartContainer = document.getElementById('monthly-chart');
            if (!chartContainer) return;
            
            // Données simulées (à remplacer par vraies données)
            const monthlyData = [2, 5, 8, 12, 15, 20, 18, 14, 10, 6, 3, 1];
            const months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 
                           'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
            
            let html = '<div class="chart-bars">';
            monthlyData.forEach((value, index) => {
                const percentage = (value / Math.max(...monthlyData)) * 100;
                html += `
                    <div class="chart-bar" style="--percentage: ${percentage}%">
                        <div class="bar-fill"></div>
                        <div class="bar-label">${months[index]}</div>
                        <div class="bar-value">${value}</div>
                    </div>
                `;
            });
            html += '</div>';
            
            chartContainer.innerHTML = html;
            
            // Animation des barres
            setTimeout(() => {
                chartContainer.querySelectorAll('.bar-fill').forEach((bar, index) => {
                    bar.style.animationDelay = `${index * 0.1}s`;
                    bar.classList.add('animate-height');
                });
            }, 500);
        }
        
        /**
         * Initialise les commentaires et avis
         */
        initializeComments() {
            // Système de notation par étoiles
            this.initializeRatingSystem();
            
            // Formulaire de commentaire
            this.initializeCommentForm();
            
            // Chargement des commentaires existants
            this.loadExistingComments();
        }
        
        /**
         * Système de notation par étoiles
         */
        initializeRatingSystem() {
            const ratingContainer = document.querySelector('.rating-input');
            if (!ratingContainer) return;
            
            const stars = ratingContainer.querySelectorAll('.star');
            
            stars.forEach((star, index) => {
                star.addEventListener('mouseover', () => {
                    this.highlightStars(stars, index + 1);
                });
                
                star.addEventListener('click', () => {
                    this.setRating(stars, index + 1);
                });
            });
            
            ratingContainer.addEventListener('mouseleave', () => {
                const currentRating = ratingContainer.dataset.rating || 0;
                this.highlightStars(stars, currentRating);
            });
        }
        
        /**
         * Met en surbrillance les étoiles
         */
        highlightStars(stars, rating) {
            stars.forEach((star, index) => {
                star.classList.toggle('active', index < rating);
            });
        }
        
        /**
         * Définit la note
         */
        setRating(stars, rating) {
            const container = stars[0].closest('.rating-input');
            container.dataset.rating = rating;
            this.highlightStars(stars, rating);
            
            // Mettre à jour le champ caché
            const hiddenInput = container.querySelector('input[name="rating"]');
            if (hiddenInput) {
                hiddenInput.value = rating;
            }
            
            ui.toast.success(`Note: ${rating}/5 étoiles`);
        }
        
        /**
         * Initialise les actions utilisateur
         */
        initializeUserActions() {
            // Bouton favoris
            this.setupFavoriteButton();
            
            // Bouton partage
            this.setupShareButton();
            
            // Log d'ascension
            this.setupAscentLog();
            
            // Actions rapides
            this.setupQuickActions();
        }
        
        /**
         * Configuration du bouton favoris
         */
        setupFavoriteButton() {
            const favoriteBtn = document.getElementById('favorite-btn');
            if (!favoriteBtn) return;
            
            favoriteBtn.addEventListener('click', async () => {
                try {
                    const response = await api.post('/api/favorites/routes', {
                        route_id: this.routeId
                    });
                    
                    if (response.success) {
                        favoriteBtn.classList.toggle('favorited');
                        const icon = favoriteBtn.querySelector('i');
                        const text = favoriteBtn.querySelector('.btn-text');
                        
                        if (favoriteBtn.classList.contains('favorited')) {
                            icon.className = 'fas fa-heart';
                            text.textContent = 'Retirer des favoris';
                            ui.toast.success('Ajouté aux favoris !');
                        } else {
                            icon.className = 'far fa-heart';
                            text.textContent = 'Ajouter aux favoris';
                            ui.toast.info('Retiré des favoris');
                        }
                    }
                } catch (error) {
                    console.error('Favorite error:', error);
                    ui.toast.error('Erreur lors de la gestion des favoris');
                }
            });
        }
        
        /**
         * Configuration du partage
         */
        setupShareButton() {
            const shareBtn = document.getElementById('share-btn');
            if (!shareBtn) return;
            
            shareBtn.addEventListener('click', () => {
                if (navigator.share) {
                    navigator.share({
                        title: `${this.route.name} - Voie d'escalade`,
                        text: `Découvrez cette voie d'escalade : ${this.route.name}`,
                        url: window.location.href
                    });
                } else {
                    // Fallback: copier le lien
                    navigator.clipboard.writeText(window.location.href).then(() => {
                        ui.toast.success('Lien copié dans le presse-papiers !');
                    });
                }
            });
        }
        
        /**
         * Configuration des interactions
         */
        setupInteractions() {
            // Navigation entre voies du même secteur
            this.setupRouteNavigation();
            
            // Filtres et recherche
            this.setupFilters();
            
            // Actions contextuelles
            this.setupContextMenu();
        }
        
        /**
         * Navigation entre les voies
         */
        setupRouteNavigation() {
            const prevBtn = document.getElementById('prev-route');
            const nextBtn = document.getElementById('next-route');
            
            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    this.navigateToRoute('prev');
                });
            }
            
            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    this.navigateToRoute('next');
                });
            }
        }
        
        /**
         * Configuration des raccourcis clavier
         */
        setupKeyboardShortcuts() {
            document.addEventListener('keydown', (e) => {
                // Flèches pour navigation
                if (e.key === 'ArrowLeft') {
                    this.navigateToRoute('prev');
                } else if (e.key === 'ArrowRight') {
                    this.navigateToRoute('next');
                }
                
                // F pour favoris
                if (e.key === 'f' || e.key === 'F') {
                    const favoriteBtn = document.getElementById('favorite-btn');
                    if (favoriteBtn) favoriteBtn.click();
                }
                
                // S pour partage
                if (e.key === 's' || e.key === 'S') {
                    const shareBtn = document.getElementById('share-btn');
                    if (shareBtn) shareBtn.click();
                }
            });
        }
        
        /**
         * Configuration des animations
         */
        setupAnimations() {
            // Observer d'intersection pour les animations au scroll
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('animate-in');
                        }
                    });
                }, {
                    threshold: 0.1
                });
                
                // Observer les éléments à animer
                document.querySelectorAll('.animate-on-scroll').forEach(el => {
                    observer.observe(el);
                });
            }
        }
        
        /**
         * Mode de secours
         */
        initializeFallback() {
            console.log('🔄 Initializing fallback mode for route page');
            
            // Fonctionnalités de base seulement
            this.setupBasicInteractions();
            
            ui.toast.warning('Page chargée en mode simplifié', { duration: 5000 });
        }
        
        /**
         * Interactions de base pour le mode de secours
         */
        setupBasicInteractions() {
            // Actions de base sur les boutons
            const buttons = document.querySelectorAll('button[data-action]');
            buttons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const action = btn.dataset.action;
                    ui.toast.info(`Action: ${action}`);
                });
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
         * Nettoyage
         */
        cleanup() {
            // Nettoyer les composants
            Object.values(this.components).forEach(component => {
                if (component && component.destroy) {
                    component.destroy();
                }
            });
            
            // Nettoyer les événements
            document.removeEventListener('keydown', this.handleKeyboard);
        }
    }
    
    return RouteShowPage;
});

// Auto-initialisation
document.addEventListener('DOMContentLoaded', async () => {
    // Vérifier qu'on est sur une page route
    if (!document.body.classList.contains('route-show-page') && 
        !window.location.pathname.match(/\/routes\/\d+/)) {
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
        const RouteShowPage = await TopoclimbCH.modules.load('page-route-show');
        const routePage = new RouteShowPage();
        await routePage.init();
        
        // Nettoyage
        window.addEventListener('beforeunload', () => {
            routePage.cleanup();
        });
        
    } catch (error) {
        console.error('❌ Failed to initialize route show page:', error);
    }
});

console.log('🧗 Route Show Page module ready');