/**
 * TopoclimbCH - JavaScript minimal
 */

document.addEventListener('DOMContentLoaded', function() {
  // Navigation mobile
  const mobileToggle = document.querySelector('.mobile-menu-toggle');
  const navLinks = document.querySelector('.nav-links');
  
  if (mobileToggle && navLinks) {
    mobileToggle.addEventListener('click', function() {
      navLinks.classList.toggle('active');
    });
  }
  
  // Gestion des onglets
  const tabLinks = document.querySelectorAll('.nav-tabs a');
  
  if (tabLinks.length > 0) {
    tabLinks.forEach(link => {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Retirer la classe active de tous les onglets
        document.querySelectorAll('.nav-tabs li').forEach(tab => {
          tab.classList.remove('active');
        });
        
        // Cacher tous les contenus d'onglets
        document.querySelectorAll('.tab-pane').forEach(pane => {
          pane.classList.remove('active');
        });
        
        // Ajouter la classe active à l'onglet cliqué
        this.parentElement.classList.add('active');
        
        // Afficher le contenu de l'onglet
        const target = this.getAttribute('href');
        document.querySelector(target).classList.add('active');
      });
    });
    
    // Activer le premier onglet par défaut si aucun n'est actif
    if (document.querySelector('.nav-tabs li.active') === null) {
      document.querySelector('.nav-tabs li:first-child').classList.add('active');
      document.querySelector('.tab-pane:first-child').classList.add('active');
    }
  }
  
  // Fermeture des alertes
  const alertCloseButtons = document.querySelectorAll('.alert .close');
  
  alertCloseButtons.forEach(button => {
    button.addEventListener('click', function() {
      this.parentElement.style.display = 'none';
    });
  });
  
  // Auto-fermeture des alertes après 5 secondes
  setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
      alert.style.display = 'none';
    });
  }, 5000);
  
  // Tooltip pour les éléments avec un attribut data-tooltip
  const tooltipElements = document.querySelectorAll('[data-tooltip]');
  
  tooltipElements.forEach(element => {
    element.addEventListener('mouseenter', function() {
      const tooltip = document.createElement('div');
      tooltip.classList.add('tooltip');
      tooltip.textContent = this.getAttribute('data-tooltip');
      
      document.body.appendChild(tooltip);
      
      const rect = this.getBoundingClientRect();
      tooltip.style.top = rect.bottom + 10 + 'px';
      tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
      
      this.addEventListener('mouseleave', () => {
        tooltip.remove();
      });
    });
  });
  
  // Confirmation pour les actions de suppression
  const deleteLinks = document.querySelectorAll('[data-confirm]');
  
  deleteLinks.forEach(link => {
    link.addEventListener('click', function(e) {
      const message = this.getAttribute('data-confirm') || 'Êtes-vous sûr de vouloir effectuer cette action?';
      
      if (!confirm(message)) {
        e.preventDefault();
      }
    });
  });
});