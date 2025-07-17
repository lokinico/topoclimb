/**
 * Service Worker pour TopoclimbCH
 * Gère le cache hors-ligne et la synchronisation des données
 */

const CACHE_NAME = 'topoclimb-v1.0.0';
const STATIC_CACHE_NAME = 'topoclimb-static-v1.0.0';
const DATA_CACHE_NAME = 'topoclimb-data-v1.0.0';

// URLs à mettre en cache pour le mode hors-ligne
const STATIC_URLS = [
    '/',
    '/css/app.css',
    '/js/app.js',
    '/js/components/common.js',
    '/js/components/map-manager.js',
    '/images/icons/climbing-marker.svg',
    '/offline.html',
    '/manifest.json'
];

// URLs des données essentielles à cache
const DATA_URLS = [
    '/api/regions',
    '/api/sites',
    '/api/alerts',
    '/api/geolocation/nearest-sites',
    '/api/geolocation/nearest-sectors'
];

// URLs des APIs externes à cache
const EXTERNAL_APIS = [
    'https://nominatim.openstreetmap.org/',
    'https://api3.geo.admin.ch/',
    'https://api.openweathermap.org/'
];

// Configuration du cache
const CACHE_CONFIG = {
    maxAgeSeconds: 60 * 60 * 24, // 24 heures
    maxEntries: 100,
    updateInterval: 60 * 60 * 1000 // 1 heure en millisecondes
};

/**
 * Installation du Service Worker
 */
self.addEventListener('install', (event) => {
    console.log('[SW] Installation du Service Worker');
    
    event.waitUntil(
        Promise.all([
            // Cache des ressources statiques
            caches.open(STATIC_CACHE_NAME)
                .then(cache => cache.addAll(STATIC_URLS)),
            
            // Cache des données essentielles
            caches.open(DATA_CACHE_NAME)
                .then(cache => {
                    return Promise.all(
                        DATA_URLS.map(url => {
                            return fetch(url)
                                .then(response => {
                                    if (response.ok) {
                                        return cache.put(url, response);
                                    }
                                })
                                .catch(err => console.log(`[SW] Erreur cache ${url}:`, err));
                        })
                    );
                })
        ])
        .then(() => {
            console.log('[SW] Cache initial créé');
            self.skipWaiting(); // Force l'activation
        })
        .catch(err => console.error('[SW] Erreur installation:', err))
    );
});

/**
 * Activation du Service Worker
 */
self.addEventListener('activate', (event) => {
    console.log('[SW] Activation du Service Worker');
    
    event.waitUntil(
        Promise.all([
            // Nettoyer les anciens caches
            caches.keys().then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== STATIC_CACHE_NAME && 
                            cacheName !== DATA_CACHE_NAME &&
                            cacheName !== CACHE_NAME) {
                            console.log('[SW] Suppression cache obsolète:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            }),
            
            // Prendre le contrôle des clients
            self.clients.claim()
        ])
        .then(() => {
            console.log('[SW] Service Worker activé');
            
            // Programmer la mise à jour du cache
            setInterval(() => {
                updateDataCache();
            }, CACHE_CONFIG.updateInterval);
        })
    );
});

/**
 * Interception des requêtes
 */
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    
    // Ignorer les requêtes non-GET
    if (request.method !== 'GET') {
        return;
    }
    
    // Stratégie de cache selon le type de requête
    if (isStaticResource(url)) {
        event.respondWith(cacheFirstStrategy(request, STATIC_CACHE_NAME));
    } else if (isDataAPI(url)) {
        event.respondWith(networkFirstStrategy(request, DATA_CACHE_NAME));
    } else if (isExternalAPI(url)) {
        event.respondWith(cacheFirstStrategy(request, CACHE_NAME));
    } else if (isNavigationRequest(request)) {
        event.respondWith(navigationHandler(request));
    }
});

/**
 * Stratégie Cache First (pour les ressources statiques)
 */
async function cacheFirstStrategy(request, cacheName) {
    try {
        const cache = await caches.open(cacheName);
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse) {
            return cachedResponse;
        }
        
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.error('[SW] Erreur cache first:', error);
        return getOfflineResponse(request);
    }
}

/**
 * Stratégie Network First (pour les données dynamiques)
 */
async function networkFirstStrategy(request, cacheName) {
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, networkResponse.clone());
            
            // Ajouter timestamp pour la fraîcheur
            const responseWithTimestamp = addTimestampToResponse(networkResponse);
            return responseWithTimestamp;
        }
        
        throw new Error('Réponse réseau invalide');
    } catch (error) {
        console.log('[SW] Réseau indisponible, utilisation du cache:', error);
        
        const cache = await caches.open(cacheName);
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse) {
            return addOfflineHeaderToResponse(cachedResponse);
        }
        
        return getOfflineResponse(request);
    }
}

/**
 * Gestionnaire de navigation
 */
async function navigationHandler(request) {
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            return networkResponse;
        }
        
        throw new Error('Réponse navigation invalide');
    } catch (error) {
        console.log('[SW] Navigation hors-ligne:', error);
        
        const cache = await caches.open(STATIC_CACHE_NAME);
        const offlineResponse = await cache.match('/offline.html');
        
        return offlineResponse || new Response('Hors ligne', { status: 503 });
    }
}

/**
 * Mise à jour du cache des données
 */
async function updateDataCache() {
    console.log('[SW] Mise à jour du cache des données');
    
    try {
        const cache = await caches.open(DATA_CACHE_NAME);
        
        await Promise.all(
            DATA_URLS.map(async url => {
                try {
                    const response = await fetch(url);
                    if (response.ok) {
                        await cache.put(url, response);
                    }
                } catch (error) {
                    console.log(`[SW] Erreur mise à jour ${url}:`, error);
                }
            })
        );
        
        console.log('[SW] Cache des données mis à jour');
    } catch (error) {
        console.error('[SW] Erreur mise à jour cache:', error);
    }
}

/**
 * Synchronisation en arrière-plan
 */
self.addEventListener('sync', (event) => {
    console.log('[SW] Synchronisation en arrière-plan:', event.tag);
    
    if (event.tag === 'background-sync') {
        event.waitUntil(
            syncOfflineData()
        );
    }
});

/**
 * Synchronisation des données hors-ligne
 */
async function syncOfflineData() {
    try {
        // Récupérer les données stockées localement
        const offlineData = await getOfflineStoredData();
        
        if (offlineData.length === 0) {
            return;
        }
        
        console.log('[SW] Synchronisation de', offlineData.length, 'éléments');
        
        // Synchroniser chaque élément
        for (const item of offlineData) {
            try {
                const response = await fetch(item.url, {
                    method: item.method || 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Offline-Sync': 'true'
                    },
                    body: JSON.stringify(item.data)
                });
                
                if (response.ok) {
                    await removeOfflineStoredData(item.id);
                    console.log('[SW] Élément synchronisé:', item.id);
                }
            } catch (error) {
                console.error('[SW] Erreur synchronisation élément:', item.id, error);
            }
        }
        
        // Mettre à jour le cache après synchronisation
        await updateDataCache();
    } catch (error) {
        console.error('[SW] Erreur synchronisation:', error);
    }
}

/**
 * Notification push
 */
self.addEventListener('push', (event) => {
    console.log('[SW] Notification push reçue');
    
    const options = {
        body: 'Nouvelles données disponibles',
        icon: '/images/icons/climbing-marker.svg',
        badge: '/images/icons/climbing-marker.svg',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            {
                action: 'explore',
                title: 'Voir les nouveautés',
                icon: '/images/icons/climbing-marker.svg'
            },
            {
                action: 'close',
                title: 'Fermer',
                icon: '/images/icons/climbing-marker.svg'
            }
        ]
    };
    
    if (event.data) {
        const data = event.data.json();
        options.body = data.body || options.body;
        options.title = data.title || 'TopoclimbCH';
    }
    
    event.waitUntil(
        self.registration.showNotification('TopoclimbCH', options)
    );
});

/**
 * Clic sur notification
 */
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Clic sur notification:', event.action);
    
    event.notification.close();
    
    if (event.action === 'explore') {
        event.waitUntil(
            clients.matchAll().then(clients => {
                if (clients.length) {
                    clients[0].navigate('/');
                    clients[0].focus();
                } else {
                    clients.openWindow('/');
                }
            })
        );
    }
});

/**
 * Fonctions utilitaires
 */
function isStaticResource(url) {
    return url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|woff|woff2|ttf|ico)$/);
}

function isDataAPI(url) {
    return url.pathname.startsWith('/api/');
}

function isExternalAPI(url) {
    return EXTERNAL_APIS.some(apiUrl => url.href.startsWith(apiUrl));
}

function isNavigationRequest(request) {
    return request.mode === 'navigate' || 
           (request.method === 'GET' && request.headers.get('accept').includes('text/html'));
}

function addTimestampToResponse(response) {
    const headers = new Headers(response.headers);
    headers.set('X-Cache-Timestamp', Date.now().toString());
    
    return new Response(response.body, {
        status: response.status,
        statusText: response.statusText,
        headers: headers
    });
}

function addOfflineHeaderToResponse(response) {
    const headers = new Headers(response.headers);
    headers.set('X-Offline-Mode', 'true');
    
    return new Response(response.body, {
        status: response.status,
        statusText: response.statusText,
        headers: headers
    });
}

async function getOfflineResponse(request) {
    const cache = await caches.open(STATIC_CACHE_NAME);
    const fallbackResponse = await cache.match('/offline.html');
    
    return fallbackResponse || new Response(
        JSON.stringify({
            error: 'Contenu indisponible hors ligne',
            offline: true,
            timestamp: Date.now()
        }),
        {
            status: 503,
            headers: {
                'Content-Type': 'application/json',
                'X-Offline-Mode': 'true'
            }
        }
    );
}

async function getOfflineStoredData() {
    try {
        const cache = await caches.open(DATA_CACHE_NAME);
        const response = await cache.match('/offline-data');
        
        if (response) {
            return await response.json();
        }
        
        return [];
    } catch (error) {
        console.error('[SW] Erreur récupération données hors-ligne:', error);
        return [];
    }
}

async function removeOfflineStoredData(id) {
    try {
        const data = await getOfflineStoredData();
        const filteredData = data.filter(item => item.id !== id);
        
        const cache = await caches.open(DATA_CACHE_NAME);
        await cache.put('/offline-data', new Response(JSON.stringify(filteredData)));
    } catch (error) {
        console.error('[SW] Erreur suppression données hors-ligne:', error);
    }
}

console.log('[SW] Service Worker TopoclimbCH chargé');