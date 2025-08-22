// Script de diagnostic pour les listes déroulantes
console.log('🔍 DIAGNOSTIC LISTES DÉROULANTES');

// Attendre que le DOM soit chargé
document.addEventListener('DOMContentLoaded', function() {
    console.log('📄 DOM chargé, diagnostic démarré');
    
    // Vérifier les éléments présents
    const elements = {
        'region_id': document.getElementById('region_id'),
        'site_id': document.getElementById('site_id'),
        'sector_id': document.getElementById('sector_id')
    };
    
    console.log('🔍 Éléments trouvés:');
    Object.entries(elements).forEach(([name, element]) => {
        if (element) {
            console.log(`  ✅ ${name}: trouvé`);
            console.log(`     - Disabled: ${element.disabled}`);
            console.log(`     - Options: ${element.options.length}`);
        } else {
            console.log(`  ❌ ${name}: NON TROUVÉ`);
        }
    });
    
    // Test API en direct
    testAPIConnectivity();
    
    // Ajouter les event listeners de base
    setupBasicCascade();
});

async function testAPIConnectivity() {
    console.log('🌐 Test connectivité API...');
    
    try {
        const response = await fetch('/api/regions');
        const data = await response.json();
        
        if (data.success) {
            console.log(`✅ API Regions OK: ${data.count} régions`);
        } else {
            console.log(`❌ API Regions Error: ${data.error}`);
        }
    } catch (error) {
        console.log(`❌ Erreur API: ${error.message}`);
    }
}

function setupBasicCascade() {
    const regionSelect = document.getElementById('region_id');
    const siteSelect = document.getElementById('site_id');
    
    if (!regionSelect || !siteSelect) {
        console.log('⚠️  Éléments cascade non trouvés pour setup');
        return;
    }
    
    console.log('🔗 Setup cascade de base région → site');
    
    regionSelect.addEventListener('change', async function() {
        const regionId = this.value;
        console.log(`🔄 Région changée: ${regionId}`);
        
        if (!regionId) {
            resetSiteSelector();
            return;
        }
        
        try {
            siteSelect.disabled = true;
            siteSelect.innerHTML = '<option value="">Chargement...</option>';
            
            const response = await fetch(`/api/regions/${regionId}/sites`);
            const data = await response.json();
            
            if (data.success) {
                console.log(`✅ Sites chargés: ${data.data.length}`);
                populateSiteSelector(data.data);
            } else {
                console.log(`❌ Erreur sites: ${data.error}`);
                resetSiteSelector();
            }
        } catch (error) {
            console.log(`❌ Erreur chargement sites: ${error.message}`);
            resetSiteSelector();
        }
    });
}

function populateSiteSelector(sites) {
    const siteSelect = document.getElementById('site_id');
    
    siteSelect.innerHTML = '<option value="">Choisissez un site...</option>';
    
    sites.forEach(site => {
        const option = document.createElement('option');
        option.value = site.id;
        option.textContent = site.name;
        siteSelect.appendChild(option);
    });
    
    siteSelect.disabled = false;
    console.log(`📋 ${sites.length} sites ajoutés au sélecteur`);
}

function resetSiteSelector() {
    const siteSelect = document.getElementById('site_id');
    siteSelect.innerHTML = '<option value="">Choisissez d\'abord une région...</option>';
    siteSelect.disabled = true;
    console.log('🔄 Site selector remis à zéro');
}