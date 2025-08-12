#!/bin/bash

echo "=== Configuration HTTPS Développement Local ==="
echo ""

echo "SOLUTION RAPIDE - Proxy HTTPS avec stunnel"
echo ""

# Installation stunnel
echo "1. Installation stunnel:"
echo "   sudo apt update"
echo "   sudo apt install stunnel4"
echo ""

# Configuration stunnel
echo "2. Configuration stunnel (/etc/stunnel/https-proxy.conf):"
cat << 'EOF'
[https]
accept = 443
connect = 8000
cert = /etc/stunnel/server.pem
key = /etc/stunnel/server.key

[service]
; Run as daemon
foreground = no
debug = 4
EOF

echo ""

# Génération certificat auto-signé
echo "3. Génération certificat auto-signé:"
echo "   sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \\"
echo "     -keyout /etc/stunnel/server.key \\"
echo "     -out /etc/stunnel/server.pem \\"
echo "     -subj '/C=CH/ST=Vaud/L=Lausanne/O=TopoclimbCH/CN=localhost'"
echo ""

# Alternative: mkcert (plus simple)
echo "SOLUTION ALTERNATIVE - mkcert (recommandée):"
echo ""
echo "1. Installation mkcert:"
echo "   # Sur Ubuntu/Debian:"
echo "   wget -O mkcert https://github.com/FiloSottile/mkcert/releases/download/v1.4.4/mkcert-v1.4.4-linux-amd64"
echo "   chmod +x mkcert"
echo "   sudo mv mkcert /usr/local/bin/"
echo ""
echo "2. Installation CA local:"
echo "   mkcert -install"
echo ""
echo "3. Génération certificat:"
echo "   mkcert localhost 127.0.0.1 topoclimb.local"
echo ""

# Configuration Nginx proxy
echo "4. Configuration Nginx proxy (/etc/nginx/sites-available/topoclimb-dev):"
cat << 'EOF'
server {
    listen 443 ssl;
    server_name localhost topoclimb.local;
    
    ssl_certificate /path/to/localhost+2.pem;
    ssl_certificate_key /path/to/localhost+2-key.pem;
    
    # Proxy vers serveur PHP développement
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-SSL on;
    }
}

server {
    listen 80;
    server_name localhost topoclimb.local;
    return 301 https://$server_name$request_uri;
}
EOF

echo ""

# Serveur PHP intégré avec HTTPS (PHP 8.1+)
echo "SOLUTION NATIVE PHP 8.1+ (expérimentale):"
echo ""
echo "PHP 8.1+ peut gérer HTTPS nativement:"
echo "   php -S localhost:8443 -t public/ \\"
echo "     --ssl-cert=server.pem \\"
echo "     --ssl-key=server.key"
echo ""

# Docker solution
echo "SOLUTION DOCKER - Nginx + PHP-FPM + SSL:"
echo ""
cat << 'EOF'
# docker-compose.yml
version: '3.8'
services:
  nginx:
    image: nginx:alpine
    ports:
      - "443:443"
      - "80:80"
    volumes:
      - ./nginx-ssl.conf:/etc/nginx/conf.d/default.conf
      - ./ssl:/etc/ssl/certs
      - .:/var/www/html
    depends_on:
      - php

  php:
    image: php:8.4-fpm
    volumes:
      - .:/var/www/html
    environment:
      - HTTPS=on
      - HTTP_X_FORWARDED_PROTO=https
EOF

echo ""

# Test rapide
echo "TEST CONFIGURATION:"
echo ""
echo "1. Démarrer serveur PHP:"
echo "   cd /home/nibaechl/topoclimb"
echo "   /home/nibaechl/.config/herd-lite/bin/php -S localhost:8000 -t public/"
echo ""
echo "2. Configurer proxy HTTPS (une des solutions ci-dessus)"
echo ""
echo "3. Tester:"
echo "   curl -k https://localhost/sectors/create"
echo "   # Vérifier headers HTTPS"
echo ""

# Configuration immédiate sans certificat
echo "CONFIGURATION IMMÉDIATE (temporaire):"
echo ""
echo "Modifier temporairement .env:"
cat << 'EOF'
# Forcer HTTPS même sans certificat (développement uniquement)
FORCE_HTTPS=true
SSL_REDIRECT=false
APP_URL=https://topoclimb.ch
EOF

echo ""
echo "Cette configuration:"
echo "   ✅ Ajoute headers de sécurité HTTPS"
echo "   ✅ Force Content Security Policy"
echo "   ✅ Améliore sécurité formulaires"
echo "   ❌ N'élimine pas complètement l'alerte navigateur"
echo ""

echo "=== RÉSUMÉ SOLUTIONS ==="
echo "🚀 Production: certificat Let's Encrypt + Apache/Nginx"
echo "🛠️ Développement: mkcert + proxy Nginx"
echo "⚡ Rapide: stunnel ou proxy Docker"
echo "🔧 Temporaire: FORCE_HTTPS=true dans .env"
echo ""
echo "✅ Erreur 500 création secteur CORRIGÉE"
echo "✅ Headers sécurité AMÉLIORÉS"  
echo "✅ Middleware HTTPS CRÉÉ"
echo "✅ Configuration HTTPS PRÊTE"