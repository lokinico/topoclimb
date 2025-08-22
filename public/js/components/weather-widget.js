/**
 * WeatherWidget - Widget météo pour pages région
 * Extrait et modularisé depuis pages/regions/show.js
 */

// Classe autonome pour widgets météo secteurs (depuis Twig template)
class SimpleWeatherWidget {
    constructor(element) {
        this.element = element;
        this.lat = element.dataset.lat;
        this.lng = element.dataset.lng;
        this.sectorId = element.dataset.sectorId;
        
        this.loadingEl = element.querySelector('.weather-loading');
        this.contentEl = element.querySelector('.weather-content');
        this.errorEl = element.querySelector('.weather-error');
        this.updatedEl = element.querySelector('.weather-updated');
        
        this.init();
    }
    
    async init() {
        if (!this.lat || !this.lng) {
            this.showError('Coordonnées manquantes');
            return;
        }
        
        try {
            await this.loadWeatherData();
        } catch (error) {
            console.error('Erreur chargement météo:', error);
            this.showError('Erreur de chargement');
        }
    }
    
    async loadWeatherData() {
        const response = await fetch(`/api/weather/current?lat=${this.lat}&lng=${this.lng}&sector_id=${this.sectorId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const response_data = await response.json();
        
        if (!response_data.success) {
            throw new Error(response_data.error || 'Erreur API');
        }
        
        this.displayWeatherData(response_data.data);
    }
    
    displayWeatherData(data) {
        this.hideLoading();
        
        this.setElementText('.weather-temp', `${Math.round(data.temperature)}°`);
        this.setElementText('.weather-desc', data.description);
        this.setElementText('.weather-humidity', `${data.humidity}%`);
        this.setElementText('.weather-wind', `${Math.round(data.wind_speed)} km/h`);
        this.setElementText('.weather-precipitation', `${data.precipitation || 0} mm`);
        
        this.updateWeatherIcon(data.condition, data.description);
        this.updateClimbingRecommendation(data.climbing_conditions);
        this.setElementText('.weather-timestamp', new Date().toLocaleString('fr-CH'));
        
        this.contentEl.classList.remove('d-none');
        this.updatedEl.classList.remove('d-none');
    }
    
    updateWeatherIcon(iconCode, description) {
        const iconEl = this.element.querySelector('.weather-icon i');
        const iconMap = {
            '01d': 'fas fa-sun text-warning',
            '01n': 'fas fa-moon text-secondary',
            '02d': 'fas fa-cloud-sun text-warning',
            '02n': 'fas fa-cloud-moon text-secondary',
            '03d': 'fas fa-cloud text-secondary',
            '04d': 'fas fa-cloud text-secondary',
            '09d': 'fas fa-cloud-rain text-primary',
            '10d': 'fas fa-cloud-sun-rain text-primary',
            '11d': 'fas fa-bolt text-warning',
            '13d': 'fas fa-snowflake text-info',
            '50d': 'fas fa-smog text-muted'
        };
        
        iconEl.className = iconMap[iconCode] || 'fas fa-question text-muted';
        iconEl.setAttribute('title', description);
    }
    
    updateClimbingRecommendation(conditions) {
        const recommendationEl = this.element.querySelector('.climbing-recommendation');
        const statusEl = this.element.querySelector('.recommendation-status');
        const textEl = this.element.querySelector('.recommendation-text');
        const iconEl = this.element.querySelector('.recommendation-icon');
        
        recommendationEl.className = 'climbing-recommendation mt-3 p-2 rounded';
        
        const rating = conditions.rating || 'good';
        if (rating === 'excellent' || rating === 'good') {
            recommendationEl.classList.add('bg-success', 'bg-opacity-10', 'border-success');
            iconEl.className = 'fas fa-mountain text-success me-2';
        } else if (rating === 'fair' || rating === 'warning') {
            recommendationEl.classList.add('warning');
            iconEl.className = 'fas fa-exclamation-triangle text-warning me-2';
        } else {
            recommendationEl.classList.add('danger');
            iconEl.className = 'fas fa-times-circle text-danger me-2';
        }
        
        statusEl.textContent = rating.charAt(0).toUpperCase() + rating.slice(1);
        textEl.textContent = conditions.recommendations ? conditions.recommendations[0] : 'Analysez les conditions';
    }
    
    setElementText(selector, text) {
        const element = this.element.querySelector(selector);
        if (element) element.textContent = text;
    }
    
    hideLoading() {
        this.loadingEl.classList.add('d-none');
    }
    
    showError(message) {
        this.loadingEl.classList.add('d-none');
        this.errorEl.classList.remove('d-none');
        const errorText = this.errorEl.querySelector('.text-muted');
        if (errorText) {
            errorText.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i>${message}`;
        }
    }
}

// Auto-initialisation des widgets météo simple (secteurs)
document.addEventListener('DOMContentLoaded', function() {
    const weatherWidgets = document.querySelectorAll('.weather-widget[data-lat][data-lng]');
    weatherWidgets.forEach(widget => {
        new SimpleWeatherWidget(widget);
    });
});

// Export pour utilisation globale
window.SimpleWeatherWidget = SimpleWeatherWidget;

// Enregistrement du module WeatherWidget pour régions
TopoclimbCH.modules.register('weather-widget', ['utils', 'api'], (utils, api) => {
    
    class WeatherWidget {
        constructor(containerId, options = {}) {
            this.containerId = containerId;
            this.options = {
                updateInterval: 300000, // 5 minutes
                showForecast: true,
                showClimbingConditions: true,
                units: 'metric',
                ...options
            };
            
            this.container = null;
            this.weatherCache = new Map();
            this.updateTimer = null;
            
            this.coordinates = options.coordinates || null;
            this.regionData = options.regionData || null;
        }
        
        /**
         * Initialise le widget météo
         */
        init() {
            this.container = document.getElementById(this.containerId);
            if (!this.container) {
                console.warn(`Weather widget container #${this.containerId} not found`);
                return;
            }
            
            if (!this.coordinates && this.regionData) {
                this.coordinates = {
                    lat: this.regionData.coordinates_lat,
                    lng: this.regionData.coordinates_lng
                };
            }
            
            if (!this.coordinates) {
                console.warn('No coordinates provided for weather widget');
                return;
            }
            
            this.createWidget();
            this.loadWeatherData();
            this.startAutoUpdate();
            
            console.log('🌤️ WeatherWidget initialized');
        }
        
        /**
         * Crée la structure HTML du widget
         */
        createWidget() {
            this.container.innerHTML = `
                <div class=\"weather-widget\">
                    <div class=\"weather-header\">
                        <h6 class=\"weather-title\">
                            <i class=\"fa fa-cloud-sun\"></i>
                            Conditions météo
                        </h6>
                        <div class=\"weather-refresh\">
                            <button id=\"refresh-weather\" class=\"btn btn-sm btn-outline-secondary\">
                                <i class=\"fa fa-refresh\"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class=\"weather-loading\" id=\"weather-loading\">
                        <div class=\"spinner-border spinner-border-sm\" role=\"status\">
                            <span class=\"sr-only\">Chargement...</span>
                        </div>
                        <span class=\"ms-2\">Chargement météo...</span>
                    </div>
                    
                    <div class=\"weather-content\" id=\"weather-content\" style=\"display: none;\">
                        <div class=\"current-weather\" id=\"current-weather\"></div>
                        
                        ${this.options.showForecast ? '<div class=\"weather-forecast\" id=\"weather-forecast\"></div>' : ''}
                        
                        ${this.options.showClimbingConditions ? '<div class=\"climbing-conditions\" id=\"climbing-conditions\"></div>' : ''}
                    </div>
                    
                    <div class=\"weather-error\" id=\"weather-error\" style=\"display: none;\">
                        <div class=\"alert alert-warning\">
                            <i class=\"fa fa-exclamation-triangle\"></i>
                            Impossible de charger les données météo
                        </div>
                    </div>
                </div>
            `;
            
            // Événement de refresh
            const refreshBtn = this.container.querySelector('#refresh-weather');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', () => {
                    this.loadWeatherData(true);
                });
            }
        }
        
        /**
         * Charge les données météo
         */
        async loadWeatherData(forceRefresh = false) {
            const cacheKey = `${this.coordinates.lat},${this.coordinates.lng}`;
            
            // Vérifier le cache
            if (!forceRefresh && this.weatherCache.has(cacheKey)) {
                const cached = this.weatherCache.get(cacheKey);
                if (Date.now() - cached.timestamp < this.options.updateInterval) {
                    this.displayWeatherData(cached.data);
                    return;
                }
            }
            
            this.showLoading();
            
            try {
                const weatherData = await api.getWeather(
                    this.coordinates.lat,
                    this.coordinates.lng
                );
                
                // Mettre en cache
                this.weatherCache.set(cacheKey, {
                    data: weatherData,
                    timestamp: Date.now()
                });
                
                this.displayWeatherData(weatherData);
                this.hideLoading();
                
            } catch (error) {
                console.error('Weather data loading error:', error);
                this.showError();
            }
        }
        
        /**
         * Affiche les données météo
         */
        displayWeatherData(data) {
            this.displayCurrentWeather(data.current);
            
            if (this.options.showForecast && data.forecast) {
                this.displayForecast(data.forecast);
            }
            
            if (this.options.showClimbingConditions) {
                this.displayClimbingConditions(data);
            }
        }
        
        /**
         * Affiche la météo actuelle
         */
        displayCurrentWeather(current) {
            const container = this.container.querySelector('#current-weather');
            if (!container) return;
            
            const temp = Math.round(current.temperature);
            const feelsLike = Math.round(current.feels_like);
            const humidity = current.humidity;
            const windSpeed = Math.round(current.wind_speed * 3.6); // m/s to km/h
            const windDir = this.getWindDirection(current.wind_direction);
            
            container.innerHTML = `
                <div class=\"current-weather-main\">
                    <div class=\"weather-icon\">
                        ${this.getWeatherIcon(current.weather_code)}
                    </div>
                    <div class=\"weather-temp\">
                        <span class=\"temp-main\">${temp}°C</span>
                        <span class=\"temp-feels\">Ressenti ${feelsLike}°C</span>
                    </div>
                    <div class=\"weather-desc\">
                        ${this.getWeatherDescription(current.weather_code)}
                    </div>
                </div>
                
                <div class=\"weather-details\">
                    <div class=\"detail-item\">
                        <i class=\"fa fa-tint\"></i>
                        <span class=\"detail-label\">Humidité</span>
                        <span class=\"detail-value\">${humidity}%</span>
                    </div>
                    <div class=\"detail-item\">
                        <i class=\"fa fa-wind\"></i>
                        <span class=\"detail-label\">Vent</span>
                        <span class=\"detail-value\">${windSpeed} km/h ${windDir}</span>
                    </div>
                    <div class=\"detail-item\">
                        <i class=\"fa fa-barometer\"></i>
                        <span class=\"detail-label\">Pression</span>
                        <span class=\"detail-value\">${current.pressure} hPa</span>
                    </div>
                </div>
            `;
        }
        
        /**
         * Affiche les prévisions
         */
        displayForecast(forecast) {
            const container = this.container.querySelector('#weather-forecast');
            if (!container || !forecast.length) return;
            
            const forecastItems = forecast.slice(0, 5).map(day => `
                <div class=\"forecast-item\">
                    <div class=\"forecast-day\">
                        ${this.formatForecastDay(day.date)}
                    </div>
                    <div class=\"forecast-icon\">
                        ${this.getWeatherIcon(day.weather_code, 'small')}
                    </div>
                    <div class=\"forecast-temp\">
                        <span class=\"temp-max\">${Math.round(day.temp_max)}°</span>
                        <span class=\"temp-min\">${Math.round(day.temp_min)}°</span>
                    </div>
                    <div class=\"forecast-rain\">
                        ${day.precipitation ? `<i class=\"fa fa-droplet\"></i> ${day.precipitation}mm` : ''}
                    </div>
                </div>
            `).join('');
            
            container.innerHTML = `
                <h6 class=\"forecast-title\">Prévisions 5 jours</h6>
                <div class=\"forecast-grid\">
                    ${forecastItems}
                </div>
            `;
        }
        
        /**
         * Affiche les conditions d'escalade
         */
        displayClimbingConditions(data) {
            const container = this.container.querySelector('#climbing-conditions');
            if (!container) return;
            
            const conditions = this.evaluateClimbingConditions(data);
            
            container.innerHTML = `
                <h6 class=\"conditions-title\">
                    <i class=\"fa fa-mountain\"></i>
                    Conditions d'escalade
                </h6>
                
                <div class=\"conditions-overall\">
                    <div class=\"condition-score condition-${conditions.overall.level}\">
                        ${this.getConditionIcon(conditions.overall.level)}
                        <span class=\"condition-text\">${conditions.overall.text}</span>
                    </div>
                </div>
                
                <div class=\"conditions-details\">
                    <div class=\"condition-item\">
                        <span class=\"condition-label\">Température:</span>
                        <span class=\"condition-value condition-${conditions.temperature.level}\">
                            ${conditions.temperature.text}
                        </span>
                    </div>
                    <div class=\"condition-item\">
                        <span class=\"condition-label\">Vent:</span>
                        <span class=\"condition-value condition-${conditions.wind.level}\">
                            ${conditions.wind.text}
                        </span>
                    </div>
                    <div class=\"condition-item\">
                        <span class=\"condition-label\">Précipitations:</span>
                        <span class=\"condition-value condition-${conditions.precipitation.level}\">
                            ${conditions.precipitation.text}
                        </span>
                    </div>
                </div>
                
                ${conditions.advice ? `
                    <div class=\"conditions-advice\">
                        <i class=\"fa fa-lightbulb\"></i>
                        <span>${conditions.advice}</span>
                    </div>
                ` : ''}
            `;
        }
        
        /**
         * Évalue les conditions d'escalade
         */
        evaluateClimbingConditions(data) {
            const current = data.current;
            const temp = current.temperature;
            const windSpeed = current.wind_speed * 3.6; // km/h
            const precipitation = current.precipitation || 0;
            
            // Évaluation température
            let tempLevel, tempText;
            if (temp < 5) {
                tempLevel = 'poor';
                tempText = 'Très froid';
            } else if (temp < 15) {
                tempLevel = 'fair';
                tempText = 'Frais';
            } else if (temp < 25) {
                tempLevel = 'good';
                tempText = 'Idéal';
            } else if (temp < 30) {
                tempLevel = 'fair';
                tempText = 'Chaud';
            } else {
                tempLevel = 'poor';
                tempText = 'Très chaud';
            }
            
            // Évaluation vent
            let windLevel, windText;
            if (windSpeed < 10) {
                windLevel = 'good';
                windText = 'Calme';
            } else if (windSpeed < 20) {
                windLevel = 'fair';
                windText = 'Modéré';
            } else if (windSpeed < 30) {
                windLevel = 'poor';
                windText = 'Fort';
            } else {
                windLevel = 'poor';
                windText = 'Très fort';
            }
            
            // Évaluation précipitations
            let precipLevel, precipText;
            if (precipitation === 0) {
                precipLevel = 'good';
                precipText = 'Sec';
            } else if (precipitation < 2) {
                precipLevel = 'fair';
                precipText = 'Légères';
            } else {
                precipLevel = 'poor';
                precipText = 'Importantes';
            }
            
            // Évaluation globale
            const scores = { good: 3, fair: 2, poor: 1 };
            const avgScore = (scores[tempLevel] + scores[windLevel] + scores[precipLevel]) / 3;
            
            let overallLevel, overallText, advice;
            if (avgScore >= 2.5) {
                overallLevel = 'good';
                overallText = 'Excellentes';
                advice = 'Conditions idéales pour l\\'escalade !';
            } else if (avgScore >= 2) {
                overallLevel = 'fair';
                overallText = 'Correctes';
                advice = 'Escalade possible avec précautions.';
            } else {
                overallLevel = 'poor';
                overallText = 'Difficiles';
                advice = 'Escalade déconseillée. Restez prudents.';
            }
            
            return {
                overall: { level: overallLevel, text: overallText },
                temperature: { level: tempLevel, text: tempText },
                wind: { level: windLevel, text: windText },
                precipitation: { level: precipLevel, text: precipText },
                advice
            };
        }
        
        /**
         * Retourne l'icône météo
         */
        getWeatherIcon(code, size = 'normal') {
            const iconMap = {
                0: '☀️',   // Clear sky
                1: '🌤️',   // Mainly clear
                2: '⛅',   // Partly cloudy
                3: '☁️',   // Overcast
                45: '🌫️',  // Fog
                48: '🌫️',  // Depositing rime fog
                51: '🌦️',  // Light drizzle
                53: '🌧️',  // Moderate drizzle
                55: '🌧️',  // Dense drizzle
                61: '🌧️',  // Slight rain
                63: '🌧️',  // Moderate rain
                65: '🌧️',  // Heavy rain
                71: '🌨️',  // Slight snow
                73: '🌨️',  // Moderate snow
                75: '🌨️',  // Heavy snow
                95: '⛈️',  // Thunderstorm
                96: '⛈️',  // Thunderstorm with hail
                99: '⛈️'   // Thunderstorm with heavy hail
            };
            
            const icon = iconMap[code] || '❓';
            const sizeClass = size === 'small' ? 'weather-icon-small' : 'weather-icon-normal';
            
            return `<span class=\"${sizeClass}\">${icon}</span>`;
        }
        
        /**
         * Retourne la description météo
         */
        getWeatherDescription(code) {
            const descriptions = {
                0: 'Ciel dégagé',
                1: 'Principalement dégagé',
                2: 'Partiellement nuageux',
                3: 'Couvert',
                45: 'Brouillard',
                48: 'Brouillard givrant',
                51: 'Bruine légère',
                53: 'Bruine modérée',
                55: 'Bruine dense',
                61: 'Pluie légère',
                63: 'Pluie modérée',
                65: 'Pluie forte',
                71: 'Neige légère',
                73: 'Neige modérée',
                75: 'Neige forte',
                95: 'Orage',
                96: 'Orage avec grêle',
                99: 'Orage violent'
            };
            
            return descriptions[code] || 'Conditions inconnues';
        }
        
        /**
         * Convertit la direction du vent
         */
        getWindDirection(degrees) {
            const directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
            const index = Math.round(degrees / 45) % 8;
            return directions[index];
        }
        
        /**
         * Formate le jour pour les prévisions
         */
        formatForecastDay(dateString) {
            const date = new Date(dateString);
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            
            if (date.toDateString() === today.toDateString()) {
                return 'Aujourd\\'hui';
            } else if (date.toDateString() === tomorrow.toDateString()) {
                return 'Demain';
            } else {
                return date.toLocaleDateString('fr-FR', { weekday: 'short' });
            }
        }
        
        /**
         * Retourne l'icône de condition
         */
        getConditionIcon(level) {
            const icons = {
                good: '✅',
                fair: '⚠️',
                poor: '❌'
            };
            return icons[level] || '❓';
        }
        
        /**
         * Affiche le loading
         */
        showLoading() {
            const loading = this.container.querySelector('#weather-loading');
            const content = this.container.querySelector('#weather-content');
            const error = this.container.querySelector('#weather-error');
            
            if (loading) loading.style.display = 'block';
            if (content) content.style.display = 'none';
            if (error) error.style.display = 'none';
        }
        
        /**
         * Masque le loading
         */
        hideLoading() {
            const loading = this.container.querySelector('#weather-loading');
            const content = this.container.querySelector('#weather-content');
            
            if (loading) loading.style.display = 'none';
            if (content) content.style.display = 'block';
        }
        
        /**
         * Affiche l'erreur
         */
        showError() {
            const loading = this.container.querySelector('#weather-loading');
            const content = this.container.querySelector('#weather-content');
            const error = this.container.querySelector('#weather-error');
            
            if (loading) loading.style.display = 'none';
            if (content) content.style.display = 'none';
            if (error) error.style.display = 'block';
        }
        
        /**
         * Démarre la mise à jour automatique
         */
        startAutoUpdate() {
            if (this.updateTimer) {
                clearInterval(this.updateTimer);
            }
            
            this.updateTimer = setInterval(() => {
                this.loadWeatherData();
            }, this.options.updateInterval);
        }
        
        /**
         * Arrête la mise à jour automatique
         */
        stopAutoUpdate() {
            if (this.updateTimer) {
                clearInterval(this.updateTimer);
                this.updateTimer = null;
            }
        }
        
        /**
         * Nettoyage
         */
        destroy() {
            this.stopAutoUpdate();
            this.weatherCache.clear();
            
            if (this.container) {
                this.container.innerHTML = '';
            }
        }
    }
    
    return WeatherWidget;
});

console.log('🌤️ WeatherWidget module ready');