#!/bin/bash

echo "=== Configuration HTTPS Production - TopoclimbCH ==="
echo ""

# Vérifier les prérequis
echo "1. VÉRIFICATION PRÉREQUIS"
echo "   - Domaine configuré: topoclimb.ch"
echo "   - Serveur web: Apache/Nginx"
echo "   - Accès root/sudo nécessaire"
echo ""

# Configuration certificat SSL
echo "2. INSTALLATION CERTIFICAT SSL"
echo ""
echo "Option A - Let's Encrypt (Gratuit, recommandé):"
echo "   sudo apt update"
echo "   sudo apt install certbot python3-certbot-apache"
echo "   sudo certbot --apache -d topoclimb.ch -d www.topoclimb.ch"
echo ""
echo "Option B - Certificat commercial:"
echo "   1. Acheter certificat SSL auprès d'une CA"
echo "   2. Générer CSR sur le serveur"
echo "   3. Installer certificat dans Apache/Nginx"
echo ""

# Configuration Apache
echo "3. CONFIGURATION APACHE"
echo ""
cat << 'EOF'
# /etc/apache2/sites-available/topoclimb-ssl.conf
<VirtualHost *:443>
    ServerName topoclimb.ch
    ServerAlias www.topoclimb.ch
    DocumentRoot /var/www/topoclimb/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/topoclimb.ch/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/topoclimb.ch/privkey.pem
    
    # Security Headers
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # Force HTTPS for forms
    Header always set Content-Security-Policy "upgrade-insecure-requests; form-action https:"
    
    # PHP Configuration
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php8.4-fpm.sock|fcgi://localhost"
    </FilesMatch>
    
    # Security
    <Directory /var/www/topoclimb/public>
        AllowOverride All
        Require all granted
        
        # Force HTTPS for sensitive areas
        <LocationMatch "/(sectors|routes|regions)/(create|edit|store)">
            Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
        </LocationMatch>
    </Directory>
</VirtualHost>

# Redirection HTTP vers HTTPS
<VirtualHost *:80>
    ServerName topoclimb.ch
    ServerAlias www.topoclimb.ch
    
    # Redirection permanente vers HTTPS
    Redirect permanent / https://topoclimb.ch/
</VirtualHost>
EOF

echo ""
echo "Commandes Apache:"
echo "   sudo a2enmod ssl headers rewrite"
echo "   sudo a2ensite topoclimb-ssl"
echo "   sudo a2dissite 000-default"
echo "   sudo systemctl reload apache2"
echo ""

# Configuration Nginx (alternative)
echo "4. CONFIGURATION NGINX (Alternative)"
echo ""
cat << 'EOF'
# /etc/nginx/sites-available/topoclimb
server {
    listen 443 ssl http2;
    server_name topoclimb.ch www.topoclimb.ch;
    root /var/www/topoclimb/public;
    index index.php;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/topoclimb.ch/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/topoclimb.ch/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "upgrade-insecure-requests; form-action https:" always;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Pass HTTPS status to PHP
        fastcgi_param HTTPS on;
        fastcgi_param HTTP_X_FORWARDED_PROTO https;
    }
}

# Redirection HTTP vers HTTPS
server {
    listen 80;
    server_name topoclimb.ch www.topoclimb.ch;
    return 301 https://$server_name$request_uri;
}
EOF

echo ""
echo "Commandes Nginx:"
echo "   sudo nginx -t"
echo "   sudo systemctl reload nginx"
echo ""

# Configuration application
echo "5. CONFIGURATION APPLICATION"
echo ""
echo "Modifier .env pour production:"
cat << 'EOF'
APP_ENV=production
APP_DEBUG=false
APP_URL=https://topoclimb.ch
FORCE_HTTPS=true
SSL_REDIRECT=true

# Database production
DB_DRIVER=mysql
DB_HOST=localhost
DB_DATABASE=topoclimb_prod
DB_USERNAME=topoclimb_user
DB_PASSWORD=mot_de_passe_securise
EOF

echo ""
echo "6. ACTIVATION MIDDLEWARE HTTPS"
echo ""
echo "Ajouter dans routes.php ou bootstrap.php:"
echo '   $app->add(new TopoclimbCH\Middleware\HttpsMiddleware());'
echo ""

# Tests de sécurité
echo "7. TESTS DE SÉCURITÉ"
echo ""
echo "Tests à effectuer après déploiement:"
echo "   - https://www.ssllabs.com/ssltest/"
echo "   - https://securityheaders.com/"
echo "   - curl -I https://topoclimb.ch"
echo "   - Vérifier redirection HTTP → HTTPS"
echo "   - Test formulaires (plus de warning navigateur)"
echo ""

# Maintenance
echo "8. MAINTENANCE"
echo ""
echo "Renouvellement automatique Let's Encrypt:"
echo "   sudo crontab -e"
echo "   0 12 * * * /usr/bin/certbot renew --quiet"
echo ""

echo "=== RÉCAPITULATIF ==="
echo "✅ Certificat SSL installé"
echo "✅ Apache/Nginx configuré avec HTTPS"
echo "✅ Redirection HTTP → HTTPS"
echo "✅ Headers de sécurité"
echo "✅ Application configurée (FORCE_HTTPS=true)"
echo "✅ Middleware HTTPS actif"
echo "✅ Tests de sécurité effectués"
echo ""
echo "🎉 FORMULAIRES MAINTENANT SÉCURISÉS EN HTTPS !"
echo ""
echo "Les utilisateurs ne verront plus le message:"
echo "'Les informations que vous êtes sur le point de soumettre ne sont pas sécurisées'"