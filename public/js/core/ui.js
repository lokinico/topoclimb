/**
 * TopoclimbCH UI Components
 * Composants d'interface utilisateur modernes et unifiés
 */

// Enregistrement du module UI
TopoclimbCH.modules.register('ui', ['utils'], (utils) => {
    
    /**
     * 🪟 Gestionnaire de modales moderne
     */
    class ModalManager {
        constructor() {
            this.activeModal = null;
            this.stack = [];
            this.init();
        }
        
        init() {
            this.createOverlay();
            this.bindEvents();
        }
        
        createOverlay() {
            if (document.querySelector('.modal-overlay')) return;
            
            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(2px);
                z-index: 9998;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            `;
            
            document.body.appendChild(overlay);
        }
        
        bindEvents() {
            // Délégation d'événements pour les déclencheurs
            document.addEventListener('click', (e) => {
                const trigger = e.target.closest('[data-modal]');
                if (trigger) {
                    e.preventDefault();
                    const modalId = trigger.dataset.modal;
                    this.open(modalId);
                }
                
                // Fermeture par clic sur overlay
                if (e.target.classList.contains('modal-overlay')) {
                    this.close();
                }
                
                // Fermeture par bouton close
                if (e.target.closest('[data-modal-close]')) {
                    this.close();
                }
            });
            
            // Fermeture par Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.activeModal) {
                    this.close();
                }
            });
        }
        
        open(modalId, options = {}) {
            const modal = document.getElementById(modalId);
            if (!modal) {
                console.warn(`Modal ${modalId} not found`);
                return;
            }
            
            const { closeOnOverlay = true, closeOnEscape = true } = options;
            
            // Empiler la modale précédente
            if (this.activeModal) {
                this.stack.push(this.activeModal);
                this.activeModal.style.display = 'none';
            }
            
            this.activeModal = modal;
            
            // Affichage
            const overlay = document.querySelector('.modal-overlay');
            overlay.style.visibility = 'visible';
            overlay.style.opacity = '1';
            
            modal.style.display = 'block';
            modal.style.zIndex = '9999';
            document.body.classList.add('modal-open');
            
            // Animation d'entrée
            requestAnimationFrame(() => {
                modal.classList.add('modal-show');
            });
            
            // Focus trap
            this.trapFocus(modal);
            
            TopoclimbCH.events.emit('modal:opened', { modalId, modal });
        }
        
        close() {
            if (!this.activeModal) return;
            
            const modal = this.activeModal;
            const modalId = modal.id;
            
            // Animation de sortie
            modal.classList.remove('modal-show');
            
            setTimeout(() => {
                modal.style.display = 'none';
                
                // Restaurer la modale précédente ou masquer l'overlay
                if (this.stack.length > 0) {
                    this.activeModal = this.stack.pop();
                    this.activeModal.style.display = 'block';
                } else {
                    this.activeModal = null;
                    const overlay = document.querySelector('.modal-overlay');
                    overlay.style.opacity = '0';
                    overlay.style.visibility = 'hidden';
                    document.body.classList.remove('modal-open');
                }
            }, 300);
            
            TopoclimbCH.events.emit('modal:closed', { modalId, modal });
        }
        
        trapFocus(modal) {
            const focusableElements = modal.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex=\"-1\"])'
            );
            
            if (focusableElements.length === 0) return;
            
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];
            
            firstElement.focus();
            
            modal.addEventListener('keydown', (e) => {
                if (e.key === 'Tab') {
                    if (e.shiftKey) {
                        if (document.activeElement === firstElement) {
                            e.preventDefault();
                            lastElement.focus();
                        }
                    } else {
                        if (document.activeElement === lastElement) {
                            e.preventDefault();
                            firstElement.focus();
                        }
                    }
                }
            });
        }
    }
    
    /**
     * 🍞 Gestionnaire de notifications toast
     */
    class ToastManager {
        constructor() {
            this.container = null;
            this.toasts = new Map();
            this.init();
        }
        
        init() {
            this.createContainer();
        }
        
        createContainer() {
            if (document.querySelector('.toast-container')) return;
            
            const container = document.createElement('div');
            container.className = 'toast-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                display: flex;
                flex-direction: column;
                gap: 10px;
                pointer-events: none;
            `;
            
            document.body.appendChild(container);
            this.container = container;
        }
        
        show(message, type = 'info', options = {}) {
            const {
                duration = 5000,
                dismissible = true,
                persistent = false,
                action = null
            } = options;
            
            const id = utils.generateId('toast');
            const toast = this.createToast(id, message, type, { dismissible, action });
            
            this.container.appendChild(toast);
            this.toasts.set(id, toast);
            
            // Animation d'entrée
            requestAnimationFrame(() => {
                toast.classList.add('toast-show');
            });
            
            // Auto-suppression
            if (!persistent && duration > 0) {
                setTimeout(() => {
                    this.hide(id);
                }, duration);
            }
            
            TopoclimbCH.events.emit('toast:shown', { id, message, type });
            
            return id;
        }
        
        createToast(id, message, type, options) {
            const toast = document.createElement('div');
            toast.id = id;
            toast.className = `toast toast-${type}`;
            toast.style.cssText = `
                background: ${this.getTypeColor(type)};
                color: white;
                padding: 16px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                min-width: 300px;
                max-width: 400px;
                transform: translateX(100%);
                transition: all 0.3s ease;
                pointer-events: auto;
                display: flex;
                align-items: center;
                gap: 12px;
            `;
            
            // Icône
            const icon = document.createElement('span');
            icon.innerHTML = this.getTypeIcon(type);
            icon.style.cssText = 'flex-shrink: 0; font-size: 18px;';
            
            // Message
            const messageEl = document.createElement('span');
            messageEl.textContent = message;
            messageEl.style.cssText = 'flex: 1; font-size: 14px; line-height: 1.4;';
            
            toast.appendChild(icon);
            toast.appendChild(messageEl);
            
            // Bouton de fermeture
            if (options.dismissible) {
                const closeBtn = document.createElement('button');
                closeBtn.innerHTML = '×';
                closeBtn.style.cssText = `
                    background: none;
                    border: none;
                    color: white;
                    font-size: 20px;
                    cursor: pointer;
                    padding: 0;
                    width: 24px;
                    height: 24px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    opacity: 0.7;
                    transition: opacity 0.2s;
                `;
                
                closeBtn.addEventListener('click', () => this.hide(id));
                closeBtn.addEventListener('mouseenter', () => closeBtn.style.opacity = '1');
                closeBtn.addEventListener('mouseleave', () => closeBtn.style.opacity = '0.7');
                
                toast.appendChild(closeBtn);
            }
            
            // Action personnalisée
            if (options.action) {
                const actionBtn = document.createElement('button');
                actionBtn.textContent = options.action.text;
                actionBtn.style.cssText = `
                    background: rgba(255, 255, 255, 0.2);
                    border: 1px solid rgba(255, 255, 255, 0.3);
                    color: white;
                    padding: 4px 8px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 12px;
                    margin-left: 8px;
                `;
                
                actionBtn.addEventListener('click', () => {
                    options.action.handler();
                    this.hide(id);
                });
                
                toast.appendChild(actionBtn);
            }
            
            return toast;
        }
        
        hide(id) {
            const toast = this.toasts.get(id);
            if (!toast) return;
            
            toast.classList.remove('toast-show');
            toast.style.transform = 'translateX(100%)';
            
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
                this.toasts.delete(id);
            }, 300);
            
            TopoclimbCH.events.emit('toast:hidden', { id });
        }
        
        getTypeColor(type) {
            const colors = {
                success: '#10b981',
                error: '#ef4444',
                warning: '#f59e0b',
                info: '#3b82f6'
            };
            return colors[type] || colors.info;
        }
        
        getTypeIcon(type) {
            const icons = {
                success: '✓',
                error: '✕',
                warning: '⚠',
                info: 'ℹ'
            };
            return icons[type] || icons.info;
        }
        
        // Méthodes de raccourci
        success(message, options) {
            return this.show(message, 'success', options);
        }
        
        error(message, options) {
            return this.show(message, 'error', { duration: 8000, ...options });
        }
        
        warning(message, options) {
            return this.show(message, 'warning', options);
        }
        
        info(message, options) {
            return this.show(message, 'info', options);
        }
        
        clear() {
            this.toasts.forEach((toast, id) => this.hide(id));
        }
    }
    
    /**
     * 🖼️ Gestionnaire de lightbox
     */
    class LightboxManager {
        constructor() {
            this.isOpen = false;
            this.currentIndex = 0;
            this.images = [];
            this.init();
        }
        
        init() {
            this.bindEvents();
        }
        
        bindEvents() {
            document.addEventListener('click', (e) => {
                const trigger = e.target.closest('[data-lightbox]');
                if (trigger) {
                    e.preventDefault();
                    this.open(trigger);
                }
            });
        }
        
        open(trigger) {
            const group = trigger.dataset.lightbox;
            this.images = Array.from(document.querySelectorAll(`[data-lightbox=\"${group}\"]`));
            this.currentIndex = this.images.indexOf(trigger);
            
            this.createLightbox();
            this.showImage(this.currentIndex);
            this.isOpen = true;
            
            document.body.classList.add('lightbox-open');
        }
        
        createLightbox() {
            if (document.querySelector('.lightbox')) return;
            
            const lightbox = document.createElement('div');
            lightbox.className = 'lightbox';
            lightbox.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.9);
                z-index: 10001;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                transition: opacity 0.3s ease;
            `;
            
            lightbox.innerHTML = `
                <div class=\"lightbox-content\" style=\"
                    position: relative;
                    max-width: 90vw;
                    max-height: 90vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                \">
                    <img class=\"lightbox-image\" style=\"
                        max-width: 100%;
                        max-height: 100%;
                        object-fit: contain;
                        border-radius: 8px;
                    \">
                    <button class=\"lightbox-close\" style=\"
                        position: absolute;
                        top: -40px;
                        right: 0;
                        background: none;
                        border: none;
                        color: white;
                        font-size: 30px;
                        cursor: pointer;
                        width: 40px;
                        height: 40px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    \">×</button>
                    <button class=\"lightbox-prev\" style=\"
                        position: absolute;
                        left: -60px;
                        top: 50%;
                        transform: translateY(-50%);
                        background: rgba(255, 255, 255, 0.1);
                        border: none;
                        color: white;
                        font-size: 24px;
                        cursor: pointer;
                        width: 50px;
                        height: 50px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    \">‹</button>
                    <button class=\"lightbox-next\" style=\"
                        position: absolute;
                        right: -60px;
                        top: 50%;
                        transform: translateY(-50%);
                        background: rgba(255, 255, 255, 0.1);
                        border: none;
                        color: white;
                        font-size: 24px;
                        cursor: pointer;
                        width: 50px;
                        height: 50px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    \">›</button>
                    <div class=\"lightbox-counter\" style=\"
                        position: absolute;
                        bottom: -40px;
                        left: 50%;
                        transform: translateX(-50%);
                        color: white;
                        font-size: 14px;
                    \"></div>
                </div>
            `;
            
            document.body.appendChild(lightbox);
            
            // Événements
            lightbox.querySelector('.lightbox-close').addEventListener('click', () => this.close());
            lightbox.querySelector('.lightbox-prev').addEventListener('click', () => this.prev());
            lightbox.querySelector('.lightbox-next').addEventListener('click', () => this.next());
            
            lightbox.addEventListener('click', (e) => {
                if (e.target === lightbox) this.close();
            });
            
            document.addEventListener('keydown', (e) => {
                if (!this.isOpen) return;
                
                switch (e.key) {
                    case 'Escape':
                        this.close();
                        break;
                    case 'ArrowLeft':
                        this.prev();
                        break;
                    case 'ArrowRight':
                        this.next();
                        break;
                }
            });
            
            // Animation d'entrée
            requestAnimationFrame(() => {
                lightbox.style.opacity = '1';
            });
        }
        
        showImage(index) {
            const lightbox = document.querySelector('.lightbox');
            const image = lightbox.querySelector('.lightbox-image');
            const counter = lightbox.querySelector('.lightbox-counter');
            const prev = lightbox.querySelector('.lightbox-prev');
            const next = lightbox.querySelector('.lightbox-next');
            
            const trigger = this.images[index];
            const src = trigger.href || trigger.dataset.src || trigger.src;
            
            image.src = src;
            counter.textContent = `${index + 1} / ${this.images.length}`;
            
            prev.style.display = this.images.length > 1 ? 'flex' : 'none';
            next.style.display = this.images.length > 1 ? 'flex' : 'none';
        }
        
        prev() {
            this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length;
            this.showImage(this.currentIndex);
        }
        
        next() {
            this.currentIndex = (this.currentIndex + 1) % this.images.length;
            this.showImage(this.currentIndex);
        }
        
        close() {
            const lightbox = document.querySelector('.lightbox');
            if (!lightbox) return;
            
            lightbox.style.opacity = '0';
            
            setTimeout(() => {
                lightbox.remove();
                this.isOpen = false;
                document.body.classList.remove('lightbox-open');
            }, 300);
        }
    }
    
    // Instances globales
    const modal = new ModalManager();
    const toast = new ToastManager();
    const lightbox = new LightboxManager();
    
    // Ajout de styles CSS automatiques
    const style = document.createElement('style');
    style.textContent = `
        .modal-open { overflow: hidden; }
        .toast-show { transform: translateX(0) !important; }
        .lightbox-open { overflow: hidden; }
    `;
    document.head.appendChild(style);
    
    // Exposer dans le namespace global
    const UI = {
        modal,
        toast,
        lightbox,
        ModalManager,
        ToastManager,
        LightboxManager
    };
    
    TopoclimbCH.ui = UI;
    
    return UI;
});

console.log('🎨 TopoclimbCH UI module ready');