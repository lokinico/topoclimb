/**
 * API Integration Layer
 * Ensures all pages use live API data instead of static data
 */

// Auto-initialize API integration when DOM is ready
document.addEventListener('DOMContentLoaded', async () => {
    console.log('🔄 Initializing API integration...');
    
    try {
        // Wait for TopoclimbCH system to be ready
        await waitForTopoclimbCH();
        
        // Initialize API integration for current page
        await initializePageAPI();
        
        console.log('✅ API integration initialized successfully');
    } catch (error) {
        console.error('❌ API integration failed:', error);
    }
});

/**
 * Wait for TopoclimbCH system to be ready
 */
function waitForTopoclimbCH() {
    return new Promise((resolve) => {
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

/**
 * Initialize API integration based on current page
 */
async function initializePageAPI() {
    const bodyClasses = document.body.className;
    
    // Regions pages
    if (bodyClasses.includes('regions-page') || bodyClasses.includes('region-')) {
        await integrateRegionsAPI();
    }
    
    // Sites pages  
    if (bodyClasses.includes('sites-page') || bodyClasses.includes('site-')) {
        await integrateSitesAPI();
    }
    
    // Sectors pages
    if (bodyClasses.includes('sectors-page') || bodyClasses.includes('sector-')) {
        await integrateSectorsAPI();
    }
    
    // Routes pages
    if (bodyClasses.includes('routes-page') || bodyClasses.includes('route-')) {
        await integrateRoutesAPI();
    }
    
    // Books pages
    if (bodyClasses.includes('books-page') || bodyClasses.includes('book-')) {
        await integrateBooksAPI();
    }
}

/**
 * Integrate regions API
 */
async function integrateRegionsAPI() {
    console.log('🏔️ Integrating regions API...');
    
    try {
        const api = await TopoclimbCH.modules.load('api');
        
        // Override static regionsData if it exists
        if (window.regionsData) {
            console.log('📝 Refreshing regions data from API...');
            const response = await api.getRegions();
            
            if (response.success && response.data) {
                // Update window.regionsData structure
                window.regionsData = {
                    ...window.regionsData,
                    regions: response.data,
                    lastUpdated: Date.now()
                };
                
                // Notify existing components that data has been refreshed
                if (window.regionsIndex && typeof window.regionsIndex.refreshData === 'function') {
                    window.regionsIndex.refreshData(response.data);
                } else {
                    // Force re-render if regions index exists
                    if (window.regionsIndex) {
                        window.regionsIndex.regions = response.data;
                        window.regionsIndex.filteredRegions = [...response.data];
                        window.regionsIndex.renderRegions();
                    }
                }
                
                console.log(`✅ Regions API integrated: ${response.data.length} regions loaded`);
            }
        }
        
        // Add real-time API loading to select elements
        await enhanceRegionSelectors(api);
        
    } catch (error) {
        console.error('❌ Regions API integration failed:', error);
    }
}

/**
 * Integrate sites API
 */
async function integrateSitesAPI() {
    console.log('🏕️ Integrating sites API...');
    
    try {
        const api = await TopoclimbCH.modules.load('api');
        
        // Load sites data dynamically
        const response = await api.getSites();
        
        if (response.success && response.data) {
            // Update existing sites data structure
            if (window.sitesData) {
                window.sitesData = {
                    ...window.sitesData,
                    sites: response.data,
                    lastUpdated: Date.now()
                };
            }
            
            // Refresh sites index if exists
            if (window.sitesIndex) {
                window.sitesIndex.sites = response.data;
                window.sitesIndex.filteredSites = [...response.data];
                if (typeof window.sitesIndex.renderSites === 'function') {
                    window.sitesIndex.renderSites();
                }
            }
            
            console.log(`✅ Sites API integrated: ${response.data.length} sites loaded`);
        }
        
        await enhanceSiteSelectors(api);
        
    } catch (error) {
        console.error('❌ Sites API integration failed:', error);
    }
}

/**
 * Integrate sectors API
 */
async function integrateSectorsAPI() {
    console.log('📍 Integrating sectors API...');
    
    try {
        const api = await TopoclimbCH.modules.load('api');
        
        const response = await api.getSectors();
        
        if (response.success && response.data) {
            if (window.sectorsData) {
                window.sectorsData = {
                    ...window.sectorsData,
                    sectors: response.data,
                    lastUpdated: Date.now()
                };
            }
            
            if (window.sectorsIndex) {
                window.sectorsIndex.sectors = response.data;
                window.sectorsIndex.filteredSectors = [...response.data];
                if (typeof window.sectorsIndex.renderSectors === 'function') {
                    window.sectorsIndex.renderSectors();
                }
            }
            
            console.log(`✅ Sectors API integrated: ${response.data.length} sectors loaded`);
        }
        
        await enhanceSectorSelectors(api);
        
    } catch (error) {
        console.error('❌ Sectors API integration failed:', error);
    }
}

/**
 * Integrate routes API
 */
async function integrateRoutesAPI() {
    console.log('🧗 Integrating routes API...');
    
    try {
        const api = await TopoclimbCH.modules.load('api');
        
        const response = await api.getRoutes();
        
        if (response.success && response.data) {
            if (window.routesData) {
                window.routesData = {
                    ...window.routesData,
                    routes: response.data,
                    lastUpdated: Date.now()
                };
            }
            
            if (window.routesIndex) {
                window.routesIndex.routes = response.data;
                window.routesIndex.filteredRoutes = [...response.data];
                if (typeof window.routesIndex.renderRoutes === 'function') {
                    window.routesIndex.renderRoutes();
                }
            }
            
            console.log(`✅ Routes API integrated: ${response.data.length} routes loaded`);
        }
        
        await enhanceRouteSelectors(api);
        
    } catch (error) {
        console.error('❌ Routes API integration failed:', error);
    }
}

/**
 * Integrate books API
 */
async function integrateBooksAPI() {
    console.log('📚 Integrating books API...');
    
    try {
        const api = await TopoclimbCH.modules.load('api');
        
        const response = await api.get('/api/books');
        
        if (response.success && response.data) {
            if (window.booksData) {
                window.booksData = {
                    ...window.booksData,
                    books: response.data,
                    lastUpdated: Date.now()
                };
            }
            
            if (window.booksIndex) {
                window.booksIndex.books = response.data;
                window.booksIndex.filteredBooks = [...response.data];
                if (typeof window.booksIndex.renderBooks === 'function') {
                    window.booksIndex.renderBooks();
                }
            }
            
            console.log(`✅ Books API integrated: ${response.data.length} books loaded`);
        }
        
    } catch (error) {
        console.error('❌ Books API integration failed:', error);
    }
}

/**
 * Enhance region selectors with dynamic loading
 */
async function enhanceRegionSelectors(api) {
    const regionSelectors = document.querySelectorAll('select[name="region_id"], select[data-region-select]');
    
    for (const select of regionSelectors) {
        if (select.dataset.enhanced === 'true') continue;
        
        try {
            const response = await api.getRegions();
            if (response.success && response.data) {
                // Clear existing options except first (usually "-- Select --")
                const firstOption = select.querySelector('option:first-child');
                select.innerHTML = '';
                if (firstOption) {
                    select.appendChild(firstOption);
                }
                
                // Add API data options
                response.data.forEach(region => {
                    const option = document.createElement('option');
                    option.value = region.id;
                    option.textContent = region.name;
                    select.appendChild(option);
                });
                
                select.dataset.enhanced = 'true';
            }
        } catch (error) {
            console.error('Failed to enhance region selector:', error);
        }
    }
}

/**
 * Enhance site selectors with dynamic loading
 */
async function enhanceSiteSelectors(api) {
    const siteSelectors = document.querySelectorAll('select[name="site_id"], select[data-site-select]');
    
    for (const select of siteSelectors) {
        if (select.dataset.enhanced === 'true') continue;
        
        try {
            const response = await api.getSites();
            if (response.success && response.data) {
                const firstOption = select.querySelector('option:first-child');
                select.innerHTML = '';
                if (firstOption) {
                    select.appendChild(firstOption);
                }
                
                response.data.forEach(site => {
                    const option = document.createElement('option');
                    option.value = site.id;
                    option.textContent = site.name;
                    select.appendChild(option);
                });
                
                select.dataset.enhanced = 'true';
            }
        } catch (error) {
            console.error('Failed to enhance site selector:', error);
        }
    }
}

/**
 * Enhance sector selectors with dynamic loading  
 */
async function enhanceSectorSelectors(api) {
    const sectorSelectors = document.querySelectorAll('select[name="sector_id"], select[data-sector-select]');
    
    for (const select of sectorSelectors) {
        if (select.dataset.enhanced === 'true') continue;
        
        try {
            const response = await api.getSectors();
            if (response.success && response.data) {
                const firstOption = select.querySelector('option:first-child');
                select.innerHTML = '';
                if (firstOption) {
                    select.appendChild(firstOption);
                }
                
                response.data.forEach(sector => {
                    const option = document.createElement('option');
                    option.value = sector.id;
                    option.textContent = sector.name;
                    select.appendChild(option);
                });
                
                select.dataset.enhanced = 'true';
            }
        } catch (error) {
            console.error('Failed to enhance sector selector:', error);
        }
    }
}

/**
 * Enhance route selectors with dynamic loading
 */
async function enhanceRouteSelectors(api) {
    const routeSelectors = document.querySelectorAll('select[name="route_id"], select[data-route-select]');
    
    for (const select of routeSelectors) {
        if (select.dataset.enhanced === 'true') continue;
        
        try {
            const response = await api.getRoutes();
            if (response.success && response.data) {
                const firstOption = select.querySelector('option:first-child');
                select.innerHTML = '';
                if (firstOption) {
                    select.appendChild(firstOption);
                }
                
                response.data.forEach(route => {
                    const option = document.createElement('option');
                    option.value = route.id;
                    option.textContent = route.name;
                    select.appendChild(option);
                });
                
                select.dataset.enhanced = 'true';
            }
        } catch (error) {
            console.error('Failed to enhance route selector:', error);
        }
    }
}

/**
 * Global API refresh function  
 */
window.refreshAPIData = async function() {
    console.log('🔄 Refreshing all API data...');
    
    try {
        const api = await TopoclimbCH.modules.load('api');
        api.clearCache();
        
        await initializePageAPI();
        
        // Show success notification if toast system is available
        if (window.TopoclimbCH && window.TopoclimbCH.ui && window.TopoclimbCH.ui.toast) {
            window.TopoclimbCH.ui.toast.success('Données actualisées avec succès');
        }
        
        console.log('✅ API data refresh completed');
    } catch (error) {
        console.error('❌ API data refresh failed:', error);
        
        // Show error notification if toast system is available
        if (window.TopoclimbCH && window.TopoclimbCH.ui && window.TopoclimbCH.ui.toast) {
            window.TopoclimbCH.ui.toast.error('Erreur lors de l\'actualisation des données');
        }
    }
};

console.log('🔗 API Integration Layer loaded');