# TopoclimbCH API v1 Documentation

## Overview

L'API REST v1 de TopoclimbCH fournit des endpoints standardisés pour accéder et manipuler les données de la plateforme d'escalade. Tous les endpoints retournent des réponses JSON formatées de manière cohérente.

## Base URL

```
https://your-domain.com/api/v1
```

## Authentication

Tous les endpoints API nécessitent une authentification. L'utilisateur doit être connecté via la session web.

## Response Format

### Réponses de succès

```json
{
    "status": "success",
    "data": {
        // Données de la ressource
    }
}
```

### Réponses paginées

```json
{
    "status": "success",
    "data": [...],
    "meta": {
        "pagination": {
            "total": 100,
            "count": 20,
            "per_page": 20,
            "current_page": 1,
            "total_pages": 5
        }
    }
}
```

### Réponses d'erreur

```json
{
    "status": "error",
    "message": "Description de l'erreur",
    "code": "ERROR_CODE"
}
```

## Regions API

### GET /api/v1/regions

Récupère la liste des régions avec pagination et recherche.

**Parameters:**
- `page` (optional): Numéro de page (défaut: 1)
- `per_page` (optional): Nombre d'éléments par page (défaut: 20, max: 100)
- `q` (optional): Terme de recherche
- `sort` (optional): Champ de tri (name, created_at, updated_at)
- `order` (optional): Ordre de tri (asc, desc)

**Example:**
```bash
GET /api/v1/regions?page=1&per_page=10&q=valais&sort=name&order=asc
```

**Response:**
```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "name": "Valais",
            "description": "Région alpine...",
            "image": "valais.jpg",
            "weather_info": "Climat alpin...",
            "created_at": "2024-01-01 10:00:00",
            "updated_at": "2024-01-01 10:00:00"
        }
    ],
    "meta": {
        "pagination": {
            "total": 1,
            "count": 1,
            "per_page": 10,
            "current_page": 1,
            "total_pages": 1
        }
    }
}
```

### GET /api/v1/regions/{id}

Récupère une région spécifique avec ses sites.

**Example:**
```bash
GET /api/v1/regions/1
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "id": 1,
        "name": "Valais",
        "description": "Région alpine...",
        "image": "valais.jpg",
        "weather_info": "Climat alpin...",
        "created_at": "2024-01-01 10:00:00",
        "updated_at": "2024-01-01 10:00:00",
        "sites": [
            {
                "id": 1,
                "name": "Site Example",
                "description": "Description du site"
            }
        ]
    }
}
```

### POST /api/v1/regions

Crée une nouvelle région.

**Permissions:** Admin, Modérateur seulement

**Request:**
```json
{
    "name": "Nouvelle Région",
    "description": "Description de la région",
    "image": "image.jpg",
    "weather_info": "Informations météo"
}
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "id": 2,
        "name": "Nouvelle Région",
        "description": "Description de la région",
        "image": "image.jpg",
        "weather_info": "Informations météo",
        "created_at": "2024-01-01 12:00:00",
        "updated_at": "2024-01-01 12:00:00"
    }
}
```

### PUT /api/v1/regions/{id}

Met à jour une région existante.

**Permissions:** Admin, Modérateur seulement

**Request:**
```json
{
    "name": "Nom Modifié",
    "description": "Description mise à jour"
}
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "id": 1,
        "name": "Nom Modifié",
        "description": "Description mise à jour",
        "image": "valais.jpg",
        "weather_info": "Climat alpin...",
        "created_at": "2024-01-01 10:00:00",
        "updated_at": "2024-01-01 12:30:00"
    }
}
```

### DELETE /api/v1/regions/{id}

Supprime une région (soft delete).

**Permissions:** Admin seulement

**Response:**
```json
{
    "status": "success",
    "data": {
        "message": "Region deleted successfully"
    }
}
```

## Error Codes

- `400` - Bad Request: Données invalides
- `401` - Unauthorized: Authentification requise
- `403` - Forbidden: Permissions insuffisantes
- `404` - Not Found: Ressource non trouvée
- `409` - Conflict: Conflit (nom déjà existant)
- `422` - Validation Error: Erreurs de validation
- `500` - Server Error: Erreur interne du serveur

## Usage Examples

### Créer une région avec curl

```bash
curl -X POST https://your-domain.com/api/v1/regions \
  -H "Content-Type: application/json" \
  -H "Cookie: session_cookie_here" \
  -d '{
    "name": "Test Region",
    "description": "Test description"
  }'
```

### Rechercher des régions

```bash
curl "https://your-domain.com/api/v1/regions?q=valais&sort=name&order=asc" \
  -H "Cookie: session_cookie_here"
```

### Mettre à jour une région

```bash
curl -X PUT https://your-domain.com/api/v1/regions/1 \
  -H "Content-Type: application/json" \
  -H "Cookie: session_cookie_here" \
  -d '{
    "description": "Updated description"
  }'
```

## Rate Limiting

Actuellement, aucune limitation de taux n'est implémentée, mais il est recommandé de ne pas dépasser 100 requêtes par minute.

## Changelog

### v1.0.0
- Endpoints Regions complets (CRUD)
- Réponses JSON standardisées
- Support de la pagination
- Support de la recherche
- Gestion d'erreurs cohérente