# 💰 BUSINESS PLAN - SYSTÈME D'ACCÈS TOPOCLIMB

**Date**: 29 Juillet 2025  
**Version**: 1.0  
**Statut**: PLANIFICATION FUTURE

---

## 🎯 MODÈLE ÉCONOMIQUE D'ACCÈS

### 📚 **ACCÈS PAR GUIDE (BOOKS)**
- **Principe**: L'utilisateur achète un guide spécifique
- **Accès**: Toutes les voies contenues dans ce guide
- **Modèle**: Achat unique par guide
- **Exemple**: Guide "Escalade Valais" → Accès à toutes les voies du Valais incluses

### 🏔️ **ABONNEMENTS GÉOGRAPHIQUES**

#### **Abonnement Secteur**
- **Scope**: Accès à toutes les voies d'un secteur spécifique
- **Durée**: Mensuel/Annuel
- **Prix**: Niveau local

#### **Abonnement Site**  
- **Scope**: Accès à toutes les voies d'un site d'escalade
- **Durée**: Mensuel/Annuel
- **Prix**: Niveau intermédiaire

#### **Abonnement Région**
- **Scope**: Accès à toutes les voies d'une région complète
- **Durée**: Mensuel/Annuel  
- **Prix**: Niveau premium

---

## 🔐 SYSTÈME DE PERMISSIONS À DÉVELOPPER

### **Hiérarchie d'Accès**
```
Région (Premium)
├── Site (Intermédiaire)
│   ├── Secteur (Local)  
│   │   └── Routes (Unitaire via Guide)
│   └── Secteur (Local)
└── Site (Intermédiaire)
```

### **Tables Base de Données Futures**

```sql
-- Abonnements géographiques
CREATE TABLE user_subscriptions (
    id INTEGER PRIMARY KEY,
    user_id INTEGER,
    subscription_type ENUM('sector', 'site', 'region'),
    entity_id INTEGER, -- ID du secteur/site/région
    start_date DATE,
    end_date DATE,
    status ENUM('active', 'expired', 'cancelled'),
    price DECIMAL(10,2),
    created_at TIMESTAMP
);

-- Achats de guides
CREATE TABLE user_book_purchases (
    id INTEGER PRIMARY KEY,
    user_id INTEGER,
    book_id INTEGER,
    purchase_date DATE,
    price DECIMAL(10,2),
    status ENUM('active', 'refunded'),
    created_at TIMESTAMP
);

-- Plans tarifaires
CREATE TABLE pricing_plans (
    id INTEGER PRIMARY KEY,
    entity_type ENUM('sector', 'site', 'region'),
    entity_id INTEGER,
    monthly_price DECIMAL(10,2),
    annual_price DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'CHF',
    active BOOLEAN DEFAULT true
);
```

---

## 💳 FONCTIONNALITÉS À DÉVELOPPER

### **Phase 1: Infrastructure**
- [ ] Système de paiement (Stripe/PayPal)
- [ ] Gestion des abonnements
- [ ] Vérification d'accès par route
- [ ] Interface d'achat

### **Phase 2: Business Logic**  
- [ ] Calcul automatique des prix
- [ ] Gestion des promotions
- [ ] Système de renouvellement
- [ ] Facturation

### **Phase 3: UX/UI**
- [ ] Pages d'abonnement
- [ ] Tableau de bord utilisateur
- [ ] Historique des achats
- [ ] Notifications d'expiration

---

## 🎨 INTERFACE UTILISATEUR PRÉVUE

### **Écran Route (si pas d'accès)**
```
🏔️ Voie: "Dalle de la Mort" (6b)
📍 Secteur: Chamonix Sud
💰 Accès requis: 
   📚 Guide "Chamonix Escalade" (49 CHF)
   🏔️ Abonnement Secteur (9 CHF/mois)
   🌍 Abonnement Région (29 CHF/mois)
   
[Acheter Guide] [S'abonner Secteur] [S'abonner Région]
```

### **Tableau de Bord Utilisateur**
```
Mes Abonnements:
✅ Région Valais (expire le 15/08/2025)
✅ Guide "Bloc Fontainebleau" (permanent)

Mes Accès:
🏔️ 1,247 voies disponibles
📚 3 guides possédés
🗓️ Prochaine expiration: 15/08/2025
```

---

## 💡 STRATÉGIE DE PRIX (INDICATIVE)

### **Guides**
- Guide local: 29-39 CHF
- Guide régional: 49-59 CHF  
- Guide national: 79-99 CHF

### **Abonnements Mensuels**
- Secteur: 5-15 CHF/mois
- Site: 15-25 CHF/mois
- Région: 25-39 CHF/mois

### **Abonnements Annuels** (20% réduction)
- Secteur: 50-150 CHF/an
- Site: 150-250 CHF/an  
- Région: 250-390 CHF/an

---

## 🔄 INTÉGRATION AVEC SYSTÈME ACTUEL

### **Middleware d'Accès (à créer)**
```php
class RouteAccessMiddleware {
    public function handle($request, $next) {
        $routeId = $request->route('id');
        
        if (!$this->userHasAccessToRoute($routeId)) {
            return redirect()->route('purchase.options', $routeId);
        }
        
        return $next($request);
    }
}
```

### **Service d'Accès (à créer)**
```php
class AccessService {
    public function userHasAccessToRoute($userId, $routeId): bool {
        // Vérifier abonnements actifs
        // Vérifier guides possédés  
        // Retourner true/false
    }
    
    public function getAccessOptions($routeId): array {
        // Retourner options d'achat/abonnement
    }
}
```

---

## 📋 NOTES IMPORTANTES

- **Freemium**: Garder certaines voies gratuites pour découverte
- **Essai gratuit**: 7 jours d'essai pour les abonnements
- **Géolocalisation**: Proposer automatiquement l'abonnement local
- **Bundling**: Offres groupées guides + abonnements
- **Fidélité**: Réductions pour clients récurrents

---

*Document à intégrer dans la roadmap de développement*  
*Priorité: Phase 2-3 du développement TopoclimbCH*