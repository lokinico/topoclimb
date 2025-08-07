/**
 * Script JavaScript pour la gestion de la pagination TopoclimbCH
 */
document.addEventListener('DOMContentLoaded', function() {
    
    // Gestion du sélecteur per_page
    const perPageSelectors = document.querySelectorAll('#perPageSelect');
    
    perPageSelectors.forEach(function(selector) {
        if (selector) {
            selector.addEventListener('change', function() {
                const currentUrl = new URL(window.location);
                const newPerPage = this.value;
                
                // Mettre à jour les paramètres URL
                currentUrl.searchParams.set('per_page', newPerPage);
                currentUrl.searchParams.set('page', '1'); // Reset à la première page
                
                // Ajouter une classe de chargement
                const paginationContainer = this.closest('nav') || this.closest('.pagination-controls');
                if (paginationContainer) {
                    paginationContainer.classList.add('loading');
                }
                
                // Rediriger vers la nouvelle URL
                window.location.href = currentUrl.toString();
            });
        }
    });
    
    // Gestion du clic sur les liens de pagination
    const paginationLinks = document.querySelectorAll('.pagination .page-link');
    
    paginationLinks.forEach(function(link) {
        if (!link.closest('.page-item.disabled') && !link.closest('.page-item.active')) {
            link.addEventListener('click', function(e) {
                // Ajouter un effet visuel de chargement
                const paginationNav = this.closest('.pagination-nav') || this.closest('nav');
                if (paginationNav) {
                    paginationNav.classList.add('pagination-loading');
                }
                
                // Optionnel: ajouter un délai pour montrer l'effet
                // e.preventDefault();
                // setTimeout(() => {
                //     window.location.href = this.href;
                // }, 200);
            });
        }
    });
    
    // Fonction pour mettre à jour l'URL avec les paramètres de pagination
    function updatePaginationUrl(page, perPage) {
        const currentUrl = new URL(window.location);
        
        if (page) {
            currentUrl.searchParams.set('page', page);
        }
        
        if (perPage) {
            currentUrl.searchParams.set('per_page', perPage);
        }
        
        return currentUrl.toString();
    }
    
    // Fonction pour préserver les filtres lors des changements de pagination
    function preserveFilters(newParams) {
        const currentUrl = new URL(window.location);
        const filtersToPreserve = [
            'search', 'region_id', 'site_id', 'sector_id', 'author', 'publisher',
            'difficulty_min', 'difficulty_max', 'length_min', 'length_max',
            'altitude_min', 'altitude_max', 'year_min', 'year_max',
            'price_min', 'price_max', 'sort', 'order'
        ];
        
        // Préserver tous les filtres existants
        filtersToPreserve.forEach(function(filter) {
            const value = currentUrl.searchParams.get(filter);
            if (value) {
                newParams.set(filter, value);
            }
        });
        
        return newParams;
    }
    
    // Gestion de l'historique du navigateur pour la pagination
    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.pagination) {
            // Recharger la page avec les paramètres d'historique
            window.location.reload();
        }
    });
    
    // Fonction pour faire une requête AJAX pour la pagination (optionnel)
    function loadPageAjax(url, targetContainer) {
        const paginationContainer = document.querySelector('.pagination-controls');
        if (paginationContainer) {
            paginationContainer.classList.add('loading');
        }
        
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(html => {
            // Remplacer le contenu de la page
            if (targetContainer) {
                const parser = new DOMParser();
                const newDoc = parser.parseFromString(html, 'text/html');
                const newContent = newDoc.querySelector(targetContainer);
                
                if (newContent) {
                    const currentContainer = document.querySelector(targetContainer);
                    if (currentContainer) {
                        currentContainer.innerHTML = newContent.innerHTML;
                        
                        // Réinitialiser les event listeners
                        initializePaginationEvents();
                    }
                }
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement de la pagination:', error);
            // Fallback: redirection normale
            window.location.href = url;
        })
        .finally(() => {
            if (paginationContainer) {
                paginationContainer.classList.remove('loading');
            }
        });
    }
    
    // Fonction pour initialiser tous les événements de pagination
    function initializePaginationEvents() {
        // Réinitialiser les événements pour les nouveaux éléments chargés via AJAX
        const newPerPageSelectors = document.querySelectorAll('#perPageSelect');
        newPerPageSelectors.forEach(function(selector) {
            selector.removeEventListener('change', handlePerPageChange);
            selector.addEventListener('change', handlePerPageChange);
        });
        
        const newPaginationLinks = document.querySelectorAll('.pagination .page-link');
        newPaginationLinks.forEach(function(link) {
            if (!link.closest('.page-item.disabled') && !link.closest('.page-item.active')) {
                link.removeEventListener('click', handlePaginationClick);
                link.addEventListener('click', handlePaginationClick);
            }
        });
    }
    
    // Handlers séparés pour éviter les doublons d'événements
    function handlePerPageChange(e) {
        const currentUrl = new URL(window.location);
        const newPerPage = e.target.value;
        
        currentUrl.searchParams.set('per_page', newPerPage);
        currentUrl.searchParams.set('page', '1');
        
        const paginationContainer = e.target.closest('nav') || e.target.closest('.pagination-controls');
        if (paginationContainer) {
            paginationContainer.classList.add('loading');
        }
        
        window.location.href = currentUrl.toString();
    }
    
    function handlePaginationClick(e) {
        const paginationNav = e.target.closest('.pagination-nav') || e.target.closest('nav');
        if (paginationNav) {
            paginationNav.classList.add('pagination-loading');
        }
    }
    
    // Amélioration de l'accessibilité
    function enhanceAccessibility() {
        // Ajouter des labels ARIA appropriés
        const paginationNavs = document.querySelectorAll('nav[aria-label*="pagination"]');
        paginationNavs.forEach(function(nav) {
            const currentPage = nav.querySelector('.page-item.active .page-link');
            if (currentPage) {
                currentPage.setAttribute('aria-current', 'page');
            }
        });
        
        // Ajouter des descriptions pour les lecteurs d'écran
        const perPageSelector = document.querySelector('#perPageSelect');
        if (perPageSelector && !perPageSelector.getAttribute('aria-describedby')) {
            perPageSelector.setAttribute('aria-describedby', 'per-page-description');
            
            const description = document.createElement('div');
            description.id = 'per-page-description';
            description.className = 'sr-only';
            description.textContent = 'Sélectionnez le nombre d\'éléments à afficher par page';
            perPageSelector.parentNode.appendChild(description);
        }
    }
    
    // Initialiser l'amélioration de l'accessibilité
    enhanceAccessibility();
    
    // Animation fluide lors du changement de page
    function smoothPageTransition() {
        const contentContainer = document.querySelector('.entities-container, .sectors-container, .routes-container, .books-container');
        if (contentContainer) {
            contentContainer.style.transition = 'opacity 0.3s ease';
            
            // Ajouter un effet lors du changement
            const paginationLinks = document.querySelectorAll('.pagination .page-link');
            paginationLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    if (!this.closest('.page-item.disabled') && !this.closest('.page-item.active')) {
                        contentContainer.style.opacity = '0.6';
                    }
                });
            });
        }
    }
    
    // Activer les transitions fluides
    smoothPageTransition();
    
    // Support du clavier pour la navigation dans la pagination
    function addKeyboardSupport() {
        const paginationContainer = document.querySelector('.pagination');
        if (paginationContainer) {
            paginationContainer.addEventListener('keydown', function(e) {
                const currentActive = this.querySelector('.page-item.active .page-link');
                let targetLink = null;
                
                switch(e.key) {
                    case 'ArrowLeft':
                        // Page précédente
                        const prevLink = this.querySelector('.page-link[aria-label="Page précédente"]');
                        if (prevLink && !prevLink.closest('.page-item.disabled')) {
                            targetLink = prevLink;
                        }
                        break;
                        
                    case 'ArrowRight':
                        // Page suivante
                        const nextLink = this.querySelector('.page-link[aria-label="Page suivante"]');
                        if (nextLink && !nextLink.closest('.page-item.disabled')) {
                            targetLink = nextLink;
                        }
                        break;
                        
                    case 'Home':
                        // Première page
                        const firstLink = this.querySelector('.page-link[aria-label="Première page"]');
                        if (firstLink && !firstLink.closest('.page-item.disabled')) {
                            targetLink = firstLink;
                        }
                        break;
                        
                    case 'End':
                        // Dernière page
                        const lastLink = this.querySelector('.page-link[aria-label="Dernière page"]');
                        if (lastLink && !lastLink.closest('.page-item.disabled')) {
                            targetLink = lastLink;
                        }
                        break;
                }
                
                if (targetLink) {
                    e.preventDefault();
                    targetLink.click();
                }
            });
        }
    }
    
    // Activer le support clavier
    addKeyboardSupport();
    
    // Debug: Log des paramètres de pagination actuels
    if (window.location.search) {
        const urlParams = new URLSearchParams(window.location.search);
        const page = urlParams.get('page') || '1';
        const perPage = urlParams.get('per_page') || '15';
        
        console.log(`[Pagination] Page actuelle: ${page}, Éléments par page: ${perPage}`);
    }
});