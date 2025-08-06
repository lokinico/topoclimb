# Guide TopoclimbCH - Documentation Projet

> Application web de gestion des sites d'escalade en Suisse

**Documentation organisée par phases de développement** - Consultez les fichiers spécifiques selon vos besoins.

## 📋 Index de la Documentation

- **[DEVELOPMENT.md](docs/DEVELOPMENT.md)** - Règles de développement et workflow
- **[PHASES.md](docs/PHASES.md)** - Phases de développement par priorité
- **[DAILY_MEMORY.md](docs/DAILY_MEMORY.md)** - Mémoire quotidienne des actions
- **[COMMANDS.md](docs/COMMANDS.md)** - Commandes Gemini CLI et Claude Code AI
- **[ARCHITECTURE.md](docs/ARCHITECTURE.md)** - Architecture technique et structure
- **[TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md)** - Résolution des problèmes

## 🚨 RÈGLES CRITIQUES

**WORKFLOW OBLIGATOIRE :**
1. 🔍 **Analyser** avec Gemini CLI
2. ⚡ **Modifier** avec Claude Code AI  
3. 📝 **Commiter** IMMÉDIATEMENT
4. ✅ **Vérifier** avec Gemini CLI

> Détails complets dans [DEVELOPMENT.md](docs/DEVELOPMENT.md)

## 🏗️ Structure Projet

> Architecture complète dans [ARCHITECTURE.md](docs/ARCHITECTURE.md)

**Stack :** PHP 8.4, Framework MVC, Twig, Bootstrap 5, MySQL/SQLite

## 🎯 Statut Projet (Août 2025)

**✅ Fonctionnel :** Base MVC, Auth, APIs, Tests (40/40)
**⚠️ En cours :** Problème secteurs production (colonne 'code')
**🔄 Priorité :** Corrections DB, Fallback système

> Détails complets dans [PHASES.md](docs/PHASES.md)

## 🚨 Problème Critique Actuel

**Erreur production :** `Unknown column 'code'` sur page secteurs
**Solutions créées :** Scripts diagnostic + fallback 4 niveaux

> Guide complet dans [TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md)

## 📖 Liens Utiles

- **[Architecture](docs/ARCHITECTURE.md)** - Stack technique et DB
- **[Commandes](docs/COMMANDS.md)** - Gemini CLI et Claude Code AI
- **[Mémoire](docs/DAILY_MEMORY.md)** - Journal quotidien des actions

---

*Ce fichier principal sert d'index vers la documentation spécialisée. Consultez les fichiers appropriés selon vos besoins.*