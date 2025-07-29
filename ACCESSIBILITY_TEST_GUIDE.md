# Guide de Test d'Accessibilité - Système de Vues TopoclimbCH

## ✅ Tests d'Accessibilité WCAG 2.1 AA

### 🔍 Tests Automatisés

```bash
# Installation d'outils de test d'accessibilité
npm install -g axe-core pa11y lighthouse

# Tests avec pa11y
pa11y http://localhost:8000/sectors --level AA
pa11y http://localhost:8000/routes --level AA

# Tests avec Lighthouse
lighthouse http://localhost:8000/sectors --preset=desktop --only-categories=accessibility
```

### 🎯 Tests Manuels

#### 1. Navigation Clavier Complète

**Test 1: Boutons de vue**
- [ ] Tab pour naviguer vers les boutons de vue
- [ ] Flèches gauche/droite pour naviguer entre les boutons
- [ ] Flèches haut/bas pour naviguer entre les boutons  
- [ ] Home pour aller au premier bouton
- [ ] End pour aller au dernier bouton
- [ ] Entrée/Espace pour activer un bouton
- [ ] Focus visible sur le bouton actif

**Test 2: Focus Management**
- [ ] Après changement de vue, focus se déplace vers le conteneur de vue
- [ ] Focus ne se perd jamais
- [ ] Ordre de tabulation logique

#### 2. Lecteurs d'Écran

**Test avec NVDA/JAWS/VoiceOver:**
- [ ] Boutons annoncés avec leur état (pressé/non pressé)
- [ ] Changement de vue annoncé via live region
- [ ] Labels contextuels pour boutons d'action
- [ ] Navigation entre vues compréhensible
- [ ] Icônes ignorées (aria-hidden="true")

**Messages attendus:**
- "Affichage en mode cartes activé"
- "Affichage en mode liste activé"  
- "Affichage en mode compact activé"

#### 3. Contraste et Visibilité

**Test des contrastes:**
- [ ] Bouton actif: contraste ≥ 4.5:1
- [ ] Bouton inactif: contraste ≥ 3:1
- [ ] Focus indicator: contraste ≥ 3:1
- [ ] Texte sur fond: contraste ≥ 4.5:1

**Test du focus:**
- [ ] Indicateur de focus visible (bordure 3px #4f46e5)
- [ ] Décalage de 2px pour éviter confusion
- [ ] Box-shadow pour meilleure visibilité

#### 4. Responsive et Mobile

**Test tactile:**
- [ ] Boutons assez grands (min 44x44px)
- [ ] Espacements suffisants
- [ ] Pas de hover sur tactile

**Test mobile:**
- [ ] Labels textuels cachés visuellement mais disponibles aux lecteurs d'écran
- [ ] Boutons restent utilisables
- [ ] Navigation tactile fluide

### 🛠️ Outils de Test Recommandés

#### Extensions Navigateur
- **axe DevTools** (Chrome/Firefox)
- **WAVE** (Web Accessibility Evaluation Tool)
- **Lighthouse** (Chrome DevTools)
- **Accessibility Insights** (Microsoft)

#### Lecteurs d'Écran
- **NVDA** (Windows, gratuit)
- **JAWS** (Windows, payant)
- **VoiceOver** (macOS, intégré)
- **Orca** (Linux, gratuit)

#### Tests Clavier
- **Désactiver la souris** temporairement
- **Utiliser uniquement Tab, flèches, Entrée, Espace**
- **Vérifier l'ordre de navigation**

### 📋 Checklist WCAG 2.1 AA

#### Perceivable (Perceptible)
- [x] **1.4.3** Contraste (Minimum): AA ✅
- [x] **1.4.11** Contraste Non-textuel: AA ✅
- [x] **1.4.13** Contenu au Survol ou Focus: AA ✅

#### Operable (Utilisable)
- [x] **2.1.1** Clavier: A ✅
- [x] **2.1.2** Pas de Piège Clavier: A ✅
- [x] **2.4.3** Ordre du Focus: A ✅
- [x] **2.4.7** Focus Visible: AA ✅

#### Understandable (Compréhensible)
- [x] **3.2.1** Au Focus: A ✅
- [x] **3.2.2** À la Saisie: A ✅

#### Robust (Robuste)
- [x] **4.1.2** Nom, Rôle, Valeur: A ✅
- [x] **4.1.3** Messages de Statut: AA ✅

### 🚀 Fonctionnalités Implémentées

#### Attributs ARIA
```html
<!-- Boutons de vue -->
<button type="button" 
        class="btn btn-outline-primary active" 
        data-view="grid"
        aria-pressed="true"
        aria-controls="sectors-grid"
        title="Affichage en cartes">
```

#### Navigation Clavier
```javascript
// Flèches, Home, End, Enter, Espace
handleKeyboardNavigation(e, currentControl) {
    switch (e.key) {
        case 'ArrowLeft': // Navigation gauche
        case 'ArrowRight': // Navigation droite
        case 'Home': // Premier bouton
        case 'End': // Dernier bouton
        case 'Enter': // Activation
        case ' ': // Activation
    }
}
```

#### Live Region
```html
<!-- Annonces pour lecteurs d'écran -->
<div id="view-change-announcer" 
     class="sr-only" 
     aria-live="polite" 
     aria-atomic="true"></div>
```

#### Focus Management
```javascript
// Focus automatique après changement
targetView.focus({ preventScroll: true });
```

### 🔧 Debugging d'Accessibilité

#### Console DevTools
```javascript
// Vérifier les attributs ARIA
document.querySelectorAll('[data-view]').forEach(btn => {
    console.log(btn.getAttribute('aria-pressed'));
});

// Tester l'annonceur
document.getElementById('view-change-announcer').textContent = 'Test';
```

#### Simulation Lecteur d'Écran
```javascript
// Simuler la navigation
const buttons = document.querySelectorAll('[data-view]');
buttons.forEach(btn => {
    console.log(`Button: ${btn.textContent.trim()}, Pressed: ${btn.getAttribute('aria-pressed')}`);
});
```

### ⚠️ Points d'Attention

#### Problèmes Potentiels
- **Mobile**: Vérifier que text hidden fonctionne avec sr-only
- **Focus trap**: S'assurer que le focus ne se perd pas
- **Annonces**: Éviter les annonces répétitives
- **Performance**: Live region ne doit pas impacter les performances

#### Solutions
- Tests réguliers avec vrais utilisateurs
- Validation avec différents lecteurs d'écran
- Tests sur vrais appareils mobiles
- Monitoring des métriques d'accessibilité

### 📊 Métriques de Succès

- **Score Lighthouse Accessibility**: ≥ 95/100
- **Tests pa11y**: 0 erreurs niveau AA
- **Tests utilisateurs**: 100% des tâches complétées
- **Temps de navigation**: < 30% d'augmentation vs souris

---

**Dernière mise à jour**: 29 juillet 2025  
**Version**: 1.0.0  
**Conformité**: WCAG 2.1 AA ✅