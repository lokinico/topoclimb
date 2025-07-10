/**
 * Google Analytics helpers for TopoclimbCH
 * Provides climbing-specific tracking functions
 */

// Check if Google Analytics is loaded
function isAnalyticsReady() {
    return typeof gtag !== 'undefined';
}

// Track climbing route view
function trackRouteView(routeId, routeName, difficulty, style, region) {
    if (!isAnalyticsReady()) return;
    
    gtag('event', 'route_view', {
        event_category: 'climbing',
        event_label: routeName,
        value: routeId,
        custom_dimension_1: 'route',
        custom_dimension_2: region,
        difficulty: difficulty,
        route_style: style
    });
}

// Track sector view
function trackSectorView(sectorId, sectorName, region) {
    if (!isAnalyticsReady()) return;
    
    gtag('event', 'sector_view', {
        event_category: 'climbing',
        event_label: sectorName,
        value: sectorId,
        custom_dimension_1: 'sector',
        custom_dimension_2: region
    });
}

// Track region view
function trackRegionView(regionId, regionName) {
    if (!isAnalyticsReady()) return;
    
    gtag('event', 'region_view', {
        event_category: 'climbing',
        event_label: regionName,
        value: regionId,
        custom_dimension_1: 'region',
        custom_dimension_2: regionName
    });
}

// Track search queries
function trackSearch(query, resultCount, searchType) {
    if (!isAnalyticsReady()) return;
    
    gtag('event', 'search', {
        search_term: query,
        event_category: 'engagement',
        event_label: query,
        value: resultCount,
        search_type: searchType || 'general'
    });
}

// Track file downloads
function trackDownload(fileName, fileType) {
    if (!isAnalyticsReady()) return;
    
    gtag('event', 'file_download', {
        event_category: 'engagement',
        event_label: fileName,
        file_type: fileType,
        file_name: fileName
    });
}

// Track weather widget usage
function trackWeatherView(location) {
    if (!isAnalyticsReady()) return;
    
    gtag('event', 'weather_view', {
        event_category: 'engagement',
        event_label: location,
        widget_type: 'weather'
    });
}

// Track map interactions
function trackMapInteraction(action, location) {
    if (!isAnalyticsReady()) return;
    
    gtag('event', 'map_interaction', {
        event_category: 'engagement',
        event_label: location,
        interaction_type: action
    });
}

// Track user registration/login
function trackUserAction(action, userType) {
    if (!isAnalyticsReady()) return;
    
    gtag('event', action, {
        event_category: 'user',
        custom_dimension_1: userType || 'user'
    });
}

// Track form submissions
function trackFormSubmission(formType, success) {
    if (!isAnalyticsReady()) return;
    
    gtag('event', 'form_submit', {
        event_category: 'engagement',
        event_label: formType,
        success: success ? 'yes' : 'no'
    });
}

// Track external link clicks
function trackExternalLink(url, linkText) {
    if (!isAnalyticsReady()) return;
    
    gtag('event', 'click', {
        event_category: 'outbound',
        event_label: url,
        transport_type: 'beacon',
        link_text: linkText
    });
}

// Track page timing
function trackPageTiming(timingCategory, timingVar, timingValue) {
    if (!isAnalyticsReady()) return;
    
    gtag('event', 'timing_complete', {
        name: timingVar,
        value: timingValue,
        event_category: timingCategory
    });
}

// Auto-track external links
document.addEventListener('DOMContentLoaded', function() {
    // Track external links automatically
    document.querySelectorAll('a[href^="http"]').forEach(function(link) {
        if (!link.href.includes(window.location.hostname)) {
            link.addEventListener('click', function() {
                trackExternalLink(this.href, this.textContent);
            });
        }
    });
    
    // Track file downloads automatically
    document.querySelectorAll('a[href$=".pdf"], a[href$=".zip"], a[href$=".doc"], a[href$=".docx"]').forEach(function(link) {
        link.addEventListener('click', function() {
            const fileName = this.href.split('/').pop();
            const fileType = fileName.split('.').pop();
            trackDownload(fileName, fileType);
        });
    });
    
    // Track search form submissions
    document.querySelectorAll('form[data-track="search"]').forEach(function(form) {
        form.addEventListener('submit', function() {
            const query = form.querySelector('input[type="search"], input[name="q"]');
            if (query && query.value.trim()) {
                trackSearch(query.value.trim(), 0, form.dataset.searchType || 'general');
            }
        });
    });
});

// Track page load timing
window.addEventListener('load', function() {
    setTimeout(function() {
        if (window.performance && window.performance.timing) {
            const timing = window.performance.timing;
            const loadTime = timing.loadEventEnd - timing.navigationStart;
            trackPageTiming('Page Load', 'load_time', loadTime);
        }
    }, 100);
});