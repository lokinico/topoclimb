/**
 * TopoclimbCH - Script principal
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('TopoclimbCH application loaded');
    
    // Initialisation des éléments interactifs
    initTabs();
    initAlerts();
    initMobileNavigation();
    initDropdowns();
});

/**
 * Initialise le système d'onglets
 */
function initTabs() {
    const tabLinks = document.querySelectorAll('.nav-tabs a');
    
    if (tabLinks.length === 0) return;
    
    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Désactiver tous les onglets
            tabLinks.forEach(tab => {
                tab.parentElement.classList.remove('active');
                document.querySelector(tab.getAttribute('href')).classList.remove('active');
            });
            
            // Activer l'onglet cliqué
            this.parentElement.classList.add('active');
            document.querySelector(this.getAttribute('href')).classList.add('active');
        });
    });
}

/**
 * Initialise les alertes fermables
 */
function initAlerts() {
    const closeButtons = document.querySelectorAll('.alert .close');
    
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            this.parentElement.remove();
        });
    });
}

/**
 * Initialise la navigation mobile
 */
function initMobileNavigation() {
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    const navLinks = document.querySelector('.nav-links');
    
    if (mobileToggle && navLinks) {
        mobileToggle.addEventListener('click', function() {
            this.classList.toggle('active');
            navLinks.classList.toggle('active');
        });
    }
}

/**
 * Initialise les menus déroulants
 */
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('active');
        });
    });
    
    // Fermer les menus déroulants en cliquant ailleurs
    document.addEventListener('click', function() {
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('active');
        });
    });
}
/**
 * Initialise la navigation responsive
 */
function initNavigation() {
    // Code pour la navigation mobile (à implémenter)
}
