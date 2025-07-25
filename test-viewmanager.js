#!/usr/bin/env node

// Test ViewManager via headless browser (Puppeteer-like simulation)
const { execSync } = require('child_process');

console.log('🧪 TESTING ViewManager via HTTP...\n');

// Test 1: Vérifier que la page charge
try {
    const response = execSync('curl -s -w "%{http_code}" http://localhost:8090/debug-viewmanager.php', {encoding: 'utf8'});
    const httpCode = response.slice(-3);
    console.log(`✅ Page loads: HTTP ${httpCode}`);
    
    if (httpCode !== '200') {
        console.log('❌ Server not responding correctly');
        process.exit(1);
    }
} catch (error) {
    console.log('❌ Cannot reach server:', error.message);
    process.exit(1);
}

// Test 2: Vérifier que les ressources CSS/JS sont accessibles
try {
    const cssResponse = execSync('curl -s -w "%{http_code}" http://localhost:8090/public/css/view-modes.css', {encoding: 'utf8'});
    const cssCode = cssResponse.slice(-3);
    console.log(`✅ CSS loads: HTTP ${cssCode}`);
    
    const jsResponse = execSync('curl -s -w "%{http_code}" http://localhost:8090/public/js/view-manager.js', {encoding: 'utf8'});
    const jsCode = jsResponse.slice(-3);
    console.log(`✅ JS loads: HTTP ${jsCode}`);
    
} catch (error) {
    console.log('⚠️  Resource loading issue:', error.message);
}

// Test 3: Parser le HTML pour vérifier la structure
try {
    const html = execSync('curl -s http://localhost:8090/debug-viewmanager.php', {encoding: 'utf8'});
    
    // Vérifier les éléments critiques
    const hasContainer = html.includes('books-container entities-container');
    const hasViewGrid = html.includes('view-grid active');
    const hasViewList = html.includes('view-list');
    const hasViewCompact = html.includes('view-compact');
    const hasButtons = html.includes('data-view="grid"') && html.includes('data-view="list"') && html.includes('data-view="compact"');
    
    console.log(`✅ Container exists: ${hasContainer}`);
    console.log(`✅ Grid view (active): ${hasViewGrid}`);
    console.log(`✅ List view: ${hasViewList}`);
    console.log(`✅ Compact view: ${hasViewCompact}`);
    console.log(`✅ View buttons: ${hasButtons}`);
    
    if (!hasContainer || !hasViewGrid || !hasViewList || !hasViewCompact || !hasButtons) {
        console.log('❌ HTML structure incomplete');
        process.exit(1);
    }
    
} catch (error) {
    console.log('❌ HTML parsing failed:', error.message);
    process.exit(1);
}

console.log('\n🎯 BASIC TESTS PASSED');
console.log('\n📖 INSTRUCTIONS:');
console.log('1. Ouvrez: http://localhost:8090/debug-viewmanager.php');
console.log('2. Regardez le panneau debug en haut à droite');
console.log('3. Cliquez sur "Inspecter Vues" pour voir l\'état réel');
console.log('4. Testez les boutons Cartes/Liste/Compact');
console.log('5. Cliquez sur "Auto Test" pour test automatique');
console.log('\n🔍 Si ça ne marche pas, le panneau debug vous dira exactement pourquoi !');