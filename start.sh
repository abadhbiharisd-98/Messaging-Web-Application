#!/bin/bash
set -e

# Render dynamically assigns PORT, default to 10000 if not set
PORT="${PORT:-10000}"

echo "=================================================="
echo "Starting Messaging App on Apache (Port: ${PORT})..."
echo "=================================================="

# Update Apache ports configuration to listen on assigned PORT
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Render Reverse Proxy Port 10000 Stripping and AllowOverride Fix
cat << 'EOF' > /etc/apache2/conf-available/render-proxy.conf
ServerName localhost
UseCanonicalName Off
UseCanonicalPhysicalPort Off

<Directory /var/www/html>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
EOF

a2enconf render-proxy

# Ensure uploads directory structure exists and is writable
mkdir -p /var/www/html/uploads/avatars
mkdir -p /var/www/html/db
chown -R www-data:www-data /var/www/html/uploads /var/www/html/db
chmod -R 775 /var/www/html/uploads /var/www/html/db

echo "Apache configured successfully. Launching apache2-foreground..."
exec apache2-foreground
